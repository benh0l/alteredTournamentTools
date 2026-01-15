import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for theme selection.
 *
 * Manages color themes (orange, blue, green, purple, rose) and
 * light/dark mode switching with persistence to localStorage and database.
 *
 * Usage:
 * <div data-controller="theme-selector"
 *      data-theme-selector-color-value="orange"
 *      data-theme-selector-mode-value="light"
 *      data-theme-selector-save-url-value="/api/theme">
 *     <button data-action="click->theme-selector#toggleMenu">Toggle</button>
 *     <div data-theme-selector-target="menu" class="hidden">...</div>
 * </div>
 */
export default class extends Controller {
    static targets = ['menu', 'colorButton', 'modeToggle', 'modeIcon'];
    static values = {
        color: { type: String, default: 'orange' },
        mode: { type: String, default: 'light' },
        saveUrl: String
    };

    connect() {
        // Load theme from localStorage (takes priority for immediate apply)
        this.loadTheme();
        this.applyTheme();
        this.updateUI();

        // Close menu on outside click
        this.boundCloseOnClickOutside = this.closeOnClickOutside.bind(this);
        document.addEventListener('click', this.boundCloseOnClickOutside);
    }

    disconnect() {
        document.removeEventListener('click', this.boundCloseOnClickOutside);
    }

    loadTheme() {
        const saved = localStorage.getItem('theme');
        if (saved) {
            try {
                const { color, mode } = JSON.parse(saved);
                if (color) this.colorValue = color;
                if (mode) this.modeValue = mode;
            } catch (e) {
                console.warn('Invalid theme in localStorage:', e);
            }
        }
    }

    applyTheme() {
        document.documentElement.setAttribute('data-theme-color', this.colorValue);
        document.documentElement.setAttribute('data-theme-mode', this.modeValue);
    }

    toggleMenu(event) {
        event.preventDefault();
        event.stopPropagation();
        this.menuTarget.classList.toggle('hidden');
    }

    closeOnClickOutside(event) {
        if (!this.element.contains(event.target) && !this.menuTarget.classList.contains('hidden')) {
            this.menuTarget.classList.add('hidden');
        }
    }

    selectColor(event) {
        event.preventDefault();
        const color = event.currentTarget.dataset.color;
        if (color && color !== this.colorValue) {
            this.colorValue = color;
            this.saveAndApply();
        }
    }

    toggleMode(event) {
        event.preventDefault();
        this.modeValue = this.modeValue === 'light' ? 'dark' : 'light';
        this.saveAndApply();
    }

    saveAndApply() {
        // Save to localStorage
        localStorage.setItem('theme', JSON.stringify({
            color: this.colorValue,
            mode: this.modeValue
        }));

        // Apply theme immediately
        this.applyTheme();
        this.updateUI();

        // Save to database if user is logged in
        if (this.saveUrlValue) {
            fetch(this.saveUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    color: this.colorValue,
                    mode: this.modeValue
                })
            }).catch(err => {
                console.warn('Failed to save theme to server:', err);
            });
        }

        // Close menu after selection
        this.menuTarget.classList.add('hidden');
    }

    updateUI() {
        // Update color buttons active state
        if (this.hasColorButtonTarget) {
            this.colorButtonTargets.forEach(btn => {
                const isActive = btn.dataset.color === this.colorValue;
                btn.classList.toggle('ring-2', isActive);
                btn.classList.toggle('ring-white', isActive);
                btn.classList.toggle('ring-offset-2', isActive);
            });
        }

        // Update mode toggle icon
        if (this.hasModeIconTarget) {
            const isDark = this.modeValue === 'dark';
            this.modeIconTargets.forEach(icon => {
                if (icon.dataset.modeIcon === 'sun') {
                    icon.classList.toggle('hidden', !isDark);
                } else if (icon.dataset.modeIcon === 'moon') {
                    icon.classList.toggle('hidden', isDark);
                }
            });
        }

        // Update toggle switch position
        if (this.hasModeToggleTarget) {
            const isDark = this.modeValue === 'dark';
            const toggle = this.modeToggleTarget.querySelector('[data-toggle-knob]');
            if (toggle) {
                toggle.classList.toggle('translate-x-4', isDark);
            }
        }
    }
}
