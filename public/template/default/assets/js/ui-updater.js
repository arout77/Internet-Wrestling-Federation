/**
 * ui-updater.js
 * Contains globally accessible functions to update UI elements without page reloads.
 */

/**
 * Updates the gold display in the main navigation bar.
 * @param {number} newGoldAmount - The new amount of gold to display.
 */
function updateGoldDisplay(newGoldAmount) {
    const goldDisplay = document.getElementById('navbar-gold-display');
    if (goldDisplay) {
        // Animate the update for better visual feedback
        goldDisplay.classList.add('animate-pulse', 'text-green-300');
        goldDisplay.textContent = newGoldAmount;
        setTimeout(() => {
            goldDisplay.classList.remove('animate-pulse', 'text-green-300');
        }, 1500);
    } else {
        console.error('Could not find the navbar gold display element.');
    }
}

/**
 * Displays a non-blocking notification message at the top of the screen.
 * Replaces the default browser alert().
 * @param {string} message - The message to display.
 * @param {string} type - 'success', 'error', or 'info'.
 */
function showNotification(message, type = 'info') {
    // Remove any existing notification
    const existingNotification = document.getElementById('global-notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    const notification = document.createElement('div');
    notification.id = 'global-notification';
    notification.textContent = message;

    // Base styles
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.left = '50%';
    notification.style.transform = 'translateX(-50%)';
    notification.style.padding = '1rem 2rem';
    notification.style.borderRadius = '8px';
    notification.style.color = 'white';
    notification.style.zIndex = '9999';
    notification.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
    notification.style.transition = 'opacity 0.5s ease-in-out';
    notification.style.opacity = '0';

    // Type-specific styles
    if (type === 'success') {
        notification.style.backgroundColor = '#28a745'; // Green
    } else if (type === 'error') {
        notification.style.backgroundColor = '#dc3545'; // Red
    } else {
        notification.style.backgroundColor = '#17a2b8'; // Blue
    }

    document.body.appendChild(notification);

    // Fade in
    setTimeout(() => {
        notification.style.opacity = '1';
    }, 10);

    // Fade out and remove after 4 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            notification.remove();
        }, 500); // Wait for fade out to complete
    }, 4000);
}

/**
 * A promise-based confirmation dialog to replace the blocking confirm().
 * @param {string} message - The confirmation message.
 * @returns {Promise<boolean>} - A promise that resolves to true if confirmed, false otherwise.
 */
function showConfirmation(message) {
    return new Promise(resolve => {
        // Remove existing modal if any
        const existingModal = document.getElementById('global-confirm-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalOverlay = document.createElement('div');
        modalOverlay.id = 'global-confirm-modal';
        modalOverlay.style.position = 'fixed';
        modalOverlay.style.inset = '0';
        modalOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.75)';
        modalOverlay.style.zIndex = '9998';
        modalOverlay.style.display = 'flex';
        modalOverlay.style.alignItems = 'center';
        modalOverlay.style.justifyContent = 'center';

        const modalContent = `
            <div style="background-color: #2d3748; color: white; padding: 2rem; border-radius: 0.5rem; text-align: center; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
                <p style="margin-bottom: 1.5rem; font-size: 1.125rem;">${message}</p>
                <div style="display: flex; justify-content: center; gap: 1rem;">
                    <button id="confirm-btn-yes" style="background-color: #28a745; padding: 0.75rem 1.5rem; border-radius: 0.375rem; border: none; color: white; cursor: pointer;">Yes</button>
                    <button id="confirm-btn-no" style="background-color: #6c757d; padding: 0.75rem 1.5rem; border-radius: 0.375rem; border: none; color: white; cursor: pointer;">No</button>
                </div>
            </div>
        `;
        modalOverlay.innerHTML = modalContent;
        document.body.appendChild(modalOverlay);

        const closeModal = (result) => {
            modalOverlay.remove();
            resolve(result);
        };

        document.getElementById('confirm-btn-yes').onclick = () => closeModal(true);
        document.getElementById('confirm-btn-no').onclick = () => closeModal(false);
    });
}
