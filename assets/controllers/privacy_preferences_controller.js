import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for privacy preferences management (GDPR compliance).
 *
 * Handles AJAX saves when preferences are toggled.
 *
 * Usage:
 * <div data-controller="privacy-preferences"
 *      data-privacy-preferences-csrf-value="token">
 *     <input type="checkbox"
 *            data-privacy-preferences-target="checkbox"
 *            data-action="change->privacy-preferences#toggle"
 *            data-key="show_real_name">
 *     <div data-privacy-preferences-target="toast">Saved</div>
 * </div>
 */
export default class extends Controller {
    static targets = ['checkbox', 'toast'];
    static values = {
        csrf: String,
        messageSaved: { type: String, default: 'Preferences saved' },
        errorSave: { type: String, default: 'Save error' },
        errorConnection: { type: String, default: 'Connection error' }
    };

    async toggle(event) {
        const checkbox = event.target;
        const key = checkbox.dataset.key;
        const value = checkbox.checked;

        // Disable checkbox during save
        checkbox.disabled = true;

        try {
            const response = await fetch('/profile/privacy/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfValue
                },
                body: JSON.stringify({ key, value })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                this.showToast(this.messageSavedValue);
            } else {
                // Revert on error
                checkbox.checked = !value;
                this.showToast(data.error || this.errorSaveValue, true);
            }
        } catch (error) {
            // Revert on network error
            checkbox.checked = !value;
            this.showToast(this.errorConnectionValue, true);
            console.error('Failed to save preference:', error);
        } finally {
            checkbox.disabled = false;
        }
    }

    showToast(message, isError = false) {
        if (!this.hasToastTarget) {
            return;
        }

        const toast = this.toastTarget;

        // Update message and styling
        toast.textContent = message;
        toast.classList.remove('bg-green-500', 'bg-red-500', 'hidden');
        toast.classList.add(isError ? 'bg-red-500' : 'bg-green-500');

        // Auto-hide after 3 seconds
        setTimeout(() => {
            toast.classList.add('hidden');
        }, 3000);
    }
}
