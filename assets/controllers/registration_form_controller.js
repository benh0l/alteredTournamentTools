import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for Tournament registration form.
 *
 * Handles dynamic hero selection based on faction:
 * - When a faction is selected, filters hero dropdown to show only heroes from that faction
 * - When no faction is selected, shows placeholder message
 * - Shows hero portrait preview when a hero is selected
 * - Special mode "heroOutOfFaction": only shows heroes NOT from the selected faction
 *
 * Usage:
 * <form data-controller="registration-form"
 *       data-registration-form-heroes-value='{"axiom": {...}, ...}'
 *       data-registration-form-hero-out-of-faction-value="false">
 *     <select data-registration-form-target="faction" data-action="change->registration-form#factionChanged">
 *     <select data-registration-form-target="hero">
 *     <div data-registration-form-target="heroPreview">...</div>
 * </form>
 */
export default class extends Controller {
    static targets = [
        'faction',
        'faction2',
        'hero',
        'heroPreview',
        'heroImageContainer',
        'heroImage',
        'heroPlaceholder',
        'heroName'
    ];

    static values = {
        heroes: Object, // { faction: { heroKey: heroLabel, ... }, ... }
        heroOutOfFaction: { type: Boolean, default: false },
        bifaction: { type: Boolean, default: false }
    };

    // Faction colors for border styling
    factionColors = {
        'axiom': '#7a4d25',
        'bravos': '#ef4444',
        'lyra': '#e394da',
        'muna': '#22c55e',
        'ordis': '#3b82f6',
        'yzmir': '#a855f7',
    };

    connect() {
        // Store original hero options for restoration
        this.originalHeroOptions = [];
        this.initialHeroValue = null;

        if (this.hasHeroTarget) {
            const options = this.heroTarget.querySelectorAll('option');
            options.forEach(option => {
                this.originalHeroOptions.push({
                    value: option.value,
                    text: option.textContent,
                    selected: option.selected
                });
                // Store the initially selected hero value
                if (option.selected && option.value) {
                    this.initialHeroValue = option.value;
                }
            });

            // Add change listener to hero select
            this.heroTarget.addEventListener('change', this.heroChanged.bind(this));
        }

        // Initialize hero select based on current faction value
        this.factionChanged(true);
    }

    factionChanged(isInitialLoad = false) {
        if (!this.hasHeroTarget || !this.hasFactionTarget) {
            return;
        }

        const selectedFaction = this.factionTarget.value;
        const selectedFaction2 = this.hasFaction2Target ? this.faction2Target.value : null;
        const heroSelect = this.heroTarget;
        const isOutOfFaction = this.heroOutOfFactionValue;
        const isBifaction = this.bifactionValue;

        // Clear current options except placeholder
        heroSelect.innerHTML = '';

        // Update border color of hero preview
        if (this.hasHeroImageContainerTarget) {
            const color = this.factionColors[selectedFaction] || 'var(--border-muted)';
            this.heroImageContainerTarget.style.borderColor = color;
        }

        // Bifaction mode: need both factions selected
        if (isBifaction) {
            if (!selectedFaction || !selectedFaction2) {
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Selectionnez d\'abord vos deux factions';
                placeholder.disabled = true;
                placeholder.selected = true;
                heroSelect.appendChild(placeholder);
                heroSelect.disabled = true;
                this.resetHeroPreview();
                return;
            }

            // Add placeholder
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selectionnez un heros';
            heroSelect.appendChild(placeholder);

            // Show heroes from BOTH selected factions
            [selectedFaction, selectedFaction2].forEach(faction => {
                const factionHeroes = this.heroesValue[faction] || {};
                if (Object.keys(factionHeroes).length > 0) {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = this.getFactionLabel(faction);

                    Object.entries(factionHeroes).forEach(([key, label]) => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = label;
                        option.dataset.faction = faction;
                        optgroup.appendChild(option);
                    });

                    heroSelect.appendChild(optgroup);
                }
            });

            heroSelect.disabled = false;
            // Restore initial hero selection on initial load
            if (isInitialLoad && this.initialHeroValue) {
                this.restoreInitialHero();
            } else {
                this.resetHeroPreview();
            }
            return;
        }

        if (!selectedFaction) {
            // No faction selected - show placeholder
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selectionnez d\'abord une faction';
            placeholder.disabled = true;
            placeholder.selected = true;
            heroSelect.appendChild(placeholder);
            heroSelect.disabled = true;

            // Reset hero preview
            this.resetHeroPreview();
            return;
        }

        // Add placeholder
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Selectionnez un heros';
        heroSelect.appendChild(placeholder);

        if (isOutOfFaction) {
            // Out of Faction mode: show heroes from OTHER factions only
            Object.entries(this.heroesValue).forEach(([faction, heroes]) => {
                if (faction !== selectedFaction) {
                    // Add faction group label
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = this.getFactionLabel(faction);

                    Object.entries(heroes).forEach(([key, label]) => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = label;
                        option.dataset.faction = faction;
                        optgroup.appendChild(option);
                    });

                    heroSelect.appendChild(optgroup);
                }
            });
        } else {
            // Normal mode: show heroes from the selected faction only
            const factionHeroes = this.heroesValue[selectedFaction] || {};
            Object.entries(factionHeroes).forEach(([key, label]) => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = label;
                heroSelect.appendChild(option);
            });
        }

        heroSelect.disabled = false;

        // Restore initial hero selection on initial load, otherwise reset preview
        if (isInitialLoad && this.initialHeroValue) {
            this.restoreInitialHero();
        } else {
            this.resetHeroPreview();
        }
    }

    getFactionLabel(faction) {
        const labels = {
            'axiom': 'Axiom',
            'bravos': 'Bravos',
            'lyra': 'Lyra',
            'muna': 'Muna',
            'ordis': 'Ordis',
            'yzmir': 'Yzmir'
        };
        return labels[faction] || faction;
    }

    heroChanged() {
        if (!this.hasHeroTarget) {
            return;
        }

        const selectedHero = this.heroTarget.value;
        let selectedFaction = this.hasFactionTarget ? this.factionTarget.value : null;

        if (!selectedHero) {
            this.resetHeroPreview();
            return;
        }

        // Get hero label from the selected option
        const selectedOption = this.heroTarget.options[this.heroTarget.selectedIndex];
        const heroLabel = selectedOption ? selectedOption.textContent : '';

        // In Out of Faction mode or Bifaction mode, get the hero's actual faction for border color
        if ((this.heroOutOfFactionValue || this.bifactionValue) && selectedOption && selectedOption.dataset.faction) {
            selectedFaction = selectedOption.dataset.faction;
        }

        // Update hero preview
        this.updateHeroPreview(selectedHero, heroLabel, selectedFaction);
    }

    updateHeroPreview(heroKey, heroLabel, faction) {
        // Update image
        if (this.hasHeroImageTarget && this.hasHeroPlaceholderTarget) {
            const imagePath = `/images/heroes/${heroKey}.png`;
            this.heroImageTarget.src = imagePath;
            this.heroImageTarget.alt = heroLabel;
            this.heroImageTarget.classList.remove('hidden');
            this.heroPlaceholderTarget.classList.add('hidden');

            // Handle image load error
            this.heroImageTarget.onerror = () => {
                this.heroImageTarget.classList.add('hidden');
                this.heroPlaceholderTarget.classList.remove('hidden');
            };
        }

        // Update name
        if (this.hasHeroNameTarget) {
            this.heroNameTarget.textContent = heroLabel;
        }

        // Update border color
        if (this.hasHeroImageContainerTarget && faction) {
            const color = this.factionColors[faction] || 'var(--border-muted)';
            this.heroImageContainerTarget.style.borderColor = color;
        }
    }

    resetHeroPreview() {
        // Hide image, show placeholder
        if (this.hasHeroImageTarget) {
            this.heroImageTarget.classList.add('hidden');
            this.heroImageTarget.src = '';
        }

        if (this.hasHeroPlaceholderTarget) {
            this.heroPlaceholderTarget.classList.remove('hidden');
        }

        // Reset name
        if (this.hasHeroNameTarget) {
            this.heroNameTarget.textContent = 'Selectionnez un heros';
        }

        // Reset border color if no faction selected
        if (this.hasHeroImageContainerTarget && this.hasFactionTarget && !this.factionTarget.value) {
            this.heroImageContainerTarget.style.borderColor = 'var(--border-muted)';
        }
    }

    restoreInitialHero() {
        if (!this.hasHeroTarget || !this.initialHeroValue) {
            return;
        }

        const heroSelect = this.heroTarget;

        // Find and select the initial hero option
        const options = heroSelect.querySelectorAll('option');
        let found = false;
        options.forEach(option => {
            if (option.value === this.initialHeroValue) {
                option.selected = true;
                found = true;
            }
        });

        // If found, trigger heroChanged to update the preview
        if (found) {
            this.heroChanged();
        } else {
            this.resetHeroPreview();
        }

        // Clear initial value so subsequent faction changes don't restore it
        this.initialHeroValue = null;
    }
}
