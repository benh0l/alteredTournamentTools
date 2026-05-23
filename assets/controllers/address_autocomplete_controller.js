import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for address autocomplete using Photon API (OpenStreetMap).
 *
 * Provides worldwide address search with geocoding.
 * No API key required, free to use.
 *
 * Usage:
 * <div data-controller="address-autocomplete">
 *     <input type="text" data-address-autocomplete-target="input" />
 *     <input type="hidden" data-address-autocomplete-target="latitude" />
 *     <input type="hidden" data-address-autocomplete-target="longitude" />
 * </div>
 */
export default class extends Controller {
    static targets = ['input', 'latitude', 'longitude'];

    static values = {
        minChars: { type: Number, default: 3 },
        debounceMs: { type: Number, default: 300 },
        limit: { type: Number, default: 5 }
    };

    connect() {
        console.log('Address autocomplete controller connected');

        this.debounceTimer = null;
        this.selectedIndex = -1;
        this.results = [];
        this.suggestionsContainer = null;

        // Create suggestions container
        this.createSuggestionsContainer();

        // Bind event listeners
        this.boundOnInput = this.onInput.bind(this);
        this.boundOnKeyDown = this.onKeyDown.bind(this);
        this.boundOnBlur = this.onBlur.bind(this);
        this.boundOnFocus = this.onFocus.bind(this);

        this.inputTarget.addEventListener('input', this.boundOnInput);
        this.inputTarget.addEventListener('keydown', this.boundOnKeyDown);
        this.inputTarget.addEventListener('blur', this.boundOnBlur);
        this.inputTarget.addEventListener('focus', this.boundOnFocus);

        // Add autocomplete off to prevent browser autocomplete
        this.inputTarget.setAttribute('autocomplete', 'off');
    }

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Remove event listeners
        this.inputTarget.removeEventListener('input', this.boundOnInput);
        this.inputTarget.removeEventListener('keydown', this.boundOnKeyDown);
        this.inputTarget.removeEventListener('blur', this.boundOnBlur);
        this.inputTarget.removeEventListener('focus', this.boundOnFocus);

        // Remove suggestions container from body
        if (this.suggestionsContainer && this.suggestionsContainer.parentNode) {
            this.suggestionsContainer.parentNode.removeChild(this.suggestionsContainer);
        }
    }

    createSuggestionsContainer() {
        this.suggestionsContainer = document.createElement('ul');
        this.suggestionsContainer.className = 'address-suggestions rounded-xl shadow-2xl overflow-hidden';
        this.suggestionsContainer.style.cssText = `
            background-color: var(--card-bg);
            border: 1px solid var(--border-default);
            display: none;
            z-index: 99999;
            position: fixed;
            max-height: 300px;
            overflow-y: auto;
        `;

        // Append to body to avoid overflow:hidden issues
        document.body.appendChild(this.suggestionsContainer);
    }

    positionSuggestions() {
        if (!this.suggestionsContainer) return;

        const inputRect = this.inputTarget.getBoundingClientRect();

        // position: fixed uses viewport coordinates, no need to add scroll
        this.suggestionsContainer.style.top = `${inputRect.bottom + 4}px`;
        this.suggestionsContainer.style.left = `${inputRect.left}px`;
        this.suggestionsContainer.style.width = `${inputRect.width}px`;
    }

    onInput(event) {
        const query = event.target.value.trim();

        // Clear coordinates when user types
        this.clearCoordinates();

        if (query.length < this.minCharsValue) {
            this.hideSuggestions();
            return;
        }

        // Debounce the API call
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        this.debounceTimer = setTimeout(() => {
            this.search(query);
        }, this.debounceMsValue);
    }

    onKeyDown(event) {
        if (!this.results.length) return;

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
                this.highlightSuggestion();
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                this.highlightSuggestion();
                break;
            case 'Enter':
                event.preventDefault();
                if (this.selectedIndex >= 0) {
                    this.selectSuggestion(this.results[this.selectedIndex]);
                }
                break;
            case 'Escape':
                this.hideSuggestions();
                break;
        }
    }

    onBlur() {
        // Delay hiding to allow click on suggestion
        setTimeout(() => this.hideSuggestions(), 250);
    }

    onFocus() {
        if (this.results.length > 0) {
            this.showSuggestions();
        }
    }

    async search(query) {
        console.log('Searching for:', query);

        try {
            const url = new URL('https://photon.komoot.io/api/');
            url.searchParams.set('q', query);
            url.searchParams.set('limit', this.limitValue);

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Photon API error');
            }

            const data = await response.json();
            console.log('Search results:', data);

            this.results = data.features || [];
            this.selectedIndex = -1;

            this.renderSuggestions();
        } catch (error) {
            console.error('Address search error:', error);
            this.results = [];
            this.hideSuggestions();
        }
    }

    renderSuggestions() {
        if (!this.results.length || !this.suggestionsContainer) {
            this.hideSuggestions();
            return;
        }

        const html = this.results.map((result, index) => {
            const props = result.properties;
            const label = this.formatAddress(props);
            const sublabel = this.formatSublabel(props);

            return `
                <li class="suggestion-item px-4 py-3 cursor-pointer transition-colors"
                    style="border-bottom: 1px solid var(--border-default); background-color: var(--card-bg);"
                    data-index="${index}">
                    <div class="font-medium" style="color: var(--text-primary);">${this.escapeHtml(label)}</div>
                    ${sublabel ? `<div class="text-sm" style="color: var(--text-muted);">${this.escapeHtml(sublabel)}</div>` : ''}
                </li>
            `;
        }).join('');

        this.suggestionsContainer.innerHTML = html;

        // Add click handlers to suggestions
        this.suggestionsContainer.querySelectorAll('.suggestion-item').forEach((item) => {
            item.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.dataset.index, 10);
                if (!isNaN(index) && this.results[index]) {
                    this.selectSuggestion(this.results[index]);
                }
            });
            item.addEventListener('mouseenter', (e) => {
                const index = parseInt(e.currentTarget.dataset.index, 10);
                if (!isNaN(index)) {
                    this.selectedIndex = index;
                    this.highlightSuggestion();
                }
            });
        });

        this.showSuggestions();
    }

    formatAddress(props) {
        const parts = [];

        if (props.name) parts.push(props.name);
        if (props.street) {
            let street = props.street;
            if (props.housenumber) street = `${props.housenumber} ${street}`;
            if (!parts.includes(street)) parts.push(street);
        }

        return parts.join(', ') || props.name || 'Unknown location';
    }

    formatSublabel(props) {
        const parts = [];

        if (props.city) parts.push(props.city);
        if (props.state) parts.push(props.state);
        if (props.country) parts.push(props.country);

        return parts.join(', ');
    }

    highlightSuggestion() {
        if (!this.suggestionsContainer) return;

        const items = this.suggestionsContainer.querySelectorAll('.suggestion-item');
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.style.backgroundColor = 'var(--color-primary-100)';
            } else {
                item.style.backgroundColor = 'var(--card-bg)';
            }
        });
    }

    selectSuggestion(result) {
        const props = result.properties;
        const coords = result.geometry.coordinates; // [longitude, latitude]

        // Build full address
        const fullAddress = this.buildFullAddress(props);

        // Update input
        this.inputTarget.value = fullAddress;

        // Update coordinates
        if (this.hasLatitudeTarget && this.hasLongitudeTarget) {
            this.latitudeTarget.value = coords[1]; // latitude
            this.longitudeTarget.value = coords[0]; // longitude
            console.log('Coordinates set:', coords[1], coords[0]);
        }

        // Hide suggestions
        this.hideSuggestions();
        this.results = [];

        // Dispatch event for other controllers to listen
        this.element.dispatchEvent(new CustomEvent('address:selected', {
            bubbles: true,
            detail: {
                address: fullAddress,
                latitude: coords[1],
                longitude: coords[0],
                properties: props
            }
        }));
    }

    buildFullAddress(props) {
        const parts = [];

        // Street address
        if (props.street) {
            let street = props.street;
            if (props.housenumber) street = `${props.housenumber} ${street}`;
            parts.push(street);
        } else if (props.name) {
            parts.push(props.name);
        }

        // City
        if (props.city) {
            let city = props.city;
            if (props.postcode) city = `${props.postcode} ${city}`;
            parts.push(city);
        }

        // Country
        if (props.country) {
            parts.push(props.country);
        }

        return parts.join(', ');
    }

    clearCoordinates() {
        if (this.hasLatitudeTarget) this.latitudeTarget.value = '';
        if (this.hasLongitudeTarget) this.longitudeTarget.value = '';
    }

    showSuggestions() {
        if (!this.suggestionsContainer) return;

        this.positionSuggestions();
        this.suggestionsContainer.style.display = 'block';
    }

    hideSuggestions() {
        if (this.suggestionsContainer) {
            this.suggestionsContainer.style.display = 'none';
        }
        this.selectedIndex = -1;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
