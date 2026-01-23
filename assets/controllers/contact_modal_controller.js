import { Controller } from '@hotwired/stimulus';

/**
 * Contact modal controller for report form.
 *
 * Usage:
 * <div data-controller="contact-modal">
 *     <button data-action="click->contact-modal#open">Open</button>
 *     <div data-contact-modal-target="modal" class="hidden">
 *         <form data-contact-modal-target="form" data-action="submit->contact-modal#submit">
 *             ...
 *         </form>
 *         <div data-contact-modal-target="success" class="hidden">Success message</div>
 *     </div>
 * </div>
 */
export default class extends Controller {
    static targets = ['modal', 'form', 'success', 'error', 'pageUrl', 'submitButton'];

    connect() {
        // Close modal on escape key
        this.escapeHandler = this.handleEscape.bind(this);
        document.addEventListener('keydown', this.escapeHandler);
    }

    disconnect() {
        document.removeEventListener('keydown', this.escapeHandler);
    }

    open(event) {
        event.preventDefault();

        // Set current page URL
        if (this.hasPageUrlTarget) {
            this.pageUrlTarget.value = window.location.href;
        }

        // Reset form state
        this.resetForm();

        // Show modal
        this.modalTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }

        this.modalTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    closeOnBackdrop(event) {
        if (event.target === this.modalTarget) {
            this.close(event);
        }
    }

    handleEscape(event) {
        if (event.key === 'Escape' && !this.modalTarget.classList.contains('hidden')) {
            this.close();
        }
    }

    async submit(event) {
        event.preventDefault();

        const form = this.formTarget;
        const formData = new FormData(form);

        // Disable submit button
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true;
            this.submitButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
        }

        // Hide error message
        if (this.hasErrorTarget) {
            this.errorTarget.classList.add('hidden');
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (data.success) {
                // Show success message
                this.formTarget.classList.add('hidden');
                if (this.hasSuccessTarget) {
                    this.successTarget.classList.remove('hidden');
                }

                // Auto close after 3 seconds
                setTimeout(() => {
                    this.close();
                    this.resetForm();
                }, 3000);
            } else {
                // Show error message
                if (this.hasErrorTarget) {
                    this.errorTarget.textContent = data.errors?.join(', ') || data.message;
                    this.errorTarget.classList.remove('hidden');
                }
            }
        } catch (error) {
            console.error('Contact form error:', error);
            if (this.hasErrorTarget) {
                this.errorTarget.textContent = 'Une erreur est survenue. Veuillez réessayer.';
                this.errorTarget.classList.remove('hidden');
            }
        } finally {
            // Re-enable submit button
            if (this.hasSubmitButtonTarget) {
                this.submitButtonTarget.disabled = false;
                this.submitButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    }

    resetForm() {
        this.formTarget.reset();
        this.formTarget.classList.remove('hidden');

        if (this.hasSuccessTarget) {
            this.successTarget.classList.add('hidden');
        }

        if (this.hasErrorTarget) {
            this.errorTarget.classList.add('hidden');
        }
    }
}
