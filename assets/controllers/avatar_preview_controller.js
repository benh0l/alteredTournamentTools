import { Controller } from '@hotwired/stimulus';

/**
 * Avatar preview controller for handling image preview before upload.
 */
export default class extends Controller {
    static targets = ['input', 'preview', 'placeholder'];

    triggerInput() {
        this.inputTarget.click();
    }

    preview() {
        const file = this.inputTarget.files[0];

        if (file) {
            // Validate file type
            if (!file.type.startsWith('image/')) {
                return;
            }

            // Validate file size (2MB max)
            if (file.size > 2 * 1024 * 1024) {
                alert('L\'image ne doit pas depasser 2 Mo.');
                this.inputTarget.value = '';
                return;
            }

            const reader = new FileReader();

            reader.onload = (e) => {
                // Hide placeholder icon
                if (this.hasPlaceholderTarget) {
                    this.placeholderTarget.style.display = 'none';
                }

                // Create or update image
                let img = this.previewTarget.querySelector('img');

                if (!img) {
                    img = document.createElement('img');
                    img.className = 'w-full h-full object-cover';
                    this.previewTarget.appendChild(img);
                }

                img.src = e.target.result;
            };

            reader.readAsDataURL(file);
        }
    }
}
