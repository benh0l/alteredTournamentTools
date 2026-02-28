import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for Tournament creation/edit form.
 *
 * Handles dynamic field visibility based on tournament structure selection:
 * - Shows/hides top cut size based on structure (MIXED or SINGLE_ELIMINATION)
 * - Shows/hides elimination match format based on structure
 * - Shows/hides Swiss rounds based on structure
 * - Suggests Swiss rounds based on expected players and match format
 * - Handles group stage configuration for GROUP_STAGE_ELIMINATION
 *
 * Usage:
 * <div data-controller="tournament-form">
 *     <select data-tournament-form-target="structure" data-action="change->tournament-form#structureChanged">
 *     <div data-tournament-form-target="topCutSizeContainer">
 *     <div data-tournament-form-target="swissRoundsContainer">
 *     <div data-tournament-form-target="eliminationFormatContainer">
 *     <!-- Group stage targets -->
 *     <div data-tournament-form-target="groupStageSection">
 *     <div data-tournament-form-target="standardPlayersSection">
 * </div>
 */
export default class extends Controller {
    static targets = [
        'structure',
        'topCutSizeContainer',
        'swissRoundsContainer',
        'eliminationFormatContainer',
        'expectedPlayers',
        'swissRounds',
        'swissMatchFormat',
        'roundsSuggestion',
        // Group stage targets
        'groupStageSection',
        'standardPlayersSection',
        'groupCount',
        'playersPerGroup',
        'qualifiersPerGroup',
        'groupSummary',
        'totalPlayersDisplay',
        'roundsPerGroupDisplay',
        'totalQualifiersDisplay',
        'bracketSizeDisplay',
        'swissRoundsWrapper',
        'topCutWrapper',
        // Round-robin targets
        'roundRobinInfo',
        'roundRobinRoundsDisplay'
    ];

    static values = {
        structure: String,
        hasGroupStage: String,
        hasSwiss: String,
        hasRoundRobin: String,
        hasElimination: String,
        matchFormat: { type: String, default: 'bo3' },
        errorCalculation: { type: String, default: 'Calculation error' },
        bracketLabel: { type: String, default: 'Bracket of %players% players' },
        bracketByesLabel: { type: String, default: 'Bracket of %bracket% (%byes% BYEs needed)' },
        roundsRecommended: { type: String, default: '%rounds% rounds recommended for %players% players' },
        roundRobinRounds: { type: String, default: '%count% rounds will be played' }
    };

    connect() {
        this.updateFieldVisibility();
        // Initialize group summary if group stage
        if (this.hasGroupStageValue === 'true') {
            this.updateGroupSummary();
        }
        // Initialize round-robin display
        if (this.hasRoundRobinValue === 'true') {
            this.updateRoundRobinRounds();
        }
    }

    structureChanged() {
        this.updateFieldVisibility();
    }

    async suggestRounds() {
        // Update round-robin display if applicable
        if (this.hasRoundRobinValue === 'true') {
            this.updateRoundRobinRounds();
        }

        if (!this.hasExpectedPlayersTarget || !this.hasSwissRoundsTarget) {
            return;
        }

        const expectedPlayers = parseInt(this.expectedPlayersTarget.value, 10);
        if (isNaN(expectedPlayers) || expectedPlayers < 4) {
            this.updateSuggestion('');
            return;
        }

        // Get match format (BO1 or BO3) - from value or from form element
        const format = this.hasSwissMatchFormatTarget
            ? this.swissMatchFormatTarget.value.toUpperCase()
            : this.matchFormatValue.toUpperCase();

        try {
            const response = await fetch(
                `/tournaments/api/swiss-rounds?playerCount=${expectedPlayers}&format=${format}`
            );

            if (!response.ok) {
                const error = await response.json();
                this.updateSuggestion(error.error || this.errorCalculationValue);
                return;
            }

            const data = await response.json();

            // Display explanation from API
            this.updateSuggestion(data.explanation);

            // Auto-fill if empty
            if (!this.swissRoundsTarget.value) {
                this.swissRoundsTarget.value = data.suggestedRounds;
            }
        } catch (error) {
            // Fallback to local calculation if API fails
            this.suggestRoundsLocally(expectedPlayers, format);
        }
    }

    suggestRoundsLocally(expectedPlayers, format) {
        let suggestedRounds = Math.ceil(Math.log2(expectedPlayers));

        if (format === 'BO1') {
            suggestedRounds += 1;
        }

        suggestedRounds = Math.max(2, Math.min(suggestedRounds, 10));

        const explanation = this.roundsRecommendedValue
            .replace('%rounds%', suggestedRounds)
            .replace('%players%', expectedPlayers);

        this.updateSuggestion(explanation);

        if (!this.swissRoundsTarget.value) {
            this.swissRoundsTarget.value = suggestedRounds;
        }
    }

    updateFieldVisibility() {
        if (!this.hasStructureTarget) {
            return;
        }

        const structure = this.structureTarget.value;

        // Determine what to show based on structure
        const isGroupStage = structure === 'group_stage_elimination';
        const isRoundRobin = structure === 'round_robin';
        const hasElimination = structure === 'mixed' || structure === 'single_elimination' || isGroupStage;
        const hasSwiss = structure === 'swiss_only' || structure === 'mixed';

        // Group Stage Section: show only for GROUP_STAGE_ELIMINATION
        const groupSection = this.element.querySelector('[data-tournament-form-target="groupStageSection"]');
        if (groupSection) {
            this.toggleContainer(groupSection, isGroupStage);
        }

        // Standard Players Section: hide for GROUP_STAGE_ELIMINATION
        const standardSection = this.element.querySelector('[data-tournament-form-target="standardPlayersSection"]');
        if (standardSection) {
            this.toggleContainer(standardSection, !isGroupStage);
        }

        // Top Cut Size: show only for MIXED or SINGLE_ELIMINATION (not group stage, not round-robin)
        this.toggleContainer(this.topCutSizeContainerTarget, hasElimination && !isGroupStage);

        // Elimination Match Format: show only if elimination phase exists
        this.toggleContainer(this.eliminationFormatContainerTarget, hasElimination);

        // Swiss Rounds: hide for SINGLE_ELIMINATION, GROUP_STAGE_ELIMINATION, and ROUND_ROBIN
        this.toggleContainer(this.swissRoundsContainerTarget, hasSwiss);

        // Round-robin info: show only for ROUND_ROBIN
        const roundRobinInfo = this.element.querySelector('[data-tournament-form-target="roundRobinInfo"]');
        if (roundRobinInfo) {
            this.toggleContainer(roundRobinInfo, isRoundRobin);
        }

        // Swiss rounds wrapper in wizard: hide for ROUND_ROBIN
        const swissRoundsWrapper = this.element.querySelector('[data-tournament-form-target="swissRoundsWrapper"]');
        if (swissRoundsWrapper) {
            this.toggleContainer(swissRoundsWrapper, hasSwiss && !isRoundRobin);
        }

        // Initialize group summary when switching to group stage
        if (isGroupStage) {
            this.updateGroupSummary();
        }

        // Update round-robin display
        if (isRoundRobin) {
            this.updateRoundRobinRounds();
        }
    }

    /**
     * Update the round-robin rounds display based on expected players.
     */
    updateRoundRobinRounds() {
        if (!this.hasExpectedPlayersTarget || !this.hasRoundRobinRoundsDisplayTarget) {
            return;
        }

        const expectedPlayers = parseInt(this.expectedPlayersTarget.value, 10);
        if (isNaN(expectedPlayers) || expectedPlayers < 2) {
            this.roundRobinRoundsDisplayTarget.textContent = '';
            return;
        }

        const rounds = expectedPlayers - 1;
        const text = this.roundRobinRoundsValue.replace('%count%', rounds);
        this.roundRobinRoundsDisplayTarget.textContent = text;
    }

    toggleContainer(container, show) {
        if (!container) {
            return;
        }

        if (show) {
            container.classList.remove('hidden');
            container.style.display = '';
        } else {
            container.classList.add('hidden');
            container.style.display = 'none';
        }
    }

    updateSuggestion(text) {
        if (this.hasRoundsSuggestionTarget) {
            this.roundsSuggestionTarget.textContent = text;
        }
    }

    /**
     * Update the group stage summary display.
     * Called when group count, players per group, or qualifiers per group changes.
     */
    updateGroupSummary() {
        if (!this.hasGroupCountTarget || !this.hasPlayersPerGroupTarget || !this.hasQualifiersPerGroupTarget) {
            return;
        }

        const groupCount = parseInt(this.groupCountTarget.value, 10) || 0;
        const playersPerGroup = parseInt(this.playersPerGroupTarget.value, 10) || 0;
        const qualifiersPerGroup = parseInt(this.qualifiersPerGroupTarget.value, 10) || 0;

        // Calculate values
        const totalPlayers = groupCount * playersPerGroup;
        const roundsPerGroup = playersPerGroup > 0 ? playersPerGroup - 1 : 0;
        const totalQualifiers = groupCount * qualifiersPerGroup;

        // Update displays
        if (this.hasTotalPlayersDisplayTarget) {
            const span = this.totalPlayersDisplayTarget.querySelector('span');
            if (span) {
                span.textContent = totalPlayers > 0 ? totalPlayers : '-';
            }
        }

        if (this.hasRoundsPerGroupDisplayTarget) {
            const span = this.roundsPerGroupDisplayTarget.querySelector('span');
            if (span) {
                span.textContent = roundsPerGroup > 0 ? roundsPerGroup : '-';
            }
        }

        if (this.hasTotalQualifiersDisplayTarget) {
            const span = this.totalQualifiersDisplayTarget.querySelector('span');
            if (span) {
                span.textContent = totalQualifiers > 0 ? totalQualifiers : '-';
            }
        }

        // Check if total qualifiers is a power of 2 (required for clean bracket)
        if (this.hasBracketSizeDisplayTarget) {
            if (totalQualifiers > 0) {
                const isPowerOfTwo = (totalQualifiers & (totalQualifiers - 1)) === 0;
                if (isPowerOfTwo) {
                    this.bracketSizeDisplayTarget.textContent = this.bracketLabelValue.replace('%players%', totalQualifiers);
                    this.bracketSizeDisplayTarget.classList.remove('text-amber-600');
                    this.bracketSizeDisplayTarget.classList.add('text-green-700');
                } else {
                    // Find next power of 2
                    const nextPower = Math.pow(2, Math.ceil(Math.log2(totalQualifiers)));
                    const byesNeeded = nextPower - totalQualifiers;
                    this.bracketSizeDisplayTarget.textContent = this.bracketByesLabelValue
                        .replace('%bracket%', nextPower)
                        .replace('%byes%', byesNeeded);
                    this.bracketSizeDisplayTarget.classList.remove('text-green-700');
                    this.bracketSizeDisplayTarget.classList.add('text-amber-600');
                }
            } else {
                this.bracketSizeDisplayTarget.textContent = '';
            }
        }

        // Also set expectedPlayers hidden field for validation
        if (this.hasExpectedPlayersTarget && totalPlayers > 0) {
            this.expectedPlayersTarget.value = totalPlayers;
        }
    }

    // Getter helpers for optional targets
    get topCutSizeContainerTarget() {
        return this.hasTopCutSizeContainerTarget ? this.targets.find('topCutSizeContainer') : null;
    }

    get eliminationFormatContainerTarget() {
        return this.hasEliminationFormatContainerTarget ? this.targets.find('eliminationFormatContainer') : null;
    }

    get swissRoundsContainerTarget() {
        return this.hasSwissRoundsContainerTarget ? this.targets.find('swissRoundsContainer') : null;
    }
}
