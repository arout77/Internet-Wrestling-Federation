// dom.js

// Removed: const wrestlerRoster = document.getElementById('wrestlerRoster'); // This global fetch is problematic if DOM isn't ready
const singleMatchLayout = document.getElementById('singleMatchLayout');
const tagTeamMatchLayout = document.getElementById('tagTeamMatchLayout');
const resultModal = document.getElementById('resultModal');
const modalTitle = document.getElementById('modalTitle');
const modalMessage = document.getElementById('modalMessage');
const matchLog = document.getElementById('matchLog'); // Ensure matchLog is accessible
const spinner = document.getElementById('spinner'); // Corrected: Moved declaration to a safe place.

// Base URL for wrestler images
const IMAGE_BASE_URL = baseUrl + '/public/media/images/';

/**
 * Shows the animated spinner.
 */
export function showSpinner() {
    if (spinner) {
        spinner.classList.remove('hidden');
    }
}

/**
 * Hides the animated spinner.
 */
export function hideSpinner() {
    if (spinner) {
        spinner.classList.add('hidden');
    }
}

/**
 * Renders a single wrestler card for display in the roster or a drop zone.
 * @param {object} wrestler - The wrestler object.
 * @param {boolean} isDraggable - Whether the card should be draggable.
 * @param {number} [currentHp] - The current HP of the wrestler (for modal display).
 * @param {number} [initialHp] - The initial HP of the wrestler (for modal display).
 * @param {number} [currentStamina] - The current stamina of the wrestler (for modal display).
 * @param {number} [initialStamina] - The initial stamina of the wrestler (for modal display).
 * @returns {HTMLElement} The created wrestler card element.
 */
export function renderWrestlerCard(wrestler, isDraggable = true, currentHp, initialHp, currentStamina, initialStamina) {
    const card = document.createElement('div');
    // Apply classes for styling and drag-and-drop functionality
    card.id = wrestler.id; // Original ID for roster card
    card.classList.add(
        'wrestler-card', // A new general class for all wrestler cards
        'wrestler-roster-card-item', // Specific class for roster items
        'bg-gray-900', 'rounded-lg', 'shadow-md', 'p-4', 'text-center',
        'transform', 'transition-all', 'duration-200', 'hover:scale-105', 'hover:shadow-xl',
        'border-2', 'border-indigo-700', // Kept default border for non-hover state
        'relative', // Needed for pseudo-element positioning
        'flex', 'flex-col', 'items-center', 'justify-between' // Added flex for layout
    );

    // Removed 'hover:border-yellow-500' as the gradient border will now handle the hover effect.
    // The visual effect is now managed by the CSS in style.css using a pseudo-element.

    if (isDraggable) {
        card.classList.add('cursor-grab');
        card.setAttribute('draggable', 'true');
    }

    const img = document.createElement('img');
    img.src = wrestler.image;
    img.alt = wrestler.name;
    img.classList.add('w-36', 'h-36', 'rounded-full', 'object-cover', 'mx-auto', 'mb-2', 'border-2', 'border-gray-600');
    img.onerror = function() {
        this.onerror=null;
        this.src=`https://placehold.co/96x96/1a202c/e2e8f0?text=${wrestler.name.charAt(0)}`;
    };
    card.appendChild(img);

    const name = document.createElement('h3');
    name.classList.add('text-xl', 'font-bold', 'text-yellow-400', 'mb-1'); // Adjusted mb for new elements
    name.textContent = wrestler.name;
    card.appendChild(name);

    // Add Height and Weight
    const dimensions = document.createElement('p');
    dimensions.classList.add('text-sm', 'text-gray-300', 'mb-2'); // Added mb-2 for spacing
    dimensions.textContent = `${wrestler.height} | ${wrestler.weight} lbs.`;
    card.appendChild(dimensions);

    // Health and Stamina Display (only if currentHp/initialHp are provided, i.e., for modal display)
    if (typeof currentHp === 'number' && typeof initialHp === 'number') {
        const healthContainer = document.createElement('div');
        healthContainer.classList.add('w-full', 'mb-2');

        const healthLabel = document.createElement('p');
        healthLabel.classList.add('text-sm', 'text-gray-400', 'mb-1');
        healthLabel.textContent = 'HP:';
        healthContainer.appendChild(healthLabel);

        const healthBarBackground = document.createElement('div');
        healthBarBackground.classList.add('w-full', 'bg-gray-700', 'rounded-full', 'h-3');
        const healthBar = document.createElement('div');
        healthBar.id = `health-bar-${wrestler.id}`;
        healthBar.classList.add('bg-green-500', 'h-3', 'rounded-full', 'transition-all', 'duration-500');
        // Prevent division by zero if initialHp is 0
        healthBar.style.width = `${initialHp > 0 ? (currentHp / initialHp) * 100 : 0}%`;
        healthBarBackground.appendChild(healthBar);
        healthContainer.appendChild(healthBarBackground);

        const hpText = document.createElement('p');
        hpText.id = `hp-text-${wrestler.id}`;
        hpText.classList.add('text-xs', 'text-gray-300', 'mt-1');
        hpText.textContent = `${Math.max(0, currentHp).toFixed(0)} / ${initialHp.toFixed(0)}`;
        healthContainer.appendChild(hpText);

        card.appendChild(healthContainer);
    }

    if (typeof currentStamina === 'number' && typeof initialStamina === 'number') {
        const staminaContainer = document.createElement('div');
        staminaContainer.classList.add('w-full', 'mb-2');

        const staminaLabel = document.createElement('p');
        staminaLabel.classList.add('text-sm', 'text-gray-400', 'mb-1');
        staminaLabel.textContent = 'Stamina:';
        staminaContainer.appendChild(staminaLabel);

        const staminaBarBackground = document.createElement('div');
        staminaBarBackground.classList.add('w-full', 'bg-gray-700', 'rounded-full', 'h-3');
        const staminaBar = document.createElement('div');
        staminaBar.id = `stamina-bar-${wrestler.id}`;
        staminaBar.classList.add('bg-blue-500', 'h-3', 'rounded-full', 'transition-all', 'duration-500');
        staminaBar.style.width = `${initialStamina > 0 ? (currentStamina / initialStamina) * 100 : 0}%`;
        staminaBarBackground.appendChild(staminaBar);
        staminaContainer.appendChild(staminaBarBackground);

        const staminaText = document.createElement('p');
        staminaText.id = `stamina-text-${wrestler.id}`;
        staminaText.classList.add('text-xs', 'text-gray-300', 'mt-1');
        staminaText.textContent = `${Math.max(0, currentStamina).toFixed(0)} / ${initialStamina.toFixed(0)}`;
        staminaContainer.appendChild(staminaText);

        card.appendChild(staminaContainer);
    }


    // Stats Display (only for roster cards, not modal displays)
    if (!currentHp && !initialHp) { // Only show stats if not in modal context
        const statsToDisplay = ['strength', 'technicalAbility', 'brawlingAbility', 'aerialAbility', 'stamina', 'toughness'];
        const statsGrid = document.createElement('div');
        statsGrid.classList.add('grid', 'grid-cols-2', 'gap-x-4', 'gap-y-1', 'text-sm', 'text-gray-300', 'text-left', 'w-full', 'mt-2');

        statsToDisplay.forEach(statKey => {
            if (wrestler.stats.hasOwnProperty(statKey)) {
                const listItem = document.createElement('div');
                listItem.classList.add('flex', 'justify-between', 'items-center');

                let displayStatName = statKey.replace(/([A-Z])/g, ' $1').replace('Ability', '').trim();
                displayStatName = displayStatName.charAt(0).toUpperCase() + displayStatName.slice(1); // Capitalize first letter

                listItem.innerHTML = `<span class="font-semibold">${displayStatName}:</span> <span>${wrestler.stats[statKey]}</span>`;
                statsGrid.appendChild(listItem);
            }
        });
        card.appendChild(statsGrid);

        const overall = document.createElement('p');
        overall.classList.add('text-lg', 'font-bold', 'text-yellow-300', 'mt-2');
        overall.textContent = `Overall: ${wrestler.overall}`;
        card.appendChild(overall);
    }

    return card;
}

/**
 * Initializes the wrestler roster display.
 * @param {Array<object>} wrestlersData - An array of wrestler objects.
 * @param {HTMLElement} rosterElement - The DOM element to render the roster into.
 * @param {function} dragStartHandler - The event handler for dragstart.
 * @param {function} touchStartHandler - The event handler for touchstart.
 * @param {function} dragEndHandler - The event handler for dragend. // Added dragEndHandler
 */
export function initializeRoster(wrestlersData, rosterElement, dragStartHandler, touchStartHandler, dragEndHandler) {
    if (!rosterElement) {
        console.error('Roster display element not found!');
        return;
    }
    rosterElement.innerHTML = ''; // Clear existing content
    wrestlersData.forEach(wrestler => {
        const card = renderWrestlerCard(wrestler, true);
        card.addEventListener('dragstart', dragStartHandler);
        card.addEventListener('dragend', dragEndHandler); // Attach dragend listener
        card.addEventListener('touchstart', touchStartHandler, { passive: false }); // Add touchstart listener
        rosterElement.appendChild(card);
    });
}


/**
 * Sets up drag and drop listeners for all drop zones.
 * @param {function} dragOverHandler - The event handler for dragover.
 * @param {function} dropHandler - The event handler for drop.
 */
export function setupDragAndDropListeners(dragOverHandler, dropHandler) {
    const dropZones = document.querySelectorAll('.drop-zone');
    console.log(`[setupDragAndDropListeners] Found ${dropZones.length} drop zones.`); // New log
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', dragOverHandler);
        zone.addEventListener('drop', dropHandler);
        // Removed: zone.addEventListener('touchend', touchEndHandler); // This is now handled globally in app.js
    });
}


/**
 * Updates the visual appearance of a drop zone.
 * @param {HTMLElement} dropZone - The drop zone element.
 * @param {boolean} isDraggingOver - True if a draggable item is currently over the zone.
 */
export function updateDropZoneVisual(dropZone, isDraggingOver) {
    if (isDraggingOver) {
        dropZone.classList.add('border-yellow-500', 'bg-gray-700');
        dropZone.classList.remove('border-gray-600', 'bg-gray-800');
    } else {
        dropZone.classList.remove('border-yellow-500', 'bg-gray-700');
        dropZone.classList.add('border-gray-600', 'bg-gray-800');
    }
}

/**
 * Renders a wrestler card into a specific drop zone.
 * @param {HTMLElement} dropZone - The drop zone element.
 * @param {object} wrestler - The wrestler object to render.
 * @param {string} matchType - The current match type ('single' or 'tagTeam').
 */
export function renderWrestlerInDropZone(dropZone, wrestler, matchType) {
    dropZone.innerHTML = ''; // Clear existing content
    dropZone.classList.remove('p-2', 'space-x-2'); // Remove old padding and spacing

    // Add a hidden input to store the wrestler's ID for easy retrieval
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.className = 'selected-wrestler-id'; // Add a class for easy selection
    hiddenInput.value = wrestler.id;
    dropZone.appendChild(hiddenInput);

    if (matchType === 'single') {
        // For single match: image, name, overall on left; description on right
        dropZone.classList.remove('flex-col', 'justify-center');
        dropZone.classList.add('flex', 'items-center', 'space-x-4', 'p-4'); // Use flex row, add spacing and padding

        const leftContent = document.createElement('div');
        leftContent.classList.add('flex', 'flex-col', 'items-center', 'justify-center', 'flex-shrink-0'); // Image, name, overall stacked
        
        const img = document.createElement('img');
        img.src = wrestler.image;
        img.alt = wrestler.name;
        img.classList.add('w-24', 'h-24', 'rounded-full', 'object-cover', 'border-2', 'border-yellow-400', 'mb-2');
        img.onerror = function() {
            this.onerror=null;
            this.src=`https://placehold.co/96x96/1a202c/e2e8f0?text=${wrestler.name.charAt(0)}`;
        };
        leftContent.appendChild(img);

        const name = document.createElement('p');
        name.classList.add('text-lg', 'font-bold', 'text-white', 'mb-1');
        name.textContent = wrestler.name;
        leftContent.appendChild(name);

        // Add Height and Weight for single match drop zones
        const dimensions = document.createElement('p');
        dimensions.classList.add('text-sm', 'text-gray-300', 'mb-2');
        dimensions.textContent = `${wrestler.height} | ${wrestler.weight}`;
        leftContent.appendChild(dimensions);

        const overall = document.createElement('p');
        overall.classList.add('text-sm', 'text-yellow-300');
        overall.textContent = `Overall: ${wrestler.overall}`;
        leftContent.appendChild(overall);

        dropZone.appendChild(leftContent);

        const descriptionContainer = document.createElement('div');
        descriptionContainer.classList.add('flex-grow', 'text-gray-300', 'text-left', 'p-2', 'overflow-auto', 'max-h-full'); // Take remaining space, scroll if needed
        descriptionContainer.innerHTML = `<h4 class="font-bold text-yellow-300 mb-2">Description:</h4><p>${wrestler.description || "No description available for this wrestler. Please update wrestler data to include descriptions."}</p>`;
        dropZone.appendChild(descriptionContainer);

    } else if (matchType === 'tagTeam') {
        // For tag team: image, name, overall centered vertically
        dropZone.classList.remove('space-x-4');
        dropZone.classList.add('flex', 'flex-col', 'items-center', 'justify-center', 'has-wrestler', 'p-4'); // Ensure flex-col and centering

        const img = document.createElement('img');
        img.src = wrestler.image;
        img.alt = wrestler.name;
        img.classList.add('w-24', 'h-24', 'rounded-full', 'object-cover', 'border-2', 'border-yellow-400', 'mb-2');
        img.onerror = function() {
            this.onerror=null;
            this.src=`https://placehold.co/96x96/1a202c/e2e8f0?text=${wrestler.name.charAt(0)}`;
        };
        dropZone.appendChild(img);

        const name = document.createElement('p');
        name.classList.add('text-lg', 'font-bold', 'text-white', 'mb-1');
        name.textContent = wrestler.name;
        dropZone.appendChild(name);

        // Add Height and Weight for tag team drop zones
        const dimensions = document.createElement('p');
        dimensions.classList.add('text-sm', 'text-gray-300', 'mb-2');
        dimensions.textContent = `${wrestler.height} | ${wrestler.weight}`;
        dropZone.appendChild(dimensions);

        const overall = document.createElement('p');
        overall.classList.add('text-sm', 'text-yellow-300');
        overall.textContent = `Overall: ${wrestler.overall}`;
        dropZone.appendChild(overall);
    }
    dropZone.classList.add('has-wrestler'); // Mark drop zone as occupied
}

/**
 * Hides a wrestler card from the roster.
 * @param {string} wrestlerId - The ID of the wrestler card to hide.
 */
export function hideWrestlerCardInRoster(wrestlerId) {
    const card = document.getElementById(wrestlerId);
    if (card) {
        card.classList.add('hidden');
    }
}

/**
 * Shows a wrestler card back in the roster.
 * @param {string} wrestlerId - The ID of the wrestler card to show.
 */
export function showWrestlerCardInRoster(wrestlerId) {
    const card = document.getElementById(wrestlerId);
    if (card) {
        card.classList.remove('hidden');
        card.classList.remove('opacity-50'); // Ensure opacity is reset
    }
}

/**
 * Shows a modal dialog.
 * @param {HTMLElement} modalElement - The modal element to show.
 * @param {string} title - The title for the modal.
 * @param {string} message - The message content for the modal.
 */
export function showModal(modalElement, title, message) {
    if (modalElement) {
        const modalTitleElement = modalElement.querySelector('#modalTitle, #winnerModalTitle');
        const modalMessageElement = modalElement.querySelector('#modalMessage, #winnerModalMessage');

        if (modalTitleElement) modalTitleElement.textContent = title;
        if (modalMessageElement) modalMessageElement.textContent = message;

        modalElement.classList.remove('hidden');
    }
}

/**
 * Hides a modal dialog.
 * @param {HTMLElement} modalElement - The modal element to hide.
 */
export function hideModal(modalElement) {
    if (modalElement) {
        modalElement.classList.add('hidden');
    }
}

/**
 * Updates the match log with new entries.
 * @param {Array<string>} entries - An array of HTML strings representing log entries.
 * @param {string} type - The type of log entry ('info', 'action', 'damage', 'result').
 */
export function updateMatchLog(message, type = 'info') {
    const matchLog = document.getElementById('matchLog');
    if (matchLog) {
        const logEntry = document.createElement('p');
        logEntry.innerHTML = message;
        logEntry.classList.add('py-1', 'px-2', 'rounded-md', 'mb-1');

        switch (type) {
            case 'info':
                logEntry.classList.add('text-gray-300');
                break;
            case 'action':
                logEntry.classList.add('text-blue-300', 'font-semibold');
                break;
            case 'damage':
                logEntry.classList.add('text-red-400', 'font-bold');
                break;
            case 'result':
                logEntry.classList.add('text-yellow-400', 'font-bold', 'text-lg', 'mt-2');
                break;
            default:
                logEntry.classList.add('text-gray-300');
        }
        matchLog.appendChild(logEntry);
        matchLog.scrollTop = matchLog.scrollHeight; // Auto-scroll to bottom
    }
}

/**
 * Updates the match result display in the modal.
 * @param {string} title - The title of the match result.
 * @param {string} message - The message describing the result.
 */
export function updateMatchResult(title, message = '') {
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    if (modalTitle) {
        modalTitle.textContent = title;
    }
    if (modalMessage) {
        modalMessage.textContent = message;
    }
}

/**
 * Resets the UI of all drop zones to their initial empty state.
 */
export function resetDropZonesUI() {
    const dropZones = document.querySelectorAll('.drop-zone');
    dropZones.forEach(zone => {
        zone.innerHTML = `<span class="text-gray-500 text-center">Drag Wrestler Here</span>`;
        // Ensure flex-col and centering are applied for the placeholder
        zone.classList.remove('p-2', 'flex', 'items-center', 'space-x-2', 'has-wrestler');
        zone.classList.add('flex-col', 'items-center', 'justify-center');
        updateDropZoneVisual(zone, false); // Ensure no highlight
    });
}

/**
 * Toggles between single match and tag team match layouts.
 * @param {string} matchType - 'single' or 'tagTeam'.
 * @param {HTMLElement} singleLayout - The single match layout element.
 * @param {HTMLElement} tagTeamLayout - The tag team match layout element.
 * @param {HTMLElement} singleBtn - The single match button element.
 * @param {HTMLElement} tagTeamBtn - The tag team match button element.
 */
export function toggleMatchLayout(matchType, singleLayout, tagTeamLayout, singleBtn, tagTeamBtn) {
    if (matchType === 'single') {
        if (singleLayout) singleLayout.classList.remove('hidden');
        if (tagTeamLayout) tagTeamLayout.classList.add('hidden');
        if (singleBtn) singleBtn.classList.add('bg-yellow-500', 'text-gray-900');
        if (tagTeamBtn) tagTeamBtn.classList.remove('bg-yellow-500', 'text-gray-900');
    } else if (matchType === 'tagTeam') {
        if (singleLayout) singleLayout.classList.add('hidden');
        if (tagTeamLayout) tagTeamLayout.classList.remove('hidden');
        if (singleBtn) singleBtn.classList.remove('bg-yellow-500', 'text-gray-900');
        if (tagTeamBtn) tagTeamBtn.classList.add('bg-yellow-500', 'text-gray-900');
    }
}

/**
 * Updates the health and stamina bars and text for a wrestler.
 * @param {string} wrestlerId - The ID of the wrestler.
 * @param {number} currentHp - The current HP.
 * @param {number} initialHp - The initial HP.
 * @param {number} currentStamina - The current stamina.
 * @param {number} initialStamina - The initial stamina.
 */
export function updateWrestlerHealthDisplay(wrestlerId, currentHp, initialHp, currentStamina, initialStamina) {
    const healthBar = document.getElementById(`health-bar-${wrestlerId}`);
    const hpText = document.getElementById(`hp-text-${wrestlerId}`);
    const staminaBar = document.getElementById(`stamina-bar-${wrestlerId}`);
    const staminaText = document.getElementById(`stamina-text-${wrestlerId}`);

    if (healthBar) {
        const healthPercentage = initialHp > 0 ? (currentHp / initialHp) * 100 : 0;
        healthBar.style.width = `${Math.max(0, healthPercentage)}%`;
        if (healthPercentage < 25) {
            healthBar.classList.remove('bg-green-500', 'bg-yellow-500');
            healthBar.classList.add('bg-red-500');
        } else if (healthPercentage < 50) {
            healthBar.classList.remove('bg-green-500', 'bg-red-500');
            healthBar.classList.add('bg-yellow-500');
        } else {
            healthBar.classList.remove('bg-yellow-500', 'bg-red-500');
            healthBar.classList.add('bg-green-500');
        }
    }
    if (hpText) {
        hpText.textContent = `${Math.max(0, currentHp).toFixed(0)} / ${initialHp.toFixed(0)}`;
    }

    if (staminaBar) {
        const staminaPercentage = initialStamina > 0 ? (currentStamina / initialStamina) * 100 : 0;
        staminaBar.style.width = `${Math.max(0, staminaPercentage)}%`;
        if (staminaPercentage < 25) {
            staminaBar.classList.remove('bg-blue-500', 'bg-teal-500');
            staminaBar.classList.add('bg-orange-500');
        } else if (staminaPercentage < 50) {
            staminaBar.classList.remove('bg-blue-500', 'bg-orange-500');
            staminaBar.classList.add('bg-teal-500');
        } else {
            staminaBar.classList.remove('bg-teal-500', 'bg-orange-500');
            staminaBar.classList.add('bg-blue-500');
        }
    }
    if (staminaText) {
        staminaText.textContent = `${Math.max(0, currentStamina).toFixed(0)} / ${initialStamina.toFixed(0)}`;
    }
}

/**
 * Triggers a damage animation on a wrestler's display element.
 * @param {string} wrestlerId - The ID of the wrestler whose display should animate.
 */
export function triggerDamageAnimation(wrestlerId) {
    const wrestlerCard = document.getElementById(wrestlerId); // This is for the roster card
    const modalWrestlerDisplay = document.getElementById(`modal-display-${wrestlerId}`); // This is for the modal display
    const elementsToAnimate = [];

    if (wrestlerCard) elementsToAnimate.push(wrestlerCard);
    if (modalWrestlerDisplay) elementsToAnimate.push(modalWrestlerDisplay);

    elementsToAnimate.forEach(el => {
        el.classList.add('animate-shake');
        setTimeout(() => {
            el.classList.remove('animate-shake');
        }, 500); // Animation duration
    });
}

/**
 * Renders a wrestler's details in a modal display zone, including dynamic HP/Stamina.
 * @param {HTMLElement} displayZone - The specific modal display zone element (e.g., modalSinglePlayer1).
 * @param {object} wrestler - The wrestler object.
 * @param {number} currentHp - The current HP of the wrestler.
 * @param {number} initialHp - The initial HP of the wrestler.
 * @param {number} currentStamina - The current stamina of the wrestler.
 * @param {number} initialStamina - The initial stamina of the wrestler.
 */
export function renderWrestlerInModalDisplay(displayZone, wrestler, currentHp, initialHp, currentStamina, initialStamina) {
    if (!displayZone) {
        console.warn(`Modal display zone not found for wrestler: ${wrestler.name}`);
        return;
    }
    displayZone.innerHTML = ''; // Clear existing content
    displayZone.classList.remove('hidden'); // Ensure it's visible

    const card = renderWrestlerCard(wrestler, false, currentHp, initialHp, currentStamina, initialStamina);
    // Remove draggable classes as these are display-only in the modal
    card.classList.remove('wrestler-roster-card-item', 'cursor-grab');
    card.removeAttribute('draggable');
    card.id = `modal-display-${wrestler.id}`; // Give it a unique ID for modal context

    displayZone.appendChild(card);
    updateWrestlerHealthDisplay(wrestler.id, currentHp, initialHp, currentStamina, initialStamina);
}

// --- Touch Drag-and-Drop Helper Functions (for creating/managing the clone) ---

/**
 * Creates a draggable clone of the touched wrestler card for touch events.
 * @param {HTMLElement} originalCard - The original wrestler card element.
 * @returns {HTMLElement} The created clone element.
 */
export function createDragCloneForTouch(originalCard) {
    const clone = originalCard.cloneNode(true);
    clone.id = originalCard.id + '-clone'; // Unique ID for the clone
    clone.style.position = 'absolute';
    clone.style.zIndex = '1000';
    clone.style.pointerEvents = 'none'; // Ensure it doesn't block events on elements beneath
    clone.style.width = originalCard.offsetWidth + 'px';
    clone.style.height = originalCard.offsetHeight + 'px'; // Maintain height
    clone.classList.add('opacity-75', 'shadow-xl', 'scale-105', 'transition-none'); // No transition for immediate movement
    document.body.appendChild(clone);
    return clone;
}

/**
 * Updates the position of the draggable clone during touchmove.
 * @param {number} clientX - The clientX coordinate of the touch.
 * @param {number} clientY - The clientY coordinate of the touch.
 */
export function updateDragClonePosition(clientX, clientY) {
    if (currentTouchDragClone) {
        currentTouchDragClone.style.left = (clientX - currentTouchDragClone.offsetWidth / 2) + 'px';
        currentTouchDragClone.style.top = (clientY - currentTouchDragClone.offsetHeight / 2) + 'px';
    }
}

/**
 * Removes the draggable clone from the DOM.
 */
export function removeDragClone() {
    if (currentTouchDragClone) {
        currentTouchDragClone.remove();
        currentTouchDragClone = null;
    }
}

/**
 * Highlights a drop zone.
 * @param {HTMLElement} dropZone - The drop zone element to highlight.
 */
export function highlightDropZone(dropZone) {
    dropZone.classList.add('border-yellow-500', 'bg-gray-700');
    dropZone.classList.remove('border-gray-600', 'bg-gray-800');
}

/**
 * Unhighlight a drop zone.
 * @param {HTMLElement} dropZone - The drop zone element to unhighlight.
 */
export function unhighlightDropZone(dropZone) {
    dropZone.classList.remove('border-yellow-500', 'bg-gray-700');
    dropZone.classList.add('border-gray-600', 'bg-gray-800');
}
