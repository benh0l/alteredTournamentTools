import { Controller } from '@hotwired/stimulus';

/**
 * Button Loader Controller
 *
 * Shows a loading spinner on buttons during form submission.
 * Prevents double-click and provides visual feedback.
 *
 * Usage:
 *   <form data-controller="button-loader">
 *     <button type="submit">Enregistrer</button>
 *   </form>
 *
 * Or on individual buttons:
 *   <button type="submit"
 *           data-controller="button-loader"
 *           data-button-loader-text-value="Enregistrement...">
 *     Enregistrer
 *   </button>
 */
export default class extends Controller {
    static values = {
        text: { type: String, default: 'En cours...' }
    };

    connect() {
        // If controller is on a form, listen to submit event
        if (this.element.tagName === 'FORM') {
            this.element.addEventListener('submit', this.handleSubmit.bind(this));
        }
        // If controller is on a button, find the parent form
        else if (this.element.tagName === 'BUTTON') {
            this.element.addEventListener('click', this.handleClick.bind(this));
        }
    }

    handleSubmit(event) {
        // Find all submit buttons in the form
        const buttons = this.element.querySelectorAll('button[type="submit"], input[type="submit"]');
        buttons.forEach(button => this.startLoading(button));
    }

    handleClick(event) {
        // Only handle if it's a submit button
        if (this.element.type === 'submit') {
            const form = this.element.closest('form');
            if (form && form.checkValidity()) {
                this.startLoading(this.element);
            }
        }
    }

    startLoading(button) {
        // Prevent double-click
        if (button.disabled) return;

        // Store original content
        button.dataset.originalContent = button.innerHTML;
        button.dataset.originalWidth = button.offsetWidth + 'px';

        // Set minimum width to prevent shrinking
        button.style.minWidth = button.dataset.originalWidth;

        // Get loading text
        const loadingText = button.dataset.buttonLoaderTextValue || this.textValue;

        // Replace content with spinner + text
        button.innerHTML = `
            <span class="btn-spinner"></span>
            <span>${loadingText}</span>
        `;

        // Disable button
        button.disabled = true;
        button.classList.add('btn-loading');
    }

    // Can be called to restore button state (e.g., on AJAX error)
    stopLoading(button = null) {
        const targetButton = button || this.element;

        if (targetButton.dataset.originalContent) {
            targetButton.innerHTML = targetButton.dataset.originalContent;
            targetButton.disabled = false;
            targetButton.classList.remove('btn-loading');
            targetButton.style.minWidth = '';
            delete targetButton.dataset.originalContent;
            delete targetButton.dataset.originalWidth;
        }
    }

    // Reset all buttons in form
    reset() {
        if (this.element.tagName === 'FORM') {
            const buttons = this.element.querySelectorAll('.btn-loading');
            buttons.forEach(button => this.stopLoading(button));
        } else {
            this.stopLoading();
        }
    }
}
