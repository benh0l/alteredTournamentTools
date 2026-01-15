import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for admin match result override (FR72).
 *
 * Handles the modal for overriding match results.
 */
export default class extends Controller {
    static targets = [
        'modal',
        'form',
        'matchId',
        'tableNumber',
        'winner',
        'player1Option',
        'player2Option',
        'reason'
    ];

    static values = {
        csrf: String,
        errorConnection: { type: String, default: 'Connection error.' },
        errorGeneric: { type: String, default: 'An error occurred.' },
        errorValidation: { type: String, default: 'Please select a winner and provide a reason.' },
        buttonConfirm: { type: String, default: 'Confirm' },
        buttonSaving: { type: String, default: 'Saving...' },
        player1Label: { type: String, default: 'Player 1' },
        player2Label: { type: String, default: 'Player 2' }
    };

    openModal(event) {
        const button = event.currentTarget;
        const matchId = button.dataset.matchId;
        const player1Name = button.dataset.player1Name;
        const player2Name = button.dataset.player2Name;
        const tableNumber = button.dataset.tableNumber;

        // Populate modal
        this.matchIdTarget.value = matchId;
        this.tableNumberTarget.textContent = tableNumber;
        this.player1OptionTarget.textContent = player1Name || this.player1LabelValue;
        this.player2OptionTarget.textContent = player2Name || this.player2LabelValue;

        // Reset form
        this.winnerTarget.value = '';
        this.reasonTarget.value = '';

        // Show modal
        this.modalTarget.classList.remove('hidden');
    }

    closeModal() {
        this.modalTarget.classList.add('hidden');
    }

    async submit(event) {
        event.preventDefault();

        const matchId = this.matchIdTarget.value;
        const winner = this.winnerTarget.value;
        const reason = this.reasonTarget.value;

        if (!winner || !reason) {
            alert(this.errorValidationValue);
            return;
        }

        const submitButton = event.currentTarget;
        submitButton.disabled = true;
        submitButton.textContent = this.buttonSavingValue;

        try {
            const formData = new FormData();
            formData.append('winner', winner);
            formData.append('reason', reason);
            formData.append('_token', this.csrfValue);

            const response = await fetch(`/admin/tournament/match/${matchId}/override`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Close modal and refresh page
                this.closeModal();
                window.location.reload();
            } else {
                alert(data.error || this.errorGenericValue);
            }
        } catch (error) {
            console.error('Override error:', error);
            alert(this.errorConnectionValue);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = this.buttonConfirmValue;
        }
    }
}
