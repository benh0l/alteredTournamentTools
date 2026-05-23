import { Controller } from '@hotwired/stimulus';

console.log('Player search controller FILE LOADED');

/**
 * Stimulus controller for player search autocomplete.
 *
 * Provides AJAX-based player search for head-to-head comparison feature.
 *
 * Usage:
 * <div data-controller="player-search" data-player-search-url-value="/profile/search-players">
 *     <input type="text" data-player-search-target="input" data-action="input->player-search#search focus->player-search#search" />
 *     <div data-player-search-target="results"></div>
 * </div>
 */
export default class extends Controller {
    static targets = ['input', 'results'];

    static values = {
        url: String,
        minChars: { type: Number, default: 2 },
        debounceMs: { type: Number, default: 300 }
    };

    connect() {
        console.log('Player search controller connected');
        console.log('URL value:', this.urlValue);

        this.debounceTimer = null;
        this.selectedIndex = -1;
        this.players = [];

        // Bind event listeners
        this.boundOnKeyDown = this.onKeyDown.bind(this);
        this.boundOnBlur = this.onBlur.bind(this);

        this.inputTarget.addEventListener('keydown', this.boundOnKeyDown);
        this.inputTarget.addEventListener('blur', this.boundOnBlur);

        // Add autocomplete off to prevent browser autocomplete
        this.inputTarget.setAttribute('autocomplete', 'off');
    }

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        this.inputTarget.removeEventListener('keydown', this.boundOnKeyDown);
        this.inputTarget.removeEventListener('blur', this.boundOnBlur);
    }

    search() {
        const query = this.inputTarget.value.trim();
        console.log('Search called with query:', query);

        if (query.length < this.minCharsValue) {
            this.hideResults();
            return;
        }

        // Debounce the API call
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        this.debounceTimer = setTimeout(() => {
            this.fetchPlayers(query);
        }, this.debounceMsValue);
    }

    onKeyDown(event) {
        if (!this.players.length) return;

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.players.length - 1);
                this.highlightResult();
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                this.highlightResult();
                break;
            case 'Enter':
                event.preventDefault();
                if (this.selectedIndex >= 0) {
                    this.selectPlayer(this.players[this.selectedIndex]);
                }
                break;
            case 'Escape':
                this.hideResults();
                break;
        }
    }

    onBlur() {
        // Delay hiding to allow click on result
        setTimeout(() => this.hideResults(), 250);
    }

    async fetchPlayers(query) {
        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('q', query);

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Search error');
            }

            const data = await response.json();

            this.players = data || [];
            this.selectedIndex = -1;

            this.renderResults();
        } catch (error) {
            console.error('Player search error:', error);
            this.players = [];
            this.hideResults();
        }
    }

    renderResults() {
        if (!this.players.length) {
            this.resultsTarget.innerHTML = `
                <div class="px-4 py-3 text-sm" style="color: var(--text-muted);">
                    Aucun joueur trouvé
                </div>
            `;
            this.showResults();
            return;
        }

        const html = this.players.map((player, index) => {
            const initial = player.pseudo ? player.pseudo.charAt(0).toUpperCase() : '?';
            const avatarHtml = player.avatar
                ? `<img src="/uploads/avatars/${this.escapeHtml(player.avatar)}" alt="" class="w-10 h-10 rounded-full object-cover">`
                : `<div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold" style="background-color: var(--bg-subtle); color: var(--color-primary-500);">${initial}</div>`;

            return `
                <a href="/profile/head-to-head/${player.id}"
                   class="player-result flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50"
                   style="border-bottom: 1px solid var(--border-default);"
                   data-index="${index}">
                    ${avatarHtml}
                    <div>
                        <div class="font-medium" style="color: var(--text-primary);">${this.escapeHtml(player.displayName)}</div>
                        <div class="text-sm" style="color: var(--text-muted);">@${this.escapeHtml(player.pseudo)}</div>
                    </div>
                </a>
            `;
        }).join('');

        this.resultsTarget.innerHTML = html;

        // Add hover handlers
        this.resultsTarget.querySelectorAll('.player-result').forEach((item) => {
            item.addEventListener('mouseenter', (e) => {
                const index = parseInt(e.currentTarget.dataset.index, 10);
                if (!isNaN(index)) {
                    this.selectedIndex = index;
                    this.highlightResult();
                }
            });
        });

        this.showResults();
    }

    highlightResult() {
        const items = this.resultsTarget.querySelectorAll('.player-result');
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.style.backgroundColor = 'var(--color-primary-100)';
            } else {
                item.style.backgroundColor = '';
            }
        });
    }

    selectPlayer(player) {
        // Navigate to head-to-head page
        window.location.href = `/profile/head-to-head/${player.id}`;
    }

    showResults() {
        this.resultsTarget.style.display = 'block';
    }

    hideResults() {
        this.resultsTarget.style.display = 'none';
        this.selectedIndex = -1;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
