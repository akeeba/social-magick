/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

((window, document) => {
    class SocialMagickPreviewModal {
        constructor(modalId) {
            this.modal = document.getElementById(modalId);
            this.backdrop = this.modal;
            this.dialog = this.modal.querySelector('.plg_system_socialmagic_modal_dialog');
            this.closeBtn = this.modal.querySelector('.plg_system_socialmagic_modal_close_btn');
            this.isOpen = false;

            this.init();
        }

        init() {
            // Close button click
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', () => this.close());
            }

            // Backdrop click (but not dialog click)
            this.backdrop.addEventListener('click', (e) => {
                if (e.target === this.backdrop) {
                    this.close();
                }
            });

            // ESC key press
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            // Prevent dialog clicks from closing modal
            this.dialog.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }

        open() {
            if (this.isOpen) return;

            this.modal.style.display = 'flex';
            // Force reflow to enable transition
            this.modal.offsetHeight;
            this.modal.classList.add('show');
            this.isOpen = true;

            // Prevent body scroll
            document.body.style.overflow = 'hidden';

            // Dispatch custom event
            this.modal.dispatchEvent(new CustomEvent('modal:opened'));
        }

        close() {
            if (!this.isOpen) return;

            this.modal.classList.remove('show');
            this.isOpen = false;

            // Restore body scroll
            document.body.style.overflow = '';

            // Hide modal after transition
            setTimeout(() => {
                if (!this.isOpen) {
                    this.modal.style.display = 'none';
                }
            }, 300);

            // Dispatch custom event
            this.modal.dispatchEvent(new CustomEvent('modal:closed'));
        }
    }

    // Initialize modal
    const modal = new SocialMagickPreviewModal('plg_system_socialmagic_modal_backdrop');

    // Example usage with trigger button
    const openModalBtn = document.getElementById('plg_system_socialmagic_btn');
    if (openModalBtn) {
        openModalBtn.addEventListener('click', () => modal.open());
    }

    // Example usage with footer buttons
    const cancelBtn = document.getElementById('cancel-btn');
    const confirmBtn = document.getElementById('confirm-btn');

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => modal.close());
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            // Handle confirmation logic here
            console.log('Confirmed!');
            modal.close();
        });
    }

    // Example of listening to modal events
    document.getElementById('plg_system_socialmagic_modal_backdrop').addEventListener('modal:opened', () => {
        console.log('Modal opened');
    });

    document.getElementById('plg_system_socialmagic_modal_backdrop').addEventListener('modal:closed', () => {
        console.log('Modal closed');
    });


})(window, document);