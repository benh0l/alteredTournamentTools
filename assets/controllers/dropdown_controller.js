import { Controller } from '@hotwired/stimulus';

/**
 * Simple dropdown controller for menus.
 *
 * Usage:
 * <div data-controller="dropdown">
 *     <button data-action="click->dropdown#toggle">Toggle</button>
 *     <div data-dropdown-target="menu" class="hidden">Menu content</div>
 * </div>
 */
export default class extends Controller {
    static targets = ['menu'];

    connect() {
        // Close dropdown when clicking outside
        this.clickOutsideHandler = this.clickOutside.bind(this);
        document.addEventListener('click', this.clickOutsideHandler);
    }

    disconnect() {
        document.removeEventListener('click', this.clickOutsideHandler);
    }

    toggle(event) {
        event.stopPropagation();
        this.menuTarget.classList.toggle('hidden');
    }

    clickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.menuTarget.classList.add('hidden');
        }
    }
}
