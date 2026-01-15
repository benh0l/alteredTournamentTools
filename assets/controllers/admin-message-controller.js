import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for admin message form (FR71).
 *
 * Toggles the recipient field based on recipient type selection.
 */
export default class extends Controller {
    static targets = ['recipientContainer'];

    connect() {
        // Check initial state
        this.toggleRecipient();
    }

    toggleRecipient() {
        const selectedType = document.querySelector('input[name*="[recipientType]"]:checked');

        if (!selectedType || !this.hasRecipientContainerTarget) {
            return;
        }

        if (selectedType.value === 'all_organizers') {
            this.recipientContainerTarget.classList.add('hidden');
            this.recipientContainerTarget.style.display = 'none';
        } else {
            this.recipientContainerTarget.classList.remove('hidden');
            this.recipientContainerTarget.style.display = 'block';
        }
    }
}
