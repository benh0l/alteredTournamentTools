import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for Mercure SSE subscriptions.
 *
 * Provides real-time updates for tournament dashboard, standings, and timer.
 * Includes automatic reconnection logic and fallback polling strategy (NFR24).
 *
 * Usage:
 * <div data-controller="mercure"
 *      data-mercure-url-value="{{ mercure_public_url }}"
 *      data-mercure-topic-value="/tournament/{{ tournament.id }}">
 *     <div data-mercure-target="status"></div>
 *     <div data-mercure-target="messages"></div>
 * </div>
 */
export default class extends Controller {
    static targets = ['messages', 'status'];
    static values = {
        url: String,
        topic: String,
        reconnectAttempts: { type: Number, default: 5 },
        reconnectDelay: { type: Number, default: 3000 },
        pollingInterval: { type: Number, default: 10000 },
        pollingEndpoint: String
    };

    eventSource = null;
    attempts = 0;
    pollingIntervalId = null;
    reconnectTimeoutId = null;
    isDisconnected = false;

    connect() {
        this.isDisconnected = false;
        this.startConnection();
    }

    disconnect() {
        this.isDisconnected = true;

        // Clear reconnect timeout if pending
        if (this.reconnectTimeoutId) {
            clearTimeout(this.reconnectTimeoutId);
            this.reconnectTimeoutId = null;
        }

        // CRITICAL: Always close EventSource on disconnect (from project-context.md)
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }

        // Stop polling if active
        if (this.pollingIntervalId) {
            clearInterval(this.pollingIntervalId);
            this.pollingIntervalId = null;
        }
    }

    startConnection() {
        const url = new URL(this.urlValue);
        url.searchParams.append('topic', this.topicValue);

        this.eventSource = new EventSource(url);

        this.eventSource.onopen = () => {
            this.attempts = 0;
            this.updateStatus('connected', 'Connecté');

            // Stop polling if it was active as fallback
            if (this.pollingIntervalId) {
                clearInterval(this.pollingIntervalId);
                this.pollingIntervalId = null;
            }
        };

        this.eventSource.onmessage = (event) => {
            this.handleMessage(JSON.parse(event.data));
        };

        this.eventSource.onerror = () => {
            this.eventSource.close();
            this.handleReconnect();
        };
    }

    handleMessage(data) {
        // Dispatch custom event for other controllers to handle
        this.dispatch('message', { detail: data });

        // Also emit specific event based on type
        if (data.type) {
            this.dispatch(data.type.replace('.', '-'), { detail: data });
        }

        // Log message for debugging
        console.log('[Mercure]', data.type, data);
    }

    handleReconnect() {
        if (this.isDisconnected) return;

        if (this.attempts < this.reconnectAttemptsValue) {
            this.attempts++;
            this.updateStatus('reconnecting', `Reconnexion (${this.attempts}/${this.reconnectAttemptsValue})...`);
            this.reconnectTimeoutId = setTimeout(() => {
                this.reconnectTimeoutId = null;
                if (!this.isDisconnected) {
                    this.startConnection();
                }
            }, this.reconnectDelayValue);
        } else {
            this.updateStatus('failed', 'Connexion échouée - Mode polling activé');
            // Fallback to polling (NFR24)
            this.dispatch('fallback-required');
            this.startPollingFallback();
        }
    }

    /**
     * NFR24: Fallback polling strategy when SSE fails.
     */
    startPollingFallback() {
        if (!this.hasPollingEndpointValue || !this.pollingEndpointValue) {
            console.warn('[Mercure] No polling endpoint configured, skipping fallback');
            return;
        }

        console.log('[Mercure] Starting polling fallback...');

        this.pollingIntervalId = setInterval(() => {
            fetch(this.pollingEndpointValue)
                .then(response => response.json())
                .then(data => {
                    if (data.updates && Array.isArray(data.updates)) {
                        data.updates.forEach(update => this.handleMessage(update));
                    }
                })
                .catch(error => console.error('[Mercure] Polling error:', error));
        }, this.pollingIntervalValue);
    }

    updateStatus(state, message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
            this.statusTarget.dataset.state = state;

            // Add visual styling based on state
            this.statusTarget.classList.remove('text-green-500', 'text-yellow-500', 'text-red-500');
            switch (state) {
                case 'connected':
                    this.statusTarget.classList.add('text-green-500');
                    break;
                case 'reconnecting':
                    this.statusTarget.classList.add('text-yellow-500');
                    break;
                case 'failed':
                    this.statusTarget.classList.add('text-red-500');
                    break;
            }
        }
    }

    /**
     * Manually retry connection.
     */
    retry() {
        this.attempts = 0;

        if (this.pollingIntervalId) {
            clearInterval(this.pollingIntervalId);
            this.pollingIntervalId = null;
        }

        this.startConnection();
    }
}
