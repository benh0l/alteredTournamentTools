import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for log filtering with AJAX (FR69).
 *
 * Usage:
 * <div data-controller="logs-filter"
 *      data-logs-filter-url-value="/admin/logs">
 *     <select data-logs-filter-target="level" data-action="change->logs-filter#filter">
 *     <input data-logs-filter-target="keyword" data-action="input->logs-filter#debounceFilter">
 *     <div data-logs-filter-target="entriesContainer"></div>
 * </div>
 */
export default class extends Controller {
    static targets = [
        'level',
        'startDate',
        'endDate',
        'keyword',
        'entriesContainer',
        'loading',
        'totalCount',
        'modal',
        'contextContent'
    ];

    static values = {
        url: String
    };

    connect() {
        this.debounceTimer = null;
    }

    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
    }

    /**
     * Immediate filter (for select changes).
     */
    filter() {
        this.loadLogs(1);
    }

    /**
     * Debounced filter (for text input).
     */
    debounceFilter() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        this.debounceTimer = setTimeout(() => {
            this.loadLogs(1);
        }, 300);
    }

    /**
     * Handle pagination click.
     */
    paginate(event) {
        event.preventDefault();
        const page = event.currentTarget.dataset.page;
        this.loadLogs(parseInt(page, 10));
    }

    /**
     * Reset all filters.
     */
    reset() {
        if (this.hasLevelTarget) {
            this.levelTarget.value = '';
        }
        if (this.hasStartDateTarget) {
            this.startDateTarget.value = '';
        }
        if (this.hasEndDateTarget) {
            this.endDateTarget.value = '';
        }
        if (this.hasKeywordTarget) {
            this.keywordTarget.value = '';
        }

        this.loadLogs(1);
    }

    /**
     * Load logs via AJAX.
     */
    async loadLogs(page = 1) {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove('hidden');
        }

        const params = new URLSearchParams();

        if (this.hasLevelTarget && this.levelTarget.value) {
            params.set('level', this.levelTarget.value);
        }
        if (this.hasStartDateTarget && this.startDateTarget.value) {
            params.set('start_date', this.startDateTarget.value);
        }
        if (this.hasEndDateTarget && this.endDateTarget.value) {
            params.set('end_date', this.endDateTarget.value);
        }
        if (this.hasKeywordTarget && this.keywordTarget.value) {
            params.set('keyword', this.keywordTarget.value);
        }
        params.set('page', page.toString());

        // Update URL for bookmarking
        const newUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState({}, '', newUrl);

        try {
            const response = await fetch(`${this.urlValue}?${params.toString()}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const html = await response.text();
                if (this.hasEntriesContainerTarget) {
                    this.entriesContainerTarget.innerHTML = html;
                }
            } else {
                console.error('Failed to load logs:', response.status);
            }
        } catch (error) {
            console.error('Error loading logs:', error);
        } finally {
            if (this.hasLoadingTarget) {
                this.loadingTarget.classList.add('hidden');
            }
        }
    }

    /**
     * Toggle context modal.
     */
    toggleContext(event) {
        const context = event.currentTarget.dataset.context;

        try {
            const parsed = JSON.parse(context);
            const formatted = JSON.stringify(parsed, null, 2);

            if (this.hasContextContentTarget) {
                this.contextContentTarget.querySelector('pre').textContent = formatted;
            }

            if (this.hasModalTarget) {
                this.modalTarget.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Failed to parse context:', error);
        }
    }

    /**
     * Close context modal.
     */
    closeModal() {
        if (this.hasModalTarget) {
            this.modalTarget.classList.add('hidden');
        }
    }
}
