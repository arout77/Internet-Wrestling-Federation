// booking.js

import { wrestlers as fetchWrestlers, getRandomInt } from './data.js';
import {
    renderWrestlerCard,
    initializeRoster,
    updateDropZoneVisual,
    renderWrestlerInDropZone,
    hideWrestlerCardInRoster,
    showWrestlerCardInRoster,
    showModal,
    hideModal,
    createDragCloneForTouch,
    updateDragClonePosition,
    removeDragClone,
    highlightDropZone,
    unhighlightDropZone
} from './dom.js';

// --- Global State ---
let allWrestlers = [];
let contractedWrestlers = [];
let freeAgents = [];
let bookedMatches = {}; // Dynamic object to store match data by ID
let currentMoney = 20000;
let matchCounter = 0;
const MIN_ROSTER_SIZE = 10;

// Venue data
const venues = [
    { name: "High School Gym", cost: 500, capacity: 500, ticketPrice: 5, merchSalesPerFan: 2 },
    { name: "Local Fairgrounds", cost: 2000, capacity: 2000, ticketPrice: 10, merchSalesPerFan: 3 },
    { name: "Community Arena", cost: 7500, capacity: 5000, ticketPrice: 15, merchSalesPerFan: 4 },
    { name: "Major City Stadium", cost: 25000, capacity: 15000, ticketPrice: 25, merchSalesPerFan: 6 },
    { name: "Madison Square Garden", cost: 100000, capacity: 20000, ticketPrice: 50, merchSalesPerFan: 10 }
];
let selectedVenue = venues[0];
let advertisingSpend = 0;

// --- DOM Elements ---
let wrestlerRosterDiv, ppvNameInput, currentMoneySpan, currentRosterSizeSpan,
    currentRosterSizeModalSpan, matchSlotsContainer, bookPpvBtn, resetPpvBtn,
    ppvLogModal, ppvLog, openFreeAgencyModalBtn, freeAgentListDiv, venueSelect,
    venueDetailsP, advertisingSpendInput, advertisingValueSpan, addMatchBtn, matchCardTemplate;

// --- Drag and Drop State ---
let draggedWrestlerId = null;
let isDraggingTouch = false;
let currentTouchDragClone = null;
let currentDropZone = null;

// --- UI & State Management Functions ---

function updateMoneyDisplay() {
    if (currentMoneySpan) {
        currentMoneySpan.textContent = `$${currentMoney.toFixed(2)}`;
    }
}

function updateRosterSizeDisplay() {
    if (currentRosterSizeSpan) {
        currentRosterSizeSpan.textContent = `${contractedWrestlers.length} / ${MIN_ROSTER_SIZE} (min)`;
    }
    if (currentRosterSizeModalSpan) {
        currentRosterSizeModalSpan.textContent = `${contractedWrestlers.length} / 20`;
    }
}

function createMatchCard(isMainEvent = false) {
    matchCounter++;
    const clone = matchCardTemplate.content.cloneNode(true);
    const matchCard = clone.querySelector('.match-card');
    const matchTitle = clone.querySelector('.match-title');
    const matchContent = clone.querySelector('.match-content');

    matchCard.dataset.matchId = matchCounter;
    matchTitle.textContent = isMainEvent ? `Main Event` : `Match ${matchCounter}`;

    // Initialize match state in the global object
    bookedMatches[matchCounter] = {
        type: 'singles',
        participants: {}
    };

    renderDropZones('singles', matchContent, matchCounter);
    
    // Insert before the "Add Match" button if it exists
    if(addMatchBtn) {
        matchSlotsContainer.insertBefore(clone, null); // Appends to the end of the container
    } else {
        matchSlotsContainer.appendChild(clone);
    }


    const newCard = matchSlotsContainer.querySelector(`[data-match-id='${matchCounter}']`);
    addToggleListeners(newCard);
}

function renderDropZones(type, container, matchId) {
    container.innerHTML = ''; // Clear existing
    const match = bookedMatches[matchId];
    if (!match) return;

    match.type = type;
    
    // Clear previous participants for this match
    Object.values(match.participants).forEach(wrestler => {
        if (wrestler) showWrestlerCardInRoster(wrestler.id);
    });
    match.participants = {};

    if (type === 'singles') {
        container.innerHTML = `
            <div class="flex gap-4">
                <div id="match${matchId}Player1" class="drop-zone w-1/2 bg-gray-800 border-2 border-dashed border-gray-600 rounded-lg flex items-center justify-center p-2">
                    <span class="text-gray-500">Drag Wrestler Here</span>
                </div>
                <div class="flex items-center justify-center text-2xl font-bold">VS</div>
                <div id="match${matchId}Player2" class="drop-zone w-1/2 bg-gray-800 border-2 border-dashed border-gray-600 rounded-lg flex items-center justify-center p-2">
                    <span class="text-gray-500">Drag Wrestler Here</span>
                </div>
            </div>
        `;
        match.participants = { player1: null, player2: null };
    } else { // tag
        container.innerHTML = `
            <div class="flex gap-4">
                <div class="w-1/2 space-y-4">
                    <div id="match${matchId}TeamA1" class="drop-zone bg-gray-800 border-2 border-dashed border-gray-600 rounded-lg flex items-center justify-center p-2">
                        <span class="text-gray-500">Team A - Wrestler 1</span>
                    </div>
                    <div id="match${matchId}TeamA2" class="drop-zone bg-gray-800 border-2 border-dashed border-gray-600 rounded-lg flex items-center justify-center p-2">
                        <span class="text-gray-500">Team A - Wrestler 2</span>
                    </div>
                </div>
                <div class="flex items-center justify-center text-2xl font-bold">VS</div>
                <div class="w-1/2 space-y-4">
                    <div id="match${matchId}TeamB1" class="drop-zone bg-gray-800 border-2 border-dashed border-gray-600 rounded-lg flex items-center justify-center p-2">
                        <span class="text-gray-500">Team B - Wrestler 1</span>
                    </div>
                    <div id="match${matchId}TeamB2" class="drop-zone bg-gray-800 border-2 border-dashed border-gray-600 rounded-lg flex items-center justify-center p-2">
                        <span class="text-gray-500">Team B - Wrestler 2</span>
                    </div>
                </div>
            </div>
        `;
        match.participants = { teamA1: null, teamA2: null, teamB1: null, teamB2: null };
    }
    
    // Re-attach drag/drop listeners to the new drop zones
    container.querySelectorAll('.drop-zone').forEach(zone => {
        zone.addEventListener('dragover', handleDragOver);
        zone.addEventListener('dragleave', () => updateDropZoneVisual(zone, false));
        zone.addEventListener('drop', handleDrop);
        zone.addEventListener('click', (e) => {
             if (e.target.classList.contains('drop-zone') || e.target.parentElement.classList.contains('drop-zone')) {
                removeWrestlerFromDropZone(zone);
            }
        });
    });
}

function addToggleListeners(cardElement) {
    const singlesBtn = cardElement.querySelector('.singles-btn');
    const tagBtn = cardElement.querySelector('.tag-btn');
    const matchContent = cardElement.querySelector('.match-content');
    const matchId = cardElement.dataset.matchId;

    singlesBtn.addEventListener('click', () => {
        singlesBtn.classList.add('active');
        tagBtn.classList.remove('active');
        renderDropZones('singles', matchContent, matchId);
    });

    tagBtn.addEventListener('click', () => {
        tagBtn.classList.add('active');
        singlesBtn.classList.remove('active');
        renderDropZones('tag', matchContent, matchId);
    });
}

function assignWrestlerToDropZone(dropZone, wrestler) {
    const dropZoneId = dropZone.id;
    const matchId = dropZone.parentElement.closest('.match-card').dataset.matchId;
    const slotKey = dropZoneId.replace(`match${matchId}`, '').toLowerCase();
    
    // Check if wrestler is already booked anywhere and remove them
    for (const id in bookedMatches) {
        const match = bookedMatches[id];
        for (const key in match.participants) {
            if (match.participants[key] && match.participants[key].id === wrestler.id) {
                const oldDropZone = document.getElementById(`match${id}${key.charAt(0).toUpperCase() + key.slice(1)}`);
                if(oldDropZone) removeWrestlerFromDropZone(oldDropZone, false); // Don't show in roster yet
            }
        }
    }

    // If the target drop zone is occupied, return its wrestler to the roster
    if (bookedMatches[matchId].participants[slotKey]) {
        showWrestlerCardInRoster(bookedMatches[matchId].participants[slotKey].id);
    }

    // Assign new wrestler
    bookedMatches[matchId].participants[slotKey] = wrestler;
    renderWrestlerInDropZone(dropZone, wrestler);
    hideWrestlerCardInRoster(wrestler.id);
}

function removeWrestlerFromDropZone(dropZone, returnToRoster = true) {
    const dropZoneId = dropZone.id;
    if (!dropZone.parentElement.closest('.match-card')) return;
    
    const matchId = dropZone.parentElement.closest('.match-card').dataset.matchId;
    const slotKey = dropZoneId.replace(`match${matchId}`, '').toLowerCase();
    const wrestler = bookedMatches[matchId].participants[slotKey];

    if (wrestler) {
        bookedMatches[matchId].participants[slotKey] = null;
        if(returnToRoster) showWrestlerCardInRoster(wrestler.id);
        
        dropZone.innerHTML = `<span class="text-gray-500">Drag Wrestler Here</span>`;
        if(dropZoneId.includes('Team')) {
            const team = dropZoneId.includes('TeamA') ? 'Team A' : 'Team B';
            const player = dropZoneId.includes('1') ? 'Wrestler 1' : 'Wrestler 2';
            dropZone.innerHTML = `<span class="text-gray-500">${team} - ${player}</span>`;
        }
    }
}


// --- Drag & Drop Handlers ---
function handleDragStart(event) {
    draggedWrestlerId = event.currentTarget.id;
    event.dataTransfer.setData('text/plain', draggedWrestlerId);
    event.dataTransfer.effectAllowed = 'move';
    event.currentTarget.classList.add('opacity-50');
    document.body.style.cursor = 'grabbing';
}

function handleDragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    updateDropZoneVisual(event.currentTarget, true);
}

function handleDrop(event) {
    event.preventDefault();
    const dropZone = event.currentTarget;
    updateDropZoneVisual(dropZone, false);

    const id = event.dataTransfer.getData('text/plain');
    const wrestler = contractedWrestlers.find(w => w.id === id);

    if (wrestler) {
        assignWrestlerToDropZone(dropZone, wrestler);
    } else {
        showWrestlerCardInRoster(id);
    }
    draggedWrestlerId = null;
}

function handleDragEnd(event) {
    const originalCard = document.getElementById(draggedWrestlerId);
    if (originalCard) {
        originalCard.classList.remove('opacity-50');
    }
    document.body.style.cursor = 'auto';
    document.querySelectorAll('.drop-zone.border-yellow-500').forEach(zone => updateDropZoneVisual(zone, false));
}


// --- Free Agency & PPV Logic ---

function renderFreeAgents() {
    if (!freeAgentListDiv) return;
    freeAgentListDiv.innerHTML = '';

    if (freeAgents.length === 0) {
        freeAgentListDiv.innerHTML = '<p class="text-gray-400 col-span-full">No free agents available.</p>';
        return;
    }

    freeAgents.forEach(wrestler => {
        const card = renderWrestlerCard(wrestler, false);
        card.classList.add('relative');

        const signButton = document.createElement('button');
        signButton.textContent = `Sign ($${wrestler.salary})`;
        signButton.classList.add('absolute', 'bottom-2', 'left-1/2', '-translate-x-1/2', 'bg-green-600', 'hover:bg-green-700', 'text-white', 'text-xs', 'font-bold', 'py-1', 'px-2', 'rounded-full');
        
        if (currentMoney < wrestler.salary || contractedWrestlers.length >= 20) {
            signButton.disabled = true;
            signButton.classList.add('bg-gray-500', 'cursor-not-allowed');
        }

        signButton.addEventListener('click', () => signWrestler(wrestler.id));
        card.appendChild(signButton);
        freeAgentListDiv.appendChild(card);
    });
}

function signWrestler(wrestlerId) {
    const wrestlerIndex = freeAgents.findIndex(w => w.id === wrestlerId);
    if (wrestlerIndex > -1) {
        const wrestlerToSign = freeAgents[wrestlerIndex];
        if (currentMoney >= wrestlerToSign.salary && contractedWrestlers.length < 20) {
            currentMoney -= wrestlerToSign.salary;
            contractedWrestlers.push(wrestlerToSign);
            freeAgents.splice(wrestlerIndex, 1);

            updateMoneyDisplay();
            updateRosterSizeDisplay();
            initializeRoster(contractedWrestlers, wrestlerRosterDiv, handleDragStart, handleDragEnd);
            renderFreeAgents();
        } else {
            showModal(ppvLogModal, 'Cannot Sign', 'Not enough money or roster is full.');
        }
    }
}

function populateVenueSelect() {
    if (!venueSelect) return;
    venueSelect.innerHTML = '';
    venues.forEach(venue => {
        const option = document.createElement('option');
        option.value = venue.name;
        option.textContent = `${venue.name} (Cost: $${venue.cost}, Capacity: ${venue.capacity})`;
        venueSelect.appendChild(option);
    });
    selectedVenue = venues[0];
    updateVenueDetails();
}

function updateVenueDetails() {
    if (venueDetailsP && selectedVenue) {
        venueDetailsP.textContent = `Cost: $${selectedVenue.cost} | Capacity: ${selectedVenue.capacity} | Ticket Price: $${selectedVenue.ticketPrice} | Merch/Fan: $${selectedVenue.merchSalesPerFan}`;
    }
}

async function simulatePpv() {
    // This function will need to be updated to use the dynamic `bookedMatches` object
    // and call the backend for simulation results.
    console.log("Simulating PPV with matches:", bookedMatches);
    showModal(ppvLogModal, 'PPV Simulation', 'This feature needs to be connected to the backend simulation logic.');
}

function resetPpv() {
    matchSlotsContainer.innerHTML = '';
    bookedMatches = {};
    matchCounter = 0;
    
    // Create initial matches
    for (let i = 0; i < 4; i++) {
        createMatchCard();
    }
    createMatchCard(true); // Main Event

    // Return all wrestlers to roster
    contractedWrestlers.forEach(w => showWrestlerCardInRoster(w.id));
    
    if (ppvNameInput) ppvNameInput.value = '';
    if (advertisingSpendInput) advertisingSpendInput.value = 0;
    if (advertisingValueSpan) advertisingValueSpan.textContent = '0';
    
    if (venueSelect) {
        venueSelect.value = venues[0].name;
        selectedVenue = venues[0];
        updateVenueDetails();
    }
    hideModal(ppvLogModal);
}

// --- DOMContentLoaded ---
document.addEventListener('DOMContentLoaded', async () => {
    // Assign DOM elements
    wrestlerRosterDiv = document.getElementById('wrestlerRoster');
    ppvNameInput = document.getElementById('ppvName');
    currentMoneySpan = document.getElementById('currentMoney');
    currentRosterSizeSpan = document.getElementById('currentRosterSize');
    currentRosterSizeModalSpan = document.getElementById('currentRosterSizeModal');
    matchSlotsContainer = document.getElementById('matchSlotsContainer');
    bookPpvBtn = document.getElementById('bookPpvBtn');
    resetPpvBtn = document.getElementById('resetPpvBtn');
    ppvLogModal = document.getElementById('ppvLogModal');
    ppvLog = document.getElementById('ppvLog');
    openFreeAgencyModalBtn = document.getElementById('openFreeAgencyModalBtn');
    freeAgentListDiv = document.getElementById('freeAgentList');
    venueSelect = document.getElementById('venueSelect');
    venueDetailsP = document.getElementById('venueDetails');
    advertisingSpendInput = document.getElementById('advertisingSpend');
    advertisingValueSpan = document.getElementById('advertisingValue');
    addMatchBtn = document.getElementById('addMatchBtn');
    matchCardTemplate = document.getElementById('matchCardTemplate');

    // Fetch and process data
    allWrestlers = await fetchWrestlers();
    if (allWrestlers.length > 0) {
        const sortedByOverall = [...allWrestlers].sort((a, b) => a.overall - b.overall);
        const lowest20Wrestlers = sortedByOverall.slice(0, 20);
        const shuffledLowest20 = lowest20Wrestlers.sort(() => 0.5 - Math.random());
        contractedWrestlers = shuffledLowest20.slice(0, MIN_ROSTER_SIZE);

        const contractedIds = new Set(contractedWrestlers.map(w => w.id));
        freeAgents = allWrestlers.filter(w => !contractedIds.has(w.id));

        let initialSalariesCost = 0;
        contractedWrestlers.forEach(wrestler => {
            wrestler.salary = wrestler.salary || Math.round(500 + (wrestler.overall * 20));
            initialSalariesCost += wrestler.salary;
        });
        currentMoney -= initialSalariesCost;

        initializeRoster(contractedWrestlers, wrestlerRosterDiv, handleDragStart, handleDragEnd);
        updateMoneyDisplay();
        updateRosterSizeDisplay();
    }

    // Initial UI setup
    populateVenueSelect();
    resetPpv(); // This will create the initial match cards

    // Event Listeners
    addMatchBtn.addEventListener('click', () => createMatchCard());
    bookPpvBtn.addEventListener('click', simulatePpv);
    resetPpvBtn.addEventListener('click', resetPpv);
    
    venueSelect.addEventListener('change', (e) => {
        selectedVenue = venues.find(v => v.name === e.target.value);
        updateVenueDetails();
    });

    advertisingSpendInput.addEventListener('input', (e) => {
        advertisingSpend = parseInt(e.target.value);
        advertisingValueSpan.textContent = advertisingSpend;
    });

    openFreeAgencyModalBtn.addEventListener('click', () => {
        renderFreeAgents();
        showModal(document.getElementById('freeAgencyModal'));
    });

    document.querySelectorAll('.close-button').forEach(button => {
        button.addEventListener('click', (e) => {
            hideModal(e.target.closest('.modal'));
        });
    });
});
