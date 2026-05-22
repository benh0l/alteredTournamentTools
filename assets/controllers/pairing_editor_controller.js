import { Controller } from '@hotwired/stimulus';

/**
 * Drag & drop controller for editing tournament pairings.
 *
 * Allows organizers to:
 * - Swap players between tables by dragging
 * - Assign manual BYEs by dropping on BYE zone
 *
 * All changes are stored locally and only saved when clicking "Save".
 */
export default class extends Controller {
    static targets = ['matchesContainer', 'byeZone', 'editButton', 'saveButton', 'cancelButton', 'playerCard', 'rematchModal', 'rematchMessage', 'startRoundForm', 'byeSlot'];

    static values = {
        tournamentId: Number,
        roundNumber: Number,
    };

    // State
    editMode = false;
    pendingSwaps = [];
    pendingByes = [];
    pendingFillByes = []; // { playerId, matchId, player1Id }
    originalState = null;
    draggedPlayer = null;
    pendingRematchSwap = null;
    selectedPlayer = null;      // Mobile: joueur sélectionné pour échange
    isTouchDevice = false;      // Détection appareil tactile

    connect() {
        // Détection appareil tactile
        this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

        // Bind handlers une seule fois pour éviter les problèmes de référence
        this._boundHandleTapSelect = this.handleTapSelect.bind(this);
        this._boundHandleByeZoneTap = this.handleByeZoneTap.bind(this);
        this._boundHandleByeSlotTap = this.handleByeSlotTap.bind(this);

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
        this.pendingByes = [];
        this.pendingFillByes = [];

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

        // Make player cards interactive
        this.playerCardTargets.forEach(card => {
            if (this.isTouchDevice) {
                // Mode mobile: tap-to-select
                card.classList.add('cursor-pointer', 'hover:ring-2', 'hover:ring-blue-400', 'select-none');
                card.addEventListener('click', this._boundHandleTapSelect);
            } else {
                // Mode desktop: drag & drop
                card.setAttribute('draggable', 'true');
                card.classList.add('cursor-move', 'hover:ring-2', 'hover:ring-blue-400');
                card.addEventListener('dragstart', this.handleDragStart.bind(this));
                card.addEventListener('dragend', this.handleDragEnd.bind(this));
                card.addEventListener('dragover', this.handleDragOver.bind(this));
                card.addEventListener('dragleave', this.handleDragLeave.bind(this));
                card.addEventListener('drop', this.handleDrop.bind(this));
            }
        });

        // BYE zone events
        if (this.hasByeZoneTarget) {
            if (this.isTouchDevice) {
                this.byeZoneTarget.addEventListener('click', this._boundHandleByeZoneTap);
            } else {
                this.byeZoneTarget.addEventListener('dragover', this.handleByeZoneDragOver.bind(this));
                this.byeZoneTarget.addEventListener('dragleave', this.handleByeZoneDragLeave.bind(this));
                this.byeZoneTarget.addEventListener('drop', this.handleByeZoneDrop.bind(this));
            }
        }

        // BYE slot events (existing BYE matches that can receive a player)
        this.byeSlotTargets.forEach(slot => {
            if (this.isTouchDevice) {
                slot.addEventListener('click', this._boundHandleByeSlotTap);
            } else {
                slot.addEventListener('dragover', this.handleByeSlotDragOver.bind(this));
                slot.addEventListener('dragleave', this.handleByeSlotDragLeave.bind(this));
                slot.addEventListener('drop', this.handleByeSlotDrop.bind(this));
            }
        });
    }

    disableEditMode() {
        this.editMode = false;

        // Show/hide buttons
        if (this.hasEditButtonTarget) {
            this.editButtonTarget.classList.remove('hidden');
        }
        if (this.hasSaveButtonTarget) {
            this.saveButtonTarget.classList.add('hidden');
        }
        if (this.hasCancelButtonTarget) {
            this.cancelButtonTarget.classList.add('hidden');
        }

        // Hide BYE zone
        if (this.hasByeZoneTarget) {
            this.byeZoneTarget.classList.add('hidden');
        }

        // Show start round button
        if (this.hasStartRoundFormTarget) {
            this.startRoundFormTarget.classList.remove('hidden');
        }

        // Remove interactive state from cards
        this.playerCardTargets.forEach(card => {
            card.removeAttribute('draggable');
            card.classList.remove('cursor-move', 'cursor-pointer', 'hover:ring-2', 'hover:ring-blue-400', 'select-none');
            // Remove mobile tap listener
            card.removeEventListener('click', this._boundHandleTapSelect);
        });

        // Remove BYE zone mobile listener
        if (this.hasByeZoneTarget) {
            this.byeZoneTarget.removeEventListener('click', this._boundHandleByeZoneTap);
        }

        // Remove BYE slot mobile listeners
        this.byeSlotTargets.forEach(slot => {
            slot.removeEventListener('click', this._boundHandleByeSlotTap);
        });

        // Clear mobile selection
        this.clearSelection();
    }

    cancelChanges() {
        // Restore original state
        if (this.hasMatchesContainerTarget) {
            this.matchesContainerTarget.innerHTML = this.originalState;
        }
        this.pendingSwaps = [];
        this.pendingByes = [];
        this.pendingFillByes = [];
        this.clearSelection();
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

        // Check for rematch before adding to pending (still need server-side check)
        const rematchInfo = await this.checkRematch(player1Id, player2Id);

        if (rematchInfo) {
            // Show rematch warning
            this.pendingRematchSwap = { player1Id, player2Id };
            this.showRematchWarning(rematchInfo);
            return;
        }

        // Add to pending swaps and update UI
        this.addPendingSwap(player1Id, player2Id);
    }

    addPendingSwap(player1Id, player2Id) {
        // Check if this swap already exists or reverses an existing swap
        const existingIndex = this.pendingSwaps.findIndex(
            s => (s.player1Id === player1Id && s.player2Id === player2Id) ||
                 (s.player1Id === player2Id && s.player2Id === player1Id)
        );

        if (existingIndex !== -1) {
            // Remove existing swap (they cancel out)
            this.pendingSwaps.splice(existingIndex, 1);
        } else {
            // Add new swap
            this.pendingSwaps.push({ player1Id, player2Id });
        }

        // Update UI
        this.swapPlayerCardsInUI(player1Id, player2Id);
        this.showSuccess('Échange préparé (non enregistré)');
    }

    handleByeZoneDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        this.byeZoneTarget.classList.add('ring-2', 'ring-amber-400', 'bg-amber-100/20');
    }

    handleByeZoneDragLeave(event) {
        this.byeZoneTarget.classList.remove('ring-2', 'ring-amber-400', 'bg-amber-100/20');
    }

    handleByeZoneDrop(event) {
        event.preventDefault();
        this.byeZoneTarget.classList.remove('ring-2', 'ring-amber-400', 'bg-amber-100/20');

        const playerId = parseInt(this.draggedPlayer.dataset.playerId);

        // Add to pending BYEs (don't send to server yet)
        this.addPendingBye(playerId);
    }

    handleByeSlotDragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        event.currentTarget.style.backgroundColor = 'rgba(34, 197, 94, 0.3)';
        event.currentTarget.style.border = '2px dashed #22c55e';
    }

    handleByeSlotDragLeave(event) {
        event.currentTarget.style.backgroundColor = '';
        event.currentTarget.style.border = '';
    }

    handleByeSlotDrop(event) {
        event.preventDefault();
        const slot = event.currentTarget;
        slot.style.backgroundColor = '';
        slot.style.border = '';

        const playerId = parseInt(this.draggedPlayer.dataset.playerId);
        const matchId = parseInt(slot.dataset.matchId);
        const player1Id = parseInt(slot.dataset.player1Id);

        // Don't allow dropping on own match
        if (playerId === player1Id) {
            this.showError('Le joueur est déjà dans ce match');
            return;
        }

        this.addPendingFillBye(playerId, matchId, player1Id);
    }

    addPendingFillBye(playerId, matchId, player1Id) {
        // Check if already pending
        const existing = this.pendingFillByes.find(f => f.matchId === matchId);
        if (existing) {
            this.showError('Ce match BYE a déjà un joueur en attente');
            return;
        }

        this.pendingFillByes.push({ playerId, matchId, player1Id });

        // Visual feedback - update the BYE slot to show the player name
        const slots = document.querySelectorAll(`[data-pairing-editor-target="byeSlot"][data-match-id="${matchId}"]`);
        const playerCards = document.querySelectorAll(`[data-pairing-editor-target="playerCard"][data-player-id="${playerId}"]`);

        // Get player name from the dragged card
        const playerName = this.draggedPlayer.textContent.trim().split('\n')[0].trim();

        slots.forEach(slot => {
            slot.style.backgroundColor = 'rgba(34, 197, 94, 0.3)';
            slot.style.border = '2px solid #22c55e';
            slot.innerHTML = `<span class="font-medium">${playerName}</span> <span class="text-xs bg-green-500 text-white px-1 rounded ml-1">NEW</span>`;
        });

        // Mark original player as "moving"
        playerCards.forEach(card => {
            card.style.opacity = '0.5';
            card.style.textDecoration = 'line-through';
        });

        this.showSuccess('Joueur ajouté au match (non enregistré)');
    }

    addPendingBye(playerId) {
        // Check if already pending - allow removing by dropping again
        const existingIndex = this.pendingByes.indexOf(playerId);
        if (existingIndex !== -1) {
            // Remove from pending BYEs
            this.pendingByes.splice(existingIndex, 1);
            this.removePendingByeVisual(playerId);
            this.showSuccess('BYE annulé');
            return;
        }

        this.pendingByes.push(playerId);

        // Visual feedback on the player card - find all cards with this player ID
        const allCards = document.querySelectorAll(`[data-pairing-editor-target="playerCard"][data-player-id="${playerId}"]`);

        allCards.forEach(card => {
            // Add highlight styles
            card.style.backgroundColor = 'rgba(245, 158, 11, 0.3)';
            card.style.border = '2px solid #f59e0b';
            card.style.borderRadius = '8px';

            // Add BYE badge
            if (!card.querySelector('.bye-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'bye-indicator';
                indicator.style.cssText = 'margin-left: 8px; font-size: 10px; background: #f59e0b; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold;';
                indicator.textContent = 'BYE';
                card.appendChild(indicator);
            }

            // Ensure card stays draggable
            card.setAttribute('draggable', 'true');
        });

        this.showSuccess('BYE préparé (non enregistré)');
    }

    removePendingByeVisual(playerId) {
        const allCards = document.querySelectorAll(`[data-pairing-editor-target="playerCard"][data-player-id="${playerId}"]`);

        allCards.forEach(card => {
            // Remove highlight styles
            card.style.backgroundColor = '';
            card.style.border = '';
            card.style.borderRadius = '';

            // Remove BYE badge
            const indicator = card.querySelector('.bye-indicator');
            if (indicator) {
                indicator.remove();
            }
        });
    }

    async checkRematch(player1Id, player2Id) {
        try {
            const response = await fetch(`/tournaments/${this.tournamentIdValue}/rounds/${this.roundNumberValue}/check-rematch`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ player1Id, player2Id }),
            });

            if (!response.ok) {
                return null;
            }

            const data = await response.json();
            return data.rematchDetected ? data.rematchDetails : null;
        } catch (error) {
            console.error('Check rematch error:', error);
            return null;
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
            const message = `Attention: cet échange crée un rematch entre ${details.player1} et ${details.player2} (déjà joué en ronde ${details.previousRound}). Continuer quand même?`;
            this.rematchMessageTarget.textContent = message;
            this.rematchModalTarget.classList.remove('hidden');
        } else {
            // Fallback to confirm dialog
            const confirmed = confirm(`Attention: cet échange crée un rematch entre ${details.player1} et ${details.player2} (déjà joué en ronde ${details.previousRound}). Continuer quand même?`);
            if (confirmed && this.pendingRematchSwap) {
                this.addPendingSwap(this.pendingRematchSwap.player1Id, this.pendingRematchSwap.player2Id);
            }
            this.pendingRematchSwap = null;
        }
    }

    confirmRematch() {
        if (this.pendingRematchSwap) {
            this.addPendingSwap(this.pendingRematchSwap.player1Id, this.pendingRematchSwap.player2Id);
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
        // Check if there are any pending changes
        if (this.pendingSwaps.length === 0 && this.pendingByes.length === 0 && this.pendingFillByes.length === 0) {
            this.showSuccess('Aucune modification à enregistrer');
            this.disableEditMode();
            return;
        }

        try {
            // Send all pending changes to the server
            const response = await fetch(`/tournaments/${this.tournamentIdValue}/rounds/${this.roundNumberValue}/apply-changes`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    swaps: this.pendingSwaps,
                    byes: this.pendingByes,
                    fillByes: this.pendingFillByes,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                this.showError(data.message || 'Erreur lors de l\'enregistrement');
                return;
            }

            // Success - update original state and exit edit mode
            this.originalState = this.matchesContainerTarget.innerHTML;
            this.pendingSwaps = [];
            this.pendingByes = [];
            this.disableEditMode();
            this.showSuccess('Modifications enregistrées');

            // Reload page to get fresh data
            window.location.reload();
        } catch (error) {
            console.error('Save error:', error);
            this.showError('Erreur de connexion');
        }
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

    // ============================================
    // MOBILE TAP-TO-SELECT HANDLERS
    // ============================================

    handleTapSelect(event) {
        event.preventDefault();
        event.stopPropagation();

        const card = event.target.closest('[data-pairing-editor-target="playerCard"]');
        if (!card) return;

        const playerId = parseInt(card.dataset.playerId);

        // Si aucun joueur sélectionné → sélectionner celui-ci
        if (!this.selectedPlayer) {
            this.selectPlayer(card, playerId);
            return;
        }

        // Si on tape sur le même joueur → désélectionner
        if (this.selectedPlayer.id === playerId) {
            this.clearSelection();
            this.showToast('Sélection annulée', 'info');
            return;
        }

        // Sinon → effectuer l'échange
        this.performTapSwap(playerId);
    }

    selectPlayer(card, playerId) {
        this.selectedPlayer = { id: playerId, card: card };

        // Highlight visuel sur toutes les cartes du joueur
        this.playerCardTargets.forEach(c => {
            if (parseInt(c.dataset.playerId) === playerId) {
                c.classList.add('ring-4', 'ring-blue-500', 'bg-blue-100/30', 'scale-105');
                c.style.transition = 'transform 0.15s ease';
            }
        });

        this.showToast('Tapez un autre joueur pour échanger', 'info');
    }

    clearSelection() {
        if (this.selectedPlayer) {
            // Retirer le highlight de toutes les cartes du joueur sélectionné
            this.playerCardTargets.forEach(c => {
                if (parseInt(c.dataset.playerId) === this.selectedPlayer.id) {
                    c.classList.remove('ring-4', 'ring-blue-500', 'bg-blue-100/30', 'scale-105');
                    c.style.transition = '';
                }
            });
        }
        this.selectedPlayer = null;
    }

    async performTapSwap(targetPlayerId) {
        const player1Id = this.selectedPlayer.id;
        const player2Id = targetPlayerId;

        // Clear selection first
        this.clearSelection();

        // Check for rematch
        const rematchInfo = await this.checkRematch(player1Id, player2Id);

        if (rematchInfo) {
            this.pendingRematchSwap = { player1Id, player2Id };
            this.showRematchWarning(rematchInfo);
            return;
        }

        this.addPendingSwap(player1Id, player2Id);
    }

    handleByeZoneTap(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!this.selectedPlayer) {
            this.showToast('Sélectionnez d\'abord un joueur', 'info');
            return;
        }

        const playerId = this.selectedPlayer.id;
        this.clearSelection();
        this.addPendingBye(playerId);
    }

    handleByeSlotTap(event) {
        event.preventDefault();
        event.stopPropagation();

        if (!this.selectedPlayer) {
            this.showToast('Sélectionnez d\'abord un joueur', 'info');
            return;
        }

        const slot = event.currentTarget;
        const playerId = this.selectedPlayer.id;
        const matchId = parseInt(slot.dataset.matchId);
        const player1Id = parseInt(slot.dataset.player1Id);

        if (playerId === player1Id) {
            this.showError('Le joueur est déjà dans ce match');
            this.clearSelection();
            return;
        }

        // Pour le fill BYE, on a besoin de stocker le nom du joueur
        const playerCard = this.playerCardTargets.find(c => parseInt(c.dataset.playerId) === playerId);
        this.draggedPlayer = playerCard; // Temporaire pour addPendingFillBye

        this.clearSelection();
        this.addPendingFillBye(playerId, matchId, player1Id);

        this.draggedPlayer = null;
    }
}
