import { Controller } from '@hotwired/stimulus';

/**
 * Drag & drop controller for editing tournament pairings.
 *
 * Allows organizers to:
 * - Swap players between tables by dragging
 * - Assign manual BYEs by dropping on BYE zone
 *
 * Usage:
 * <div data-controller="pairing-editor"
 *      data-pairing-editor-tournament-id-value="123"
 *      data-pairing-editor-round-number-value="1">
 *     <div data-pairing-editor-target="matchesContainer">
 *         <!-- Match cards with draggable players -->
 *     </div>
 *     <div data-pairing-editor-target="byeZone">BYE Zone</div>
 *     <button data-pairing-editor-target="editButton" data-action="click->pairing-editor#enableEditMode">Edit</button>
 *     <button data-pairing-editor-target="saveButton" data-action="click->pairing-editor#saveChanges" class="hidden">Save</button>
 *     <button data-pairing-editor-target="cancelButton" data-action="click->pairing-editor#cancelChanges" class="hidden">Cancel</button>
 * </div>
 */
export default class extends Controller {
    static targets = ['matchesContainer', 'byeZone', 'editButton', 'saveButton', 'cancelButton', 'playerCard', 'rematchModal', 'rematchMessage', 'startRoundForm'];

    static values = {
        tournamentId: Number,
        roundNumber: Number,
    };

    // State
    editMode = false;
    pendingSwaps = [];
    originalState = null;
    draggedPlayer = null;
    pendingRematchSwap = null;

    connect() {
        // Store original HTML for cancel
        if (this.hasMatchesContainerTarget) {
            this.originalState = this.matchesContainerTarget.innerHTML;
        }
    }

    disconnect() {
        this.disableEditMode();
    }

    enableEditMode() {
        this.editMode = true;
        this.pendingSwaps = [];

        // Show/hide buttons
        if (this.hasEditButtonTarget) {
            this.editButtonTarget.classList.add('hidden');
        }
        if (this.hasSaveButtonTarget) {
            this.saveButtonTarget.classList.remove('hidden');
        }
        if (this.hasCancelButtonTarget) {
            this.cancelButtonTarget.classList.remove('hidden');
        }

        // Show BYE zone
        if (this.hasByeZoneTarget) {
            this.byeZoneTarget.classList.remove('hidden');
        }

        // Hide start round button
        if (this.hasStartRoundFormTarget) {
            this.startRoundFormTarget.classList.add('hidden');
        }

        // Make player cards draggable
        this.playerCardTargets.forEach(card => {
            card.setAttribute('draggable', 'true');
            card.classList.add('cursor-move', 'hover:ring-2', 'hover:ring-blue-400');

            // Add drag event listeners
            card.addEventListener('dragstart', this.handleDragStart.bind(this));
            card.addEventListener('dragend', this.handleDragEnd.bind(this));
            card.addEventListener('dragover', this.handleDragOver.bind(this));
            card.addEventListener('dragleave', this.handleDragLeave.bind(this));
            card.addEventListener('drop', this.handleDrop.bind(this));
        });

        // BYE zone drop events
        if (this.hasByeZoneTarget) {
            this.byeZoneTarget.addEventListener('dragover', this.handleByeZoneDragOver.bind(this));
            this.byeZoneTarget.addEventListener('dragleave', this.handleByeZoneDragLeave.bind(this));
            this.byeZoneTarget.addEventListener('drop', this.handleByeZoneDrop.bind(this));
        }
    }

    disableEditMode() {
        this.editMode = false;

        // Show/hide buttons
        this.editButtonTarget.classList.remove('hidden');
        this.saveButtonTarget.classList.add('hidden');
        this.cancelButtonTarget.classList.add('hidden');

        // Hide BYE zone
        if (this.hasByeZoneTarget) {
            this.byeZoneTarget.classList.add('hidden');
        }

        // Show start round button
        if (this.hasStartRoundFormTarget) {
            this.startRoundFormTarget.classList.remove('hidden');
        }

        // Remove draggable from cards
        this.playerCardTargets.forEach(card => {
            card.removeAttribute('draggable');
            card.classList.remove('cursor-move', 'hover:ring-2', 'hover:ring-blue-400');
        });
    }

    cancelChanges() {
        // Restore original state
        this.matchesContainerTarget.innerHTML = this.originalState;
        this.pendingSwaps = [];
        this.disableEditMode();

        // Re-connect targets after restoring HTML
        this.connect();
    }

    handleDragStart(event) {
        this.draggedPlayer = event.target;
        event.target.classList.add('opacity-50', 'ring-2', 'ring-blue-500');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', event.target.dataset.playerId);
    }

    handleDragEnd(event) {
        event.target.classList.remove('opacity-50', 'ring-2', 'ring-blue-500');
        this.draggedPlayer = null;

        // Remove all drag highlights
        this.playerCardTargets.forEach(card => {
            card.classList.remove('ring-2', 'ring-green-400', 'bg-green-100/20');
        });
    }

    handleDragOver(event) {
        event.preventDefault();
        const target = event.target.closest('[data-pairing-editor-target="playerCard"]');

        if (target && target !== this.draggedPlayer) {
            event.dataTransfer.dropEffect = 'move';
            target.classList.add('ring-2', 'ring-green-400', 'bg-green-100/20');
        }
    }

    handleDragLeave(event) {
        const target = event.target.closest('[data-pairing-editor-target="playerCard"]');
        if (target) {
            target.classList.remove('ring-2', 'ring-green-400', 'bg-green-100/20');
        }
    }

    async handleDrop(event) {
        event.preventDefault();
        const target = event.target.closest('[data-pairing-editor-target="playerCard"]');

        if (!target || target === this.draggedPlayer) {
            return;
        }

        target.classList.remove('ring-2', 'ring-green-400', 'bg-green-100/20');

        const player1Id = parseInt(this.draggedPlayer.dataset.playerId);
        const player2Id = parseInt(target.dataset.playerId);

        // Perform swap via API
        await this.performSwap(player1Id, player2Id, false);
    }

    handleByeZoneDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        this.byeZoneTarget.classList.add('ring-2', 'ring-amber-400', 'bg-amber-100/20');
    }

    handleByeZoneDragLeave(event) {
        this.byeZoneTarget.classList.remove('ring-2', 'ring-amber-400', 'bg-amber-100/20');
    }

    async handleByeZoneDrop(event) {
        event.preventDefault();
        this.byeZoneTarget.classList.remove('ring-2', 'ring-amber-400', 'bg-amber-100/20');

        const playerId = parseInt(this.draggedPlayer.dataset.playerId);
        await this.assignBye(playerId);
    }

    async performSwap(player1Id, player2Id, forceSwap = false) {
        try {
            const response = await fetch(`/tournaments/${this.tournamentIdValue}/rounds/${this.roundNumberValue}/swap`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    player1Id,
                    player2Id,
                    forceSwap,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                this.showError(data.message || 'Erreur lors de l\'echange');
                return;
            }

            if (data.rematchDetected && !forceSwap) {
                // Show rematch warning modal
                this.pendingRematchSwap = { player1Id, player2Id };
                this.showRematchWarning(data.rematchDetails);
                return;
            }

            if (data.success) {
                // Swap succeeded - update UI
                this.swapPlayerCardsInUI(player1Id, player2Id);
                this.showSuccess('Joueurs echanges avec succes');
            }
        } catch (error) {
            console.error('Swap error:', error);
            this.showError('Erreur de connexion');
        }
    }

    async assignBye(playerId) {
        try {
            const response = await fetch(`/tournaments/${this.tournamentIdValue}/rounds/${this.roundNumberValue}/assign-bye`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ playerId }),
            });

            const data = await response.json();

            if (!response.ok) {
                this.showError(data.message || 'Erreur lors de l\'attribution du BYE');
                return;
            }

            if (data.success) {
                // Reload page to show updated pairings
                window.location.reload();
            }
        } catch (error) {
            console.error('Assign BYE error:', error);
            this.showError('Erreur de connexion');
        }
    }

    swapPlayerCardsInUI(player1Id, player2Id) {
        // Find ALL cards for each player (mobile + desktop views)
        const cards1 = this.playerCardTargets.filter(c => parseInt(c.dataset.playerId) === player1Id);
        const cards2 = this.playerCardTargets.filter(c => parseInt(c.dataset.playerId) === player2Id);

        if (cards1.length === 0 || cards2.length === 0) return;

        // Swap each pair of cards (mobile with mobile, desktop with desktop)
        const maxPairs = Math.min(cards1.length, cards2.length);
        for (let i = 0; i < maxPairs; i++) {
            const card1 = cards1[i];
            const card2 = cards2[i];

            // Swap innerHTML and data-player-id
            const tempHtml = card1.innerHTML;
            const tempPlayerId = card1.dataset.playerId;

            card1.innerHTML = card2.innerHTML;
            card1.dataset.playerId = card2.dataset.playerId;

            card2.innerHTML = tempHtml;
            card2.dataset.playerId = tempPlayerId;

            // Visual feedback
            card1.classList.add('bg-green-100/30');
            card2.classList.add('bg-green-100/30');
            setTimeout(() => {
                card1.classList.remove('bg-green-100/30');
                card2.classList.remove('bg-green-100/30');
            }, 1000);
        }
    }

    showRematchWarning(details) {
        if (this.hasRematchModalTarget) {
            const message = `Attention: cet echange cree un rematch entre ${details.player1} et ${details.player2} (deja joue en ronde ${details.previousRound}). Continuer quand meme?`;
            this.rematchMessageTarget.textContent = message;
            this.rematchModalTarget.classList.remove('hidden');
        } else {
            // Fallback to confirm dialog
            const confirmed = confirm(`Attention: cet echange cree un rematch entre ${details.player1} et ${details.player2} (deja joue en ronde ${details.previousRound}). Continuer quand meme?`);
            if (confirmed && this.pendingRematchSwap) {
                this.performSwap(this.pendingRematchSwap.player1Id, this.pendingRematchSwap.player2Id, true);
            }
            this.pendingRematchSwap = null;
        }
    }

    confirmRematch() {
        if (this.pendingRematchSwap) {
            this.performSwap(this.pendingRematchSwap.player1Id, this.pendingRematchSwap.player2Id, true);
        }
        this.closeRematchModal();
    }

    cancelRematch() {
        this.pendingRematchSwap = null;
        this.closeRematchModal();
    }

    closeRematchModal() {
        if (this.hasRematchModalTarget) {
            this.rematchModalTarget.classList.add('hidden');
        }
        this.pendingRematchSwap = null;
    }

    async saveChanges() {
        // All changes are already saved via API calls
        // Just exit edit mode
        this.originalState = this.matchesContainerTarget.innerHTML;
        this.disableEditMode();
        this.showSuccess('Modifications enregistrees');
    }

    showSuccess(message) {
        // Create toast notification
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg shadow-lg text-white z-50 transition-all duration-300 ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}
