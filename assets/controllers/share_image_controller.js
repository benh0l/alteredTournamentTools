import { Controller } from '@hotwired/stimulus';
import html2canvas from 'html2canvas';

/**
 * Share image controller for capturing and sharing tournament results.
 *
 * Usage:
 * <div data-controller="share-image">
 *     <button data-action="click->share-image#openModal" data-share-image-type-param="standings">Share</button>
 *     <div data-share-image-target="modal" class="hidden">...</div>
 *     <div data-share-image-target="content">Content to capture</div>
 *     <div data-share-image-target="preview">Preview container</div>
 * </div>
 */
export default class extends Controller {
    static targets = ['modal', 'content', 'preview', 'loading', 'actions', 'filename'];
    static values = {
        tournamentName: String,
        type: String, // 'standings' or 'stats'
    };

    connect() {
        this.canvas = null;
        this.blob = null;
        this.escapeHandler = this.handleEscape.bind(this);
        document.addEventListener('keydown', this.escapeHandler);
    }

    disconnect() {
        document.removeEventListener('keydown', this.escapeHandler);
    }

    async openModal(event) {
        event.preventDefault();

        // Get type from button parameter if provided
        const type = event.params?.type || this.typeValue || 'standings';
        this.currentType = type;

        // Show modal
        this.modalTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');

        // Show loading, hide actions and preview
        this.showLoading();

        // Generate the image
        await this.generateImage();
    }

    async generateImage() {
        const content = this.contentTarget;
        const wrapper = content.parentElement;
        let originalStyles = null;

        const restoreStyles = () => {
            if (wrapper && originalStyles) {
                wrapper.style.position = originalStyles.position || '';
                wrapper.style.left = originalStyles.left || '';
                wrapper.style.top = originalStyles.top || '';
                wrapper.style.opacity = originalStyles.opacity || '';
                wrapper.style.pointerEvents = originalStyles.pointerEvents || '';
                wrapper.style.zIndex = originalStyles.zIndex || '';
            }
        };

        try {
            // Save original styles for restoration
            originalStyles = {
                position: wrapper.style.position,
                left: wrapper.style.left,
                top: wrapper.style.top,
                opacity: wrapper.style.opacity,
                pointerEvents: wrapper.style.pointerEvents,
                zIndex: wrapper.style.zIndex,
            };

            // Position content for capture (visible but behind modal)
            wrapper.style.position = 'fixed';
            wrapper.style.left = '0';
            wrapper.style.top = '0';
            wrapper.style.opacity = '1';
            wrapper.style.pointerEvents = 'none';
            wrapper.style.zIndex = '-1';

            // Wait for styles to apply and images to load
            await new Promise(resolve => setTimeout(resolve, 200));

            // Configure html2canvas options
            const options = {
                backgroundColor: '#1f2937', // Dark background for consistency
                scale: 2, // Higher resolution
                useCORS: true, // Allow cross-origin images
                allowTaint: true,
                logging: false,
                scrollX: 0,
                scrollY: 0,
                width: content.offsetWidth,
                height: content.offsetHeight,
            };

            // Generate canvas
            this.canvas = await html2canvas(content, options);

            // Restore original styles
            restoreStyles();

            // Convert to blob for sharing
            this.blob = await new Promise(resolve => {
                this.canvas.toBlob(resolve, 'image/png', 1.0);
            });

            // Show preview
            this.showPreview();

        } catch (error) {
            console.error('Error generating image:', error);
            restoreStyles();
            this.showError();
        }
    }

    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove('hidden');
        }
        if (this.hasActionsTarget) {
            this.actionsTarget.classList.add('hidden');
        }
        if (this.hasPreviewTarget) {
            this.previewTarget.innerHTML = '';
        }
    }

    showPreview() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.add('hidden');
        }
        if (this.hasActionsTarget) {
            this.actionsTarget.classList.remove('hidden');
        }
        if (this.hasPreviewTarget && this.canvas) {
            // Clear and add image preview (using img instead of canvas clone for proper display)
            this.previewTarget.innerHTML = '';
            const previewImg = document.createElement('img');
            previewImg.src = this.canvas.toDataURL('image/png');
            previewImg.style.maxWidth = '100%';
            previewImg.style.height = 'auto';
            previewImg.style.borderRadius = '0.5rem';
            previewImg.style.display = 'block';
            previewImg.style.margin = '0 auto';
            this.previewTarget.appendChild(previewImg);
        }
    }

    showError() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.add('hidden');
        }
        if (this.hasPreviewTarget) {
            this.previewTarget.innerHTML = `
                <div class="text-center py-8 text-red-500">
                    <p>Erreur lors de la generation de l'image.</p>
                    <button data-action="click->share-image#generateImage" class="mt-4 text-sm underline">
                        Reessayer
                    </button>
                </div>
            `;
        }
    }

    getFilename() {
        const tournamentName = this.tournamentNameValue || 'tournament';
        const type = this.currentType || 'results';
        const date = new Date().toISOString().split('T')[0];
        // Sanitize filename
        const safeName = tournamentName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        return `${safeName}_${type}_${date}.png`;
    }

    download(event) {
        event.preventDefault();

        if (!this.canvas) return;

        const link = document.createElement('a');
        link.download = this.getFilename();
        link.href = this.canvas.toDataURL('image/png');
        link.click();

        this.showToast('Image telechargee !');
    }

    async copyToClipboard(event) {
        event.preventDefault();

        if (!this.blob) return;

        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'image/png': this.blob
                })
            ]);
            this.showToast('Image copiee dans le presse-papier !');
        } catch (error) {
            console.error('Error copying to clipboard:', error);
            // Fallback: show message that copy is not supported
            this.showToast('Copie non supportee par ce navigateur. Utilisez le telechargement.', true);
        }
    }

    async nativeShare(event) {
        event.preventDefault();

        if (!this.blob) return;

        // Check if Web Share API is available
        if (!navigator.share || !navigator.canShare) {
            this.showToast('Partage non supporte par ce navigateur.', true);
            return;
        }

        try {
            const file = new File([this.blob], this.getFilename(), { type: 'image/png' });

            // Check if we can share files
            if (navigator.canShare({ files: [file] })) {
                await navigator.share({
                    files: [file],
                    title: this.tournamentNameValue || 'Resultats du tournoi',
                    text: 'Resultats du tournoi - Altered Tournament Tools'
                });
                this.showToast('Partage effectue !');
            } else {
                // Fallback to sharing just text/URL
                this.showToast('Partage de fichiers non supporte. Utilisez le telechargement.', true);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error sharing:', error);
                this.showToast('Erreur lors du partage.', true);
            }
        }
    }

    close(event) {
        if (event) {
            event.preventDefault();
        }

        this.modalTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');

        // Clean up
        this.canvas = null;
        this.blob = null;
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

    showToast(message, isError = false) {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg z-[100] transition-all transform translate-y-0 opacity-100 ${
            isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);

        // Remove after 3 seconds
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}
