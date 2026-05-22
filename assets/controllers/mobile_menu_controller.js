import { Controller } from '@hotwired/stimulus';

/**
 * Mobile menu controller for responsive navigation.
 *
 * Usage:
 * <nav data-controller="mobile-menu">
 *     <button data-action="click->mobile-menu#toggle" data-mobile-menu-target="button">
 *         <span data-mobile-menu-target="iconOpen">Menu</span>
 *         <span data-mobile-menu-target="iconClose" class="hidden">Close</span>
 *     </button>
 *     <div data-mobile-menu-target="panel" class="hidden">Menu content</div>
 * </nav>
 */
export default class extends Controller {
    static targets = ['panel', 'button', 'iconOpen', 'iconClose'];

    connect() {
        this.isOpen = false;
        this.escapeHandler = this.handleEscape.bind(this);
    }

    disconnect() {
        document.removeEventListener('keydown', this.escapeHandler);
        document.body.classList.remove('overflow-hidden');
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.isOpen = true;
        this.panelTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', this.escapeHandler);

        // Update button aria state
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'true');
        }

        // Toggle icons
        if (this.hasIconOpenTarget) this.iconOpenTarget.classList.add('hidden');
        if (this.hasIconCloseTarget) this.iconCloseTarget.classList.remove('hidden');

        // Animate panel
        requestAnimationFrame(() => {
            this.panelTarget.classList.add('mobile-menu-open');
        });
    }

    close() {
        this.isOpen = false;
        this.panelTarget.classList.remove('mobile-menu-open');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', this.escapeHandler);

        // Update button aria state
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'false');
        }

        // Toggle icons
        if (this.hasIconOpenTarget) this.iconOpenTarget.classList.remove('hidden');
        if (this.hasIconCloseTarget) this.iconCloseTarget.classList.add('hidden');

        // Hide panel after animation
        setTimeout(() => {
            if (!this.isOpen) {
                this.panelTarget.classList.add('hidden');
            }
        }, 300);
    }

    handleEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
