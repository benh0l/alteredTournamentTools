import { Controller } from '@hotwired/stimulus';

/**
 * Wizard controller for handling step navigation and card selection.
 */
export default class extends Controller {
    static targets = ['form', 'submitButton'];

    connect() {
        // Auto-focus first input
        const firstInput = this.element.querySelector('input:not([type="hidden"]):not([type="radio"]), select, textarea');
        if (firstInput) {
            firstInput.focus();
        }

        // Handle Enter key to proceed
        this.element.addEventListener('keydown', this.handleKeydown.bind(this));
    }

    handleKeydown(event) {
        // Only proceed on Enter if not in a textarea
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
            event.preventDefault();
            this.submitForm();
        }
    }

    selectCard(event) {
        const card = event.currentTarget;
        const input = card.querySelector('input[type="radio"]');

        if (input) {
            input.checked = true;

            // Update visual state for all cards in the same group
            const name = input.name;
            const allCards = this.element.querySelectorAll(`input[name="${name}"]`);

            allCards.forEach(radio => {
                const parentLabel = radio.closest('label');
                if (parentLabel) {
                    const cardDiv = parentLabel.querySelector('div');
                    if (cardDiv) {
                        if (radio.checked) {
                            cardDiv.classList.remove('border-gray-200');
                            cardDiv.classList.add('border-blue-600', 'bg-blue-50');
                        } else {
                            cardDiv.classList.remove('border-blue-600', 'bg-blue-50');
                            cardDiv.classList.add('border-gray-200');
                        }
                    }
                }
            });
        }
    }

    submitForm() {
        const form = this.element.querySelector('#wizard-form');
        if (form) {
            form.submit();
        }
    }
}
