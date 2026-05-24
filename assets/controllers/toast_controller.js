import { Controller } from '@hotwired/stimulus';

/**
 * Toast Notification Controller
 *
 * Manages toast notifications with auto-dismiss, stacking, and animations.
 *
 * Usage in HTML:
 *   <div data-controller="toast" data-toast-duration-value="5000">
 *     <div data-toast-target="container" class="toast-container"></div>
 *   </div>
 *
 * Trigger from JavaScript:
 *   this.dispatch('toast:show', { detail: { type: 'success', message: 'Done!' } })
 *
 * Or via window event:
 *   window.dispatchEvent(new CustomEvent('toast:show', { detail: { type: 'error', message: 'Error!' } }))
 */
export default class extends Controller {
    static targets = ['container'];
    static values = {
        duration: { type: Number, default: 5000 }
    };

    connect() {
        // Mark controller as connected for external scripts
        this.element.__stimulusControllerConnected = true;

        // Listen for global toast events
        this.handleGlobalToast = this.handleGlobalToast.bind(this);
        window.addEventListener('toast:show', this.handleGlobalToast);
    }

    disconnect() {
        window.removeEventListener('toast:show', this.handleGlobalToast);
    }

    handleGlobalToast(event) {
        const { type, message, title, duration } = event.detail;
        this.show(type, message, title, duration);
    }

    /**
     * Show a toast notification
     * @param {string} type - success, error, warning, info
     * @param {string} message - Toast message
     * @param {string} title - Optional title
     * @param {number} duration - Auto-dismiss duration in ms (0 = no auto-dismiss)
     */
    show(type = 'info', message = '', title = '', duration = null) {
        const toast = this.createToast(type, message, title);
        this.containerTarget.appendChild(toast);

        // Auto-dismiss
        const dismissDuration = duration ?? this.durationValue;
        if (dismissDuration > 0) {
            setTimeout(() => this.dismiss(toast), dismissDuration);
        }

        return toast;
    }

    createToast(type, message, title) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} relative overflow-hidden`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');

        const icon = this.getIcon(type);
        const titleHtml = title ? `<div class="toast-title">${this.escapeHtml(title)}</div>` : '';
        const messageHtml = message ? `<div class="toast-message">${this.escapeHtml(message)}</div>` : '';

        toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                ${titleHtml}
                ${messageHtml}
            </div>
            <button type="button" class="toast-close" data-action="click->toast#close" aria-label="Fermer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            <div class="toast-progress"></div>
        `;

        return toast;
    }

    getIcon(type) {
        const icons = {
            success: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>`,
            error: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>`,
            warning: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>`,
            info: `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>`
        };
        return icons[type] || icons.info;
    }

    close(event) {
        const toast = event.target.closest('.toast');
        if (toast) {
            this.dismiss(toast);
        }
    }

    dismiss(toast) {
        if (!toast || toast.classList.contains('toast-hiding')) return;

        toast.classList.add('toast-hiding');
        toast.addEventListener('animationend', () => {
            toast.remove();
        }, { once: true });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public methods for programmatic access
    success(message, title = '') {
        return this.show('success', message, title);
    }

    error(message, title = '') {
        return this.show('error', message, title);
    }

    warning(message, title = '') {
        return this.show('warning', message, title);
    }

    info(message, title = '') {
        return this.show('info', message, title);
    }
}
