import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for admin alerts banner (FR75).
 *
 * Handles dismissing alerts via AJAX.
 */
export default class extends Controller {
    removeTimeout = null;
    abortController = null;

    disconnect() {
        // Clear pending timeout
        if (this.removeTimeout) {
            clearTimeout(this.removeTimeout);
            this.removeTimeout = null;
        }
        // Abort pending fetch requests
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
    }

    async acknowledge(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const alertBanner = form.closest('[id^="alert-banner-"]');
        const formData = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                // Remove the alert from the DOM with animation
                if (alertBanner) {
                    alertBanner.style.transition = 'opacity 0.3s, height 0.3s';
                    alertBanner.style.opacity = '0';
                    alertBanner.style.height = '0';
                    alertBanner.style.overflow = 'hidden';
                    alertBanner.style.padding = '0';
                    alertBanner.style.margin = '0';

                    this.removeTimeout = setTimeout(() => {
                        this.removeTimeout = null;
                        alertBanner.remove();

                        // Update alert badge count
                        this.updateAlertBadge();
                    }, 300);
                }
            } else {
                console.error('Failed to acknowledge alert');
            }
        } catch (error) {
            console.error('Error acknowledging alert:', error);
        }
    }

    updateAlertBadge() {
        // Cancel any previous fetch
        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = new AbortController();

        // Fetch current alert count and update badge
        fetch('/admin/alerts/active', { signal: this.abortController.signal })
            .then(response => response.json())
            .then(data => {
                this.abortController = null;
                const badge = document.querySelector('[href*="admin_alerts"] .bg-red-600');
                const alertCount = data.alerts?.length || 0;

                if (badge) {
                    if (alertCount > 0) {
                        badge.textContent = alertCount;
                    } else {
                        badge.remove();
                    }
                }
            })
            .catch(error => {
                if (error.name !== 'AbortError') {
                    console.error('Error updating badge:', error);
                }
            });
    }
}
