import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for admin dashboard real-time updates (FR68).
 *
 * Connects to Mercure SSE for live tournament status updates.
 *
 * Usage:
 * <div data-controller="admin-dashboard"
 *      data-admin-dashboard-mercure-url-value="/.well-known/mercure?topic=*">
 *     <span data-admin-dashboard-target="activeTournaments">10</span>
 * </div>
 */
export default class extends Controller {
    static targets = [
        'activeTournaments',
        'totalPlayers',
        'ongoingTournaments',
        'pendingDisputes',
        'tournamentList'
    ];

    static values = {
        mercureUrl: String
    };

    eventSource = null;
    reconnectTimeout = null;
    refreshTimeout = null;
    isDisconnected = false;

    connect() {
        this.isDisconnected = false;
        this.connectToMercure();
    }

    disconnect() {
        this.isDisconnected = true;
        if (this.reconnectTimeout) {
            clearTimeout(this.reconnectTimeout);
            this.reconnectTimeout = null;
        }
        if (this.refreshTimeout) {
            clearTimeout(this.refreshTimeout);
            this.refreshTimeout = null;
        }
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    connectToMercure() {
        if (this.isDisconnected) return;

        if (!this.mercureUrlValue) {
            console.warn('Admin dashboard: Mercure URL not configured');
            return;
        }

        try {
            // Subscribe to admin-specific topic
            const url = new URL(this.mercureUrlValue);
            url.searchParams.append('topic', '/admin/tournaments');

            this.eventSource = new EventSource(url, { withCredentials: true });

            this.eventSource.onmessage = (event) => {
                this.handleUpdate(JSON.parse(event.data));
            };

            this.eventSource.onerror = (error) => {
                console.error('Admin dashboard SSE error:', error);
                // Reconnect after 5 seconds if not disconnected
                if (!this.isDisconnected) {
                    this.reconnectTimeout = setTimeout(() => this.connectToMercure(), 5000);
                }
            };
        } catch (error) {
            console.error('Failed to connect to Mercure:', error);
        }
    }

    handleUpdate(data) {
        switch (data.type) {
            case 'tournament_status_changed':
                this.handleTournamentStatusChange(data.tournament);
                break;

            case 'stats_update':
                this.updateStats(data.stats);
                break;

            case 'dispute_created':
            case 'dispute_resolved':
                this.updateDisputeCount(data.pendingDisputes);
                break;

            default:
                console.log('Unknown update type:', data.type);
        }
    }

    handleTournamentStatusChange(tournament) {
        // Find the tournament row and update its status
        const row = this.tournamentListTarget.querySelector(
            `tr[data-tournament-id="${tournament.id}"]`
        );

        if (row) {
            const statusCell = row.querySelector('td:nth-child(5)');
            if (statusCell && tournament.status) {
                statusCell.innerHTML = this.getStatusBadge(tournament.status);
            }
        }

        // Refresh the page to get updated stats
        // In production, you might want to update stats via API instead
        this.refreshStats();
    }

    getStatusBadge(status) {
        const badges = {
            'ongoing': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">En cours</span>',
            'published': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Publie</span>',
            'completed': '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Termine</span>',
        };

        return badges[status] || `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">${status}</span>`;
    }

    updateStats(stats) {
        if (this.hasActiveTournamentsTarget && stats.totalActive !== undefined) {
            this.activeTournamentsTarget.textContent = stats.totalActive;
        }
        if (this.hasTotalPlayersTarget && stats.totalPlayers !== undefined) {
            this.totalPlayersTarget.textContent = stats.totalPlayers;
        }
        if (this.hasOngoingTournamentsTarget && stats.ongoing !== undefined) {
            this.ongoingTournamentsTarget.textContent = stats.ongoing;
        }
        if (this.hasPendingDisputesTarget && stats.pendingDisputes !== undefined) {
            this.updateDisputeCount(stats.pendingDisputes);
        }
    }

    updateDisputeCount(count) {
        if (this.hasPendingDisputesTarget) {
            this.pendingDisputesTarget.textContent = count;

            // Update styling based on count
            if (count > 0) {
                this.pendingDisputesTarget.classList.add('text-red-600');
                this.pendingDisputesTarget.classList.remove('text-gray-900');
            } else {
                this.pendingDisputesTarget.classList.remove('text-red-600');
                this.pendingDisputesTarget.classList.add('text-gray-900');
            }
        }
    }

    refreshStats() {
        if (this.isDisconnected) return;

        // Debounce refresh to avoid too many requests
        if (this.refreshTimeout) {
            clearTimeout(this.refreshTimeout);
        }

        this.refreshTimeout = setTimeout(() => {
            if (this.isDisconnected) return;
            // Simple page refresh - in production, use an API endpoint
            window.location.reload();
        }, 2000);
    }
}
