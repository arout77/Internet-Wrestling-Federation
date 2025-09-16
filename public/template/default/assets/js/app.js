// app.js

import { wrestlers, getRandomInt } from './data.js'; // wrestlers is now a Promise
import {
    renderWrestlerCard, initializeRoster, setupDragAndDropListeners,
    updateDropZoneVisual, renderWrestlerInDropZone, hideWrestlerCardInRoster,
    showWrestlerCardInRoster, showModal, hideModal, updateMatchLog,
    updateMatchResult, resetDropZonesUI, toggleMatchLayout,
    updateWrestlerHealthDisplay, triggerDamageAnimation,
    // New touch-related imports for robust drag-and-drop
    createDragCloneForTouch, updateDragClonePosition, removeDragClone,
    highlightDropZone, unhighlightDropZone, renderWrestlerInModalDisplay
} from './dom.js';

import { calculateBettingOdds } from './betting.js'; // Import betting functions
// Removed direct imports from combat.js and utils.js as logic moves to PHP
// import { triggerCrowdReaction, updateMomentum, selectRandomMove, calculateDamage } from './combat.js';
// import { determineWinner } = './utils.js'; // This line was commented out, which is correct for PHP porting.

// Match state
let currentMatchType = 'single';
let selectedWrestlers = {
    player1: null,
    player2: null,
    team1Player1: null,
    team1Player2: null,
    team2Player1: null,
    team2Player2: null
};

let draggedWrestlerId = null;
let isDraggingTouch = false;
let currentTouchDragClone = null;
let currentDropZone = null; // To keep track of the currently highlighted drop zone during drag/touch

// Global match state for turn-based simulation
let matchState = {
    currentHp: {}, // Stores current HP for each wrestler by ID
    currentStamina: {}, // Stores current Stamina for each wrestler by ID
    initialHp: {}, // Stores initial HP for percentage calculation
    initialStamina: {}, // Stores initial Stamina for each wrestler by ID
    currentRound: 0,
    matchLogEntries: [],
    maxRounds: 0, // Will be set based on match type
    modalDisplayZoneMap: {}, // Maps wrestler IDs to their modal display elements
    finisherUsed: {}, // Tracks if a wrestler has used their finisher in the current match
    currentMomentum: {} // Stores current momentum for each wrestler by ID
};

// Store the loaded wrestlers globally within app.js after fetching
let loadedWrestlers = [];

// Global state for sorting
let currentSortKey = 'name'; // Default sort by name
let currentSortOrder = 'asc'; // Default ascending

// --- Helper Functions ---

/**
 * Finds a wrestler by their ID from the loaded wrestlers array.
 * This remains in app.js as it directly depends on loadedWrestlers.
 * @param {string} id - The ID of the wrestler.
 * @returns {object|null} The wrestler object or null if not found.
 */
function findWrestlerById(id) {
    console.log('findWrestlerById: Searching for ID:', id);
    console.log('findWrestlerById: Loaded wrestlers count:', loadedWrestlers.length);
    return loadedWrestlers.find(w => w.id === id);
}


/**
 * Sends match results to the PHP backend.
 * @param {object} resultData - Object containing match details (type, participants, winner/loser/draw).
 */
async function recordMatchResult(resultData) {
    try {
        const response = await fetch(baseUrl + '/api/record_match_result', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(resultData),
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error(`Failed to record match result: ${response.status} - ${response.statusText}`, errorText);
            updateMatchLog(`Error recording match result: ${response.statusText}`, 'error');
        } else {
            const data = await response.json();
            console.log('Match result recorded:', data.message);
            updateMatchLog('Match result saved to database!', 'info');
        }
    } catch (error) {
        console.error('Network error recording match result:', error);
        updateMatchLog(`Network error recording match result: ${error.message}`, 'error');
    }
}

/**
 * Handles the end of the match, displaying the winner and recording the result.
 * @param {object|string} winner - The winning wrestler object or team string ('Team 1', 'Team 2', 'draw').
 */
function endMatch(winner) {
    const matchLogModal = document.getElementById('resultModal');
    const winnerModal = document.getElementById('winnerModal');
    const nextTurnBtn = document.getElementById('nextTurnBtn');
    const winnerImageContainer = document.getElementById('winnerImageContainer');
    const winnerModalTitle = document.getElementById('winnerModalTitle');
    const winnerModalMessage = document.getElementById('winnerModalMessage');

    hideModal(matchLogModal); // Hide match log
    showModal(winnerModal, '', ''); // Show winner modal, title and message set below

    winnerImageContainer.innerHTML = ''; // Clear previous images
    winnerImageContainer.classList.remove('hidden'); // Ensure container is visible

    console.log("endMatch: selectedWrestlers at start:", selectedWrestlers); // Debugging log

    let resultData = {
        match_type: currentMatchType,
        is_draw: false
    };

    if (winner === 'draw') {
        console.log("endMatch: Winner is a draw.");
        if (currentMatchType === 'single') {
            // Defensive checks for null before accessing .id
            resultData.player1_id = selectedWrestlers.player1 ? selectedWrestlers.player1.id : null;
            resultData.player2_id = selectedWrestlers.player2 ? selectedWrestlers.player2.id : null;
            resultData.single_winner_id = null; // Explicitly null for draw
            resultData.single_loser_id = null;  // Explicitly null for draw
            console.log("endMatch: Single match draw data prepared:", resultData); // Debugging log
        } else if (currentMatchType === 'tagTeam') {
            // Prepare team IDs for backend (with defensive checks)
            resultData.team1_player1_id = selectedWrestlers.team1Player1 ? selectedWrestlers.team1Player1.id : null;
            resultData.team1_player2_id = selectedWrestlers.team1Player2 ? selectedWrestlers.team1Player2.id : null;
            resultData.team2_player1_id = selectedWrestlers.team2Player1 ? selectedWrestlers.team2Player1.id : null;
            resultData.team2_player2_id = selectedWrestlers.team2Player2 ? selectedWrestlers.team2Player2.id : null;

            const team1Ids = [];
            if (selectedWrestlers.team1Player1) team1Ids.push(selectedWrestlers.team1Player1.id);
            if (selectedWrestlers.team1Player2) team1Ids.push(selectedWrestlers.team1Player2.id);
            team1Ids.sort();
            const canonicalTeam1Id = team1Ids.join('_');

            const team2Ids = [];
            if (selectedWrestlers.team2Player1) team2Ids.push(selectedWrestlers.team2Player1.id);
            if (selectedWrestlers.team2Player2) team2Ids.push(selectedWrestlers.team2Player2.id);
            team2Ids.sort();
            const canonicalTeam2Id = team2Ids.join('_');

            resultData.team_winner_id = null; // Explicitly null for draw
            resultData.team_loser_id = null;  // Explicitly null for draw
            console.log("endMatch: Tag team draw data prepared:", resultData); // Debugging log
        }

    } else if (typeof winner === 'object' && winner !== null) { // Single match winner (wrestler object)
        console.log("endMatch: Winner is a wrestler object:", winner);
        const winningWrestler = winner;
        // Defensive check for selectedWrestlers.player1 before accessing its id
        const losingWrestler = (winningWrestler.id === (selectedWrestlers.player1 ? selectedWrestlers.player1.id : null)) ? selectedWrestlers.player2 : selectedWrestlers.player1;

        if (winnerModalTitle) winnerModalTitle.textContent = `${winningWrestler.name || 'Unknown Wrestler'} Wins!`;
        if (winnerModalMessage) winnerModalMessage.textContent = `Congratulations to ${winningWrestler.name || 'Unknown Wrestler'}!`;

        // Display winner image
        const img = document.createElement('img');
        img.src = winningWrestler.image;
        img.alt = winningWrestler.name || 'Unknown Wrestler';
        img.classList.add('w-32', 'h-32', 'rounded-full', 'object-cover', 'mx-auto', 'border-4', 'border-green-500');
        img.onerror = function() { // Add onerror for images
            this.onerror=null;
            this.src=`https://placehold.co/128x128/1a202c/e2e8f0?text=${(winningWrestler.name || '?').charAt(0)}`;
        };
        winnerImageContainer.appendChild(img);

        // Populate resultData for single winner (with defensive checks)
        resultData.player1_id = selectedWrestlers.player1 ? selectedWrestlers.player1.id : null;
        resultData.player2_id = selectedWrestlers.player2 ? selectedWrestlers.player2.id : null;
        resultData.single_winner_id = winningWrestler.id;
        resultData.single_loser_id = losingWrestler ? losingWrestler.id : null; // Ensure losingWrestler is not null
        console.log("endMatch: Single match winner data prepared:", resultData); // Debugging log

    } else if (typeof winner === 'string' && (winner === 'Team 1' || winner === 'Team 2')) {
        console.log("endMatch: Winner is a team:", winner);
        if (winnerModalTitle) winnerModalTitle.textContent = `${winner} Wins!`;
        if (winnerModalMessage) winnerModalMessage.textContent = `All members of the opposing team are knocked out!`;

        // Prepare team IDs for backend (with defensive checks)
        resultData.team1_player1_id = selectedWrestlers.team1Player1 ? selectedWrestlers.team1Player1.id : null;
        resultData.team1_player2_id = selectedWrestlers.team1Player2 ? selectedWrestlers.team1Player2.id : null;
        resultData.team2_player1_id = selectedWrestlers.team2Player1 ? selectedWrestlers.team2Player1.id : null;
        resultData.team2_player2_id = selectedWrestlers.team2Player2 ? selectedWrestlers.team2Player2.id : null;

        const team1Ids = [];
        if (selectedWrestlers.team1Player1) team1Ids.push(selectedWrestlers.team1Player1.id);
        if (selectedWrestlers.team1Player2) team1Ids.push(selectedWrestlers.team1Player2.id);
        team1Ids.sort();
        const canonicalTeam1Id = team1Ids.join('_');

        const team2Ids = [];
        if (selectedWrestlers.team2Player1) team2Ids.push(selectedWrestlers.team2Player1.id);
        if (selectedWrestlers.team2Player2) team2Ids.push(selectedWrestlers.team2Player2.id);
        team2Ids.sort();
        const canonicalTeam2Id = team2Ids.join('_');

        // Assign winner and loser team IDs
        resultData.team_winner_id = (winner === 'Team 1') ? canonicalTeam1Id : canonicalTeam2Id;
        resultData.team_loser_id = (winner === 'Team 1') ? canonicalTeam2Id : canonicalTeam1Id;
        console.log("endMatch: Tag team winner data prepared:", resultData); // Debugging log


        // Display images of the winning team members
        if (winner === 'Team 1') {
            const team1Player1 = findWrestlerById(selectedWrestlers.team1Player1 ? selectedWrestlers.team1Player1.id : null);
            const team1Player2 = findWrestlerById(selectedWrestlers.team1Player2 ? selectedWrestlers.team1Player2.id : null);
            if (team1Player1) {
                const img1 = document.createElement('img');
                img1.src = team1Player1.image;
                const p1Name = team1Player1.name || 'Unknown Wrestler'; // Add fallback for name
                img1.alt = p1Name;
                img1.classList.add('w-24', 'h-24', 'rounded-full', 'object-cover', 'mx-2', 'border-2', 'border-green-500');
                img1.onerror = function() { // Add onerror for images
                    this.onerror=null;
                    this.src=`https://placehold.co/96x96/1a202c/e2e8f0?text=${p1Name.charAt(0) || '?'}`;
                };
                winnerImageContainer.appendChild(img1);
            }
            if (team1Player2) {
                const img2 = document.createElement('img');
                img2.src = team1Player2.image;
                const p2Name = team1Player2.name || 'Unknown Wrestler'; // Add fallback for name
                img2.alt = p2Name;
                img2.classList.add('w-24', 'h-24', 'rounded-full', 'object-cover', 'mx-2', 'border-2', 'border-green-500');
                img2.onerror = function() { // Add onerror for images
                    this.onerror=null;
                    this.src=`https://placehold.co/96x96/1a202c/e2e8f0?text=${p2Name.charAt(0) || '?'}`;
                };
                winnerImageContainer.appendChild(img2);
            }
        } else if (winner === 'Team 2') {
            const team2Player1 = findWrestlerById(selectedWrestlers.team2Player1 ? selectedWrestlers.team2Player1.id : null);
            const team2Player2 = findWrestlerById(selectedWrestlers.team2Player2 ? selectedWrestlers.team2Player2.id : null);
            if (team2Player1) {
                const img1 = document.createElement('img');
                img1.src = team2Player1.image;
                const p1Name = team2Player1.name || 'Unknown Wrestler'; // Add fallback for name
                img1.alt = p1Name;
                img1.classList.add('w-24', 'h-24', 'rounded-full', 'object-cover', 'mx-2', 'border-2', 'border-green-500');
                img1.onerror = function() { // Add onerror for images
                    this.onerror=null;
                    this.src=`https://placehold.co/96x96/1a202c/e2e8f0?text=${p1Name.charAt(0) || '?'}`;
                };
                winnerImageContainer.appendChild(img1);
            }
            if (team2Player2) {
                const img2 = document.createElement('img');
                img2.src = team2Player2.image;
                const p2Name = team2Player2.name || 'Unknown Wrestler'; // Add fallback for name
                img2.alt = p2Name;
                img2.classList.add('w-24', 'h-24', 'rounded-full', 'object-cover', 'mx-2', 'border-2', 'border-green-500');
                img2.onerror = function() { // Add onerror for images
                    this.onerror=null;
                    this.src=`https://placehold.co/96x96/1a202c/e2e8f0?text=${p2Name.charAt(0) || '?'}`;
                };
                winnerImageContainer.appendChild(img2);
            }
        }
    }
    if (nextTurnBtn) {
        nextTurnBtn.classList.add('hidden');
    }

    console.log("endMatch: Final resultData before recording:", resultData); // Debugging log
    recordMatchResult(resultData);
}


/**
 * Simulates a single turn in the match by calling the PHP endpoint.
 * @param {boolean} isBulkSimulation - True if part of a bulk simulation, skips UI updates and delays.
 */
async function advanceTurn(isBulkSimulation = false) {
    const matchLogModal = document.getElementById('resultModal');
    const nextTurnBtn = document.getElementById('nextTurnBtn');

    // Prepare data to send to PHP
    const payload = {
        matchState: matchState,
        selectedWrestlers: selectedWrestlers,
        currentMatchType: currentMatchType,
        allWrestlers: loadedWrestlers, // Send all wrestler data for PHP lookup
        isBulkSimulation: isBulkSimulation // Pass the flag to PHP
    };

    try {
        const response = await fetch(baseUrl + '/api/simulate_turn', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error(`Failed to simulate turn: ${response.status} - ${response.statusText}`, errorText);
            updateMatchLog(`Error simulating turn: ${response.statusText}`, 'error');
            if (nextTurnBtn) nextTurnBtn.disabled = false;
            return null;
        }

        const result = await response.json();
        console.log("PHP Simulation Result:", result);

        // Update client-side matchState with data from PHP
        matchState = result.matchState;

        // Update match log
        result.matchLog.forEach(entry => {
            if (!isBulkSimulation) { // Only update UI log for non-bulk simulations
                updateMatchLog(entry.message, entry.type);
            }
        });

        // Update UI for wrestler health and modal displays (only for non-bulk)
        if (!isBulkSimulation) {
            if (currentMatchType === 'single') {
                const p1 = selectedWrestlers.player1;
                const p2 = selectedWrestlers.player2;
                updateWrestlerHealthDisplay(p1.id, matchState.currentHp[p1.id], matchState.initialHp[p1.id], matchState.currentStamina[p1.id], matchState.initialStamina[p1.id], matchState.currentMomentum[p1.id]);
                updateWrestlerHealthDisplay(p2.id, matchState.currentHp[p2.id], matchState.initialHp[p2.id], matchState.currentStamina[p2.id], matchState.initialStamina[p2.id], matchState.currentMomentum[p2.id]);

                renderWrestlerInModalDisplay(document.getElementById('modalSinglePlayer1'), p1, matchState.currentHp[p1.id], matchState.initialHp[p1.id], matchState.currentStamina[p1.id], matchState.initialStamina[p1.id], matchState.currentMomentum[p1.id]);
                renderWrestlerInModalDisplay(document.getElementById('modalSinglePlayer2'), p2, matchState.currentHp[p2.id], matchState.initialHp[p2.id], matchState.currentStamina[p2.id], matchState.initialStamina[p2.id], matchState.currentMomentum[p2.id]);
                await new Promise(resolve => setTimeout(resolve, 500)); // Small delay for animation
            } else if (currentMatchType === 'tagTeam') {
                const wrestlersInMatch = [
                    selectedWrestlers.team1Player1, selectedWrestlers.team1Player2,
                    selectedWrestlers.team2Player1, selectedWrestlers.team2Player2
                ];
                for (const p of wrestlersInMatch) {
                    if (p) {
                        updateWrestlerHealthDisplay(p.id, matchState.currentHp[p.id], matchState.initialHp[p.id], matchState.currentStamina[p.id], matchState.initialStamina[p.id], matchState.currentMomentum[p.id]);
                        renderWrestlerInModalDisplay(matchState.modalDisplayZoneMap[p.id], p, matchState.currentHp[p.id], matchState.initialHp[p.id], matchState.currentStamina[p.id], matchState.initialStamina[p.id], matchState.currentMomentum[p.id]);
                    }
                }
                await new Promise(resolve => setTimeout(resolve, 500)); // Small delay for animation
            }
        }

        // Check for winner after PHP simulation
        if (result.winner && result.winner !== 'no_winner_yet') {
            if (!isBulkSimulation) {
                endMatch(result.winner); // Call endMatch for UI update
            }
            return result.winner; // Return winner for bulk simulation
        } else if (matchState.currentRound >= matchState.maxRounds) {
            if (!isBulkSimulation) {
                updateMatchLog(`--- The match ends in a time limit draw! ---`, 'result');
                endMatch('draw');
            }
            return 'draw';
        }

    } catch (error) {
        console.error('Network error during turn simulation:', error);
        updateMatchLog(`Network error during turn simulation: ${error.message}`, 'error');
        if (nextTurnBtn) nextTurnBtn.disabled = false;
        return null;
    }
    return null; // No winner yet
}

/**
 * Runs multiple match simulations and displays the aggregated results.
 */
async function runMultipleSimulations() {
    const resultModal = document.getElementById('resultModal');
    const modalMessageElement = document.getElementById('modalMessage');

    if ((currentMatchType === 'single' && (!selectedWrestlers.player1 || !selectedWrestlers.player2)) ||
        (currentMatchType === 'tagTeam' && (!selectedWrestlers.team1Player1 || !selectedWrestlers.team1Player2 || !selectedWrestlers.team2Player1 || !selectedWrestlers.team2Player2))) {
        showModal(resultModal, 'Simulation Error', 'Please select the required number of wrestlers before running simulations.');
        return;
    }

    const numSimulations = 100;
    const simulationResults = [];

    // Disable UI buttons during simulation
    const buttonsToDisable = ['startMatchBtn', 'nextTurnBtn', 'randomMatchupBtn', 'singleMatchBtn', 'tagTeamMatchBtn', 'simulate100xBtn', 'resetMatchBtn', 'resetMatchBtnModal', 'resetMatchBtnWinnerModal', 'calculateOddsBtn'];
    buttonsToDisable.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.disabled = true;
    });

    showModal(resultModal, 'Simulating...', 'Running simulations. Please wait...');

    for (let i = 0; i < numSimulations; i++) {
        if (modalMessageElement) {
            modalMessageElement.textContent = `Running simulation ${i + 1} of ${numSimulations}...`;
        }

        let currentSimWinnerStatus = 'no_winner_yet'; // This tracks the actual winner status from PHP
        let simRound = 0;

        // Initialize temporary match state for this simulation
        let tempMatchState = {
            currentHp: {},
            currentStamina: {},
            initialHp: {},
            initialStamina: {},
            currentRound: 0,
            maxRounds: currentMatchType === 'single' ? 75 : 125, // Matches max rounds in PHP
            finisherUsed: {},
            currentMomentum: {}
        };

        // Populate initial HP, Stamina, and Momentum for this specific simulation
        if (currentMatchType === 'single') {
            const p1 = selectedWrestlers.player1;
            const p2 = selectedWrestlers.player2;

            tempMatchState.initialHp[p1.id] = p1.baseHp + p1.stats.stamina;
            tempMatchState.currentHp[p1.id] = p1.baseHp + p1.stats.stamina;
            tempMatchState.initialStamina[p1.id] = p1.stats.stamina;
            tempMatchState.currentStamina[p1.id] = p1.stats.stamina;
            tempMatchState.currentMomentum[p1.id] = 50;

            tempMatchState.initialHp[p2.id] = p2.baseHp + p2.stats.stamina;
            tempMatchState.currentHp[p2.id] = p2.baseHp + p2.stats.stamina;
            tempMatchState.initialStamina[p2.id] = p2.stats.stamina;
            tempMatchState.currentStamina[p2.id] = p2.stats.stamina;
            tempMatchState.currentMomentum[p2.id] = 50;

        } else if (currentMatchType === 'tagTeam') {
            [selectedWrestlers.team1Player1, selectedWrestlers.team1Player2, selectedWrestlers.team2Player1, selectedWrestlers.team2Player2].forEach(p => {
                tempMatchState.initialHp[p.id] = p.baseHp + p.stats.stamina;
                tempMatchState.currentHp[p.id] = p.baseHp + p.stats.stamina;
                tempMatchState.initialStamina[p.id] = p.stats.stamina;
                tempMatchState.currentStamina[p.id] = p.stats.stamina;
                tempMatchState.currentMomentum[p.id] = 50;
            });
        }

        // Simulate one match (headless) by repeatedly calling the PHP endpoint
        while (currentSimWinnerStatus === 'no_winner_yet' && simRound < tempMatchState.maxRounds) {
            simRound++;
            const payload = {
                matchState: tempMatchState, // Send the current temp state
                selectedWrestlers: selectedWrestlers, // Send the original selected wrestlers
                currentMatchType: currentMatchType,
                allWrestlers: loadedWrestlers,
                isBulkSimulation: true // Indicate this is a bulk simulation
            };

            try {
                const response = await fetch(baseUrl + '/api/simulate_turn', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error(`Bulk simulation turn failed: ${response.status} - ${response.statusText}`, errorText);
                    currentSimWinnerStatus = 'error'; // Mark as error to stop simulation
                    break;
                }

                const result = await response.json();
                tempMatchState = result.matchState; // Update temp state with PHP's result
                
                // Update the winner status based on PHP's result
                if (result.winner && result.winner !== 'no_winner_yet') {
                    currentSimWinnerStatus = result.winner;
                } else if (!result.winner && simRound >= tempMatchState.maxRounds) {
                    // If PHP didn't return a winner, but max rounds are hit, it's a draw
                    currentSimWinnerStatus = 'draw';
                } else if (result.winner === null) {
                    // Explicitly handle PHP returning null for winner
                    currentSimWinnerStatus = 'php_returned_null';
                }
                // If result.winner is 'no_winner_yet', currentSimWinnerStatus remains 'no_winner_yet'
                // If result.winner is undefined/missing, currentSimWinnerStatus remains 'no_winner_yet' or previous state.

            } catch (error) {
                console.error('Network error during bulk simulation turn:', error);
                currentSimWinnerStatus = 'error';
                break;
            }
        }

        // Final determination of the winner string to store
        let winnerToStore;
        if (typeof currentSimWinnerStatus === 'object' && currentSimWinnerStatus !== null && currentSimWinnerStatus.id) {
            // If it's a wrestler object (from single match win)
            const winningWrestler = findWrestlerById(currentSimWinnerStatus.id);
            winnerToStore = winningWrestler ? (winningWrestler.name || 'Unknown Wrestler') : ('Unknown Wrestler (ID: ' + currentSimWinnerStatus.id + ')');
        } else if (typeof currentSimWinnerStatus === 'string') {
            // If it's a string ('draw', 'Team 1', 'Team 2', 'error', 'no_winner_yet', 'php_returned_null')
            if (currentSimWinnerStatus === 'no_winner_yet') {
                // If loop ended but no winner was explicitly found, it's a draw (e.g., max rounds hit, but PHP didn't explicitly return 'draw')
                winnerToStore = 'draw';
            } else if (currentSimWinnerStatus === 'php_returned_null') {
                winnerToStore = 'Unknown Winner (PHP returned null)';
            }
            else {
                winnerToStore = currentSimWinnerStatus;
            }
        } else {
            // Catch-all for any other unexpected value
            winnerToStore = 'Unknown Winner (Unexpected Type: ' + typeof currentSimWinnerStatus + ')';
        }

        simulationResults.push(winnerToStore);

        // Yield control back to the browser periodically
        if ((i + 1) % 10 === 0) {
            await new Promise(resolve => setTimeout(resolve, 0));
        }
    }

    console.log("Final simulationResults array:", simulationResults);
    console.log("Final simulationResults array length:", simulationResults.length);
    const finalCounts = {};
    simulationResults.forEach(result => {
        finalCounts[result] = (finalCounts[result] || 0) + 1;
    });
    console.log("Aggregated counts for simulation results:", finalCounts);


    hideModal(resultModal); // Hide "Simulating..." modal
    showSimulationResultsModal(finalCounts); // Pass the aggregated counts

    // Re-enable UI buttons after simulation
    const buttonsToReEnable = ['startMatchBtn', 'nextTurnBtn', 'randomMatchupBtn', 'singleMatchBtn', 'tagTeamMatchBtn', 'simulate100xBtn', 'resetMatchBtn', 'resetMatchBtnModal', 'resetMatchBtnWinnerModal', 'calculateOddsBtn'];
    buttonsToReEnable.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.disabled = false;
    });
}

/**
 * Displays the aggregated results of multiple simulations.
 * @param {Object} counts - An object mapping winner names or 'draw' strings to their counts.
 */
function showSimulationResultsModal(counts) {
    let resultHtml = '<h2 class="text-3xl font-bold text-yellow-400 mb-4">Simulation Results (100 Matches)</h2>';
    resultHtml += '<div class="text-left text-gray-300 mb-6">';
    for (const winner in counts) {
        // The 'winner' variable here is already the name string or 'draw'/'Team X'
        resultHtml += `<p class="text-lg">${winner}: <span class="font-bold">${counts[winner]} wins</span></p>`;
    }
    resultHtml += '</div>';

    const simulationResultModal = document.createElement('div');
    simulationResultModal.id = 'simulationResultModal';
    simulationResultModal.classList.add('modal', 'fixed', 'inset-0', 'flex', 'items-center', 'justify-center', 'z-50');
    simulationResultModal.innerHTML = `
        <div class="modal-content bg-gray-800 p-8 rounded-lg shadow-xl w-11/12 md:w-1/2 relative border-2 border-yellow-500 text-center">
            ${resultHtml}
            <button id="closeSimulationResultsModalBtn" class="btn btn-primary mt-4">Close</button>
        </div>
    `;
    document.body.appendChild(simulationResultModal);

    const closeBtn = document.getElementById('closeSimulationResultsModalBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            simulationResultModal.remove();
        });
    }
}

/**
 * Creates a random matchup based on the current match type.
 */
function createRandomMatchup() {
    resetMatch(); // Clear any existing selections

    const resultModal = document.getElementById('resultModal'); // Get reference to the result modal

    if (loadedWrestlers.length < 2) {
        showModal(resultModal, 'Error', 'Not enough wrestlers in the roster to create a random matchup.');
        return;
    }

    const availableWrestlers = [...loadedWrestlers]; // Create a mutable copy

    if (currentMatchType === 'single') {
        if (availableWrestlers.length < 2) {
            showModal(resultModal, 'Error', 'Not enough wrestlers for a random single match.');
            return;
        }
        const p1Index = getRandomInt(0, availableWrestlers.length - 1);
        const p1 = availableWrestlers.splice(p1Index, 1)[0];

        const p2Index = getRandomInt(0, availableWrestlers.length - 1);
        const p2 = availableWrestlers.splice(p2Index, 1)[0];

        // Directly assign using the helper function
        assignWrestlerToDropZone(document.getElementById('player1DropZone'), p1);
        assignWrestlerToDropZone(document.getElementById('player2DropZone'), p2);

    } else if (currentMatchType === 'tagTeam') {
        if (availableWrestlers.length < 4) {
            showModal(resultModal, 'Error', 'Not enough wrestlers for a random tag team match.');
            return;
        }

        const selectRandomWrestler = () => {
            const index = getRandomInt(0, availableWrestlers.length - 1);
            return availableWrestlers.splice(index, 1)[0];
        };

        // Directly assign using the helper function
        assignWrestlerToDropZone(document.getElementById('team1Player1DropZone'), selectRandomWrestler());
        assignWrestlerToDropZone(document.getElementById('team1Player2DropZone'), selectRandomWrestler());
        assignWrestlerToDropZone(document.getElementById('team2Player1DropZone'), selectRandomWrestler());
        assignWrestlerToDropZone(document.getElementById('team2Player2DropZone'), selectRandomWrestler());
    }
    checkAndToggleStartButton(); // Check if start button should be enabled after random selection
}


/**
 * Assigns a wrestler to the correct selectedWrestlers slot and updates UI.
 * This centralizes the logic used by both mouse and touch drop handlers.
 * @param {HTMLElement} dropZone - The drop zone element.
 * @param {object} wrestler - The wrestler object to assign.
 */
function assignWrestlerToDropZone(dropZone, wrestler) {
    // Determine which selectedWrestlers slot to update based on dropZone.id
    let targetSlot = null;
    if (dropZone.id === 'player1DropZone') {
        targetSlot = 'player1';
    } else if (dropZone.id === 'player2DropZone') {
        targetSlot = 'player2';
    } else if (dropZone.id === 'team1Player1DropZone') {
        targetSlot = 'team1Player1';
    } else if (dropZone.id === 'team1Player2DropZone') {
        targetSlot = 'team1Player2';
    } else if (dropZone.id === 'team2Player1DropZone') {
        targetSlot = 'team2Player1';
    } else if (dropZone.id === 'team2Player2DropZone') {
        targetSlot = 'team2Player2';
    }

    if (targetSlot) {
        // If the target slot is already occupied, return the old wrestler to the roster
        if (selectedWrestlers[targetSlot]) {
            showWrestlerCardInRoster(selectedWrestlers[targetSlot].id);
        }
        // Assign the new wrestler
        selectedWrestlers[targetSlot] = wrestler;
        // Pass currentMatchType to renderWrestlerInDropZone
        renderWrestlerInDropZone(dropZone, wrestler, currentMatchType);
        hideWrestlerCardInRoster(wrestler.id); // Hide the new wrestler from the roster
        checkAndToggleStartButton();
    } else {
        console.warn('assignWrestlerToDropZone: Invalid drop zone ID:', dropZone.id);
        // If it's an invalid drop zone, ensure the original card is visible in the roster
        showWrestlerCardInRoster(wrestler.id);
    }
}

// --- Event Handlers for Drag and Drop (Mouse & Touch) ---

/**
 * Handles the start of a drag operation (mouse).
 * @param {DragEvent} event - The dragstart event.
 */
function handleDragStart(event) {
    // Use event.currentTarget.id to ensure we get the ID of the draggable element itself
    draggedWrestlerId = event.currentTarget.id;
    event.dataTransfer.setData('text/plain', draggedWrestlerId);
    event.dataTransfer.effectAllowed = 'move';
    event.currentTarget.classList.add('opacity-50'); // Reduce opacity of original card
    document.body.style.cursor = 'grabbing'; // Change cursor to grabbing
}

/**
 * Handles drag over event for drop zones (mouse).
 * @param {DragEvent} event - The dragover event.
 */
function handleDragOver(event) {
    event.preventDefault(); // Allow drop
    event.dataTransfer.dropEffect = 'move'; // Explicitly set dropEffect to 'move'
    const dropZone = event.currentTarget;
    if (dropZone !== currentDropZone) {
        if (currentDropZone) {
            updateDropZoneVisual(currentDropZone, false); // Unhighlight previous
        }
        updateDropZoneVisual(dropZone, true); // Highlight current
        currentDropZone = dropZone;
    }
}

/**
 * Handles the drop action for drop zones (mouse).
 * @param {DragEvent} event - The drop event.
 */
function handleDrop(event) {
    event.preventDefault();
    const dropZone = event.currentTarget;
    updateDropZoneVisual(dropZone, false); // Remove highlight
    currentDropZone = null; // Reset

    const id = event.dataTransfer.getData('text/plain');
    const wrestler = findWrestlerById(id);


    if (wrestler) {
        // Opacity reset is now handled solely by handleDragEnd for consistency.
        // The original card will be hidden by hideWrestlerCardInRoster.
        assignWrestlerToDropZone(dropZone, wrestler); // Use the new helper function
    } else {
        console.warn('handleDrop: Wrestler not found for ID:', id);
        // If wrestler not found, ensure the original card is visible in the roster
        showWrestlerCardInRoster(id); // Use 'id' here as it's the dragged ID
    }
    draggedWrestlerId = null; // Reset dragged ID after drop
}

/**
 * Handles the end of a drag operation (mouse).
 * This always fires after dragstart, regardless of drop success.
 * @param {DragEvent} event - The dragend event.
 */
function handleDragEnd(event) {
    // Use event.currentTarget.id which refers to the element that started the drag
    const draggedElementId = event.currentTarget.id;

    const originalCardInRoster = document.getElementById(draggedElementId);
    if (originalCardInRoster) {
        originalCardInRoster.classList.remove('opacity-50'); // Ensure opacity is reset
    } else {
        console.warn('DRAG_END: Original card not found for ID:', draggedElementId);
    }

    // Also ensure any drop zone highlight is removed if drag ended outside a valid drop zone
    if (currentDropZone) {
        updateDropZoneVisual(currentDropZone, false);
        currentDropZone = null;
    }
    draggedWrestlerId = null; // Clear global state for next drag
    document.body.style.cursor = 'auto'; // Reset cursor to auto
}


/**
 * Handles touch start event for wrestler cards (mobile drag).
 * @param {TouchEvent} event - The touchstart event.
 */
function handleTouchStart(event) {
    // Only proceed if it's a single touch
    if (event.touches.length === 1) {
        isDraggingTouch = true; // Set flag
        const touch = event.touches[0];
        const touchedCard = event.currentTarget; // The wrestler card
        draggedWrestlerId = touchedCard.id;

        // Create a visual clone immediately
        currentTouchDragClone = createDragCloneForTouch(touchedCard);
        updateDragClonePosition(touch.clientX, touch.clientY);

        // Reduce opacity of original card
        touchedCard.classList.add('opacity-50');

        // Attach global touchmove and touchend listeners to document.body
        document.body.addEventListener('touchmove', handleTouchMove, { passive: false });
        document.body.addEventListener('touchend', handleTouchEnd);
        document.body.addEventListener('touchcancel', handleTouchCancel); // Add touchcancel for robustness
    }
}

/**
 * Handles touch move event for wrestler cards (mobile drag).
 * @param {TouchEvent} event - The touchmove event.
 */
function handleTouchMove(event) {
    if (isDraggingTouch && event.touches.length === 1) {
        const touch = event.touches[0];
        updateDragClonePosition(touch.clientX, touch.clientY);

        // Prevent default scrolling *only if* we are actively dragging a clone
        // This is crucial to allow normal scrolling when not dragging
        event.preventDefault();

        // Identify the element under the touch point
        const targetElement = document.elementFromPoint(touch.clientX, touch.clientY);
        let newDropZone = null;

        if (targetElement) {
            // Traverse up the DOM to find a drop-zone parent
            let current = targetElement;
            while (current && !current.classList.contains('drop-zone')) {
                current = current.parentElement;
            }
            if (current && current.classList.contains('drop-zone')) {
                newDropZone = current;
            }
        }

        if (newDropZone !== currentDropZone) {
            if (currentDropZone) {
                unhighlightDropZone(currentDropZone);
            }
            if (newDropZone) {
                highlightDropZone(newDropZone);
            }
            currentDropZone = newDropZone;
        }
    }
}

/**
 * Handles touch end event for wrestler cards (mobile drop).
 * This listener is now attached to `document.body`.
 * @param {TouchEvent} event - The touchend event.
 */
function handleTouchEnd(event) {
    if (isDraggingTouch) {
        // Get the last touch position (from where the finger was lifted)
        const touch = event.changedTouches[0];
        if (!touch) {
            console.warn("handleTouchEnd: No changedTouches found on touchend, cannot determine drop position.");
            handleTouchCleanup();
            return;
        }

        const dropPointX = touch.clientX;
        const dropPointY = touch.clientY;

        // Identify the element under the final touch point
        const targetElement = document.elementFromPoint(dropPointX, dropPointY);
        let finalDropZone = null;

        if (targetElement) {
            let current = targetElement;
            while (current && !current.classList.contains('drop-zone')) {
                current = current.parentElement;
            }
            if (current && current.classList.contains('drop-zone')) {
                finalDropZone = current;
            }
        }

        if (currentDropZone) {
            unhighlightDropZone(currentDropZone); // Remove highlight from any active zone
        }

        if (finalDropZone && draggedWrestlerId) {
            const wrestler = findWrestlerById(draggedWrestlerId);

            if (wrestler) {
                assignWrestlerToDropZone(finalDropZone, wrestler); // Use the new helper function
            } else {
                console.warn('TOUCH_END: Wrestler not found for ID:', draggedWrestlerId);
                // If wrestler not found, ensure the original card is visible in the roster
                showWrestlerCardInRoster(draggedWrestlerId);
            }
        } else {
            console.warn('TOUCH_END: No valid drop zone found or draggedWrestlerId is null. Returning card to roster.');
            // If no valid drop zone, return the original card to the roster
            showWrestlerCardInRoster(draggedWrestlerId);
        }
    }
    handleTouchCleanup(); // Perform cleanup regardless of drop success
}

/**
 * Handles touchcancel event (e.g., call interrupted, too many touches).
 * @param {TouchEvent} event - The touchcancel event.
 */
function handleTouchCancel(event) {
    console.warn('Touch drag cancelled.');
    // Ensure original card opacity is reset if a drag was in progress
    if (draggedWrestlerId) {
        showWrestlerCardInRoster(draggedWrestlerId);
    }
    handleTouchCleanup();
}

/**
 * Cleans up touch drag state and removes global listeners.
 */
function handleTouchCleanup() {
    removeDragClone();
    draggedWrestlerId = null;
    isDraggingTouch = false;
    currentDropZone = null; // Reset current drop zone
    document.body.removeEventListener('touchmove', handleTouchMove);
    document.body.removeEventListener('touchend', handleTouchEnd);
    document.body.removeEventListener('touchcancel', handleTouchCancel);
}


/**
 * Checks if enough wrestlers are selected for the current match type and toggles the start button.
 */
function checkAndToggleStartButton() {
    const startMatchBtn = document.getElementById('startMatchBtn');
    const calculateOddsBtn = document.getElementById('calculateOddsBtn'); // Get the new button
    let canStart = false;

    console.log("currentMatchType:", currentMatchType);
    console.log("selectedWrestlers state:", selectedWrestlers);

    if (currentMatchType === 'single') {
        canStart = selectedWrestlers.player1 !== null && selectedWrestlers.player2 !== null;
        console.log("Single match check - player1:", selectedWrestlers.player1 ? selectedWrestlers.player1.id : 'null', "player2:", selectedWrestlers.player2 ? selectedWrestlers.player2.id : 'null', "canStart:", canStart);
    } else if (currentMatchType === 'tagTeam') {
        canStart = selectedWrestlers.team1Player1 !== null && selectedWrestlers.team1Player2 !== null &&
                   selectedWrestlers.team2Player1 !== null && selectedWrestlers.team2Player2 !== null;
        console.log("Tag team match check - team1Player1:", selectedWrestlers.team1Player1 ? selectedWrestlers.team1Player1.id : 'null',
                    "team1Player2:", selectedWrestlers.team1Player2 ? selectedWrestlers.team1Player2.id : 'null',
                    "team2Player1:", selectedWrestlers.team2Player1 ? selectedWrestlers.team2Player1.id : 'null',
                    "team2Player2:", selectedWrestlers.team2Player2 ? selectedWrestlers.team2Player2.id : 'null',
                    "canStart:", canStart);
    }

    if (startMatchBtn) {
        if (canStart) {
            startMatchBtn.classList.remove('hidden');
        } else {
            startMatchBtn.classList.add('hidden');
        }
    }
    // Toggle visibility for the new calculate odds button as well
    if (calculateOddsBtn) {
        if (canStart) {
            calculateOddsBtn.classList.remove('hidden');
        } else {
            calculateOddsBtn.classList.add('hidden');
        }
    }
}

/**
 * Resets the entire match state and UI.
 */
function resetMatch() {
    // Clear selected wrestlers
    for (const key in selectedWrestlers) {
        if (selectedWrestlers[key]) {
            showWrestlerCardInRoster(selectedWrestlers[key].id);
            selectedWrestlers[key] = null;
        }
    }
    // Reset UI for drop zones
    resetDropZonesUI(); // This function now handles getting all drop zones
    // Clear match log and result
    updateMatchLog("Match log cleared.", 'info'); // Clear log by passing a single message
    updateMatchResult('Awaiting Match...');

    const matchLogModal = document.getElementById('resultModal');
    if (matchLogModal) {
        hideModal(matchLogModal); // Hide match log modal
    }

    const winnerModal = document.getElementById('winnerModal');
    if (winnerModal) {
        hideModal(winnerModal); // Use hideModal for consistency
    } else {
        console.warn("[resetMatch] Winner modal not found during reset. Cannot hide.");
    }

    const bettingOddsModal = document.getElementById('bettingOddsModal');
    if (bettingOddsModal) {
        hideModal(bettingOddsModal); // Hide betting odds modal
    }

    const nextTurnBtn = document.getElementById('nextTurnBtn');
    if (nextTurnBtn) {
        nextTurnBtn.classList.add('hidden');
    }

    const startMatchBtn = document.getElementById('startMatchBtn');
    if (startMatchBtn) {
        startMatchBtn.classList.add('hidden');
    }
    const calculateOddsBtn = document.getElementById('calculateOddsBtn');
    if (calculateOddsBtn) {
        calculateOddsBtn.classList.add('hidden');
    }

    // Clear and hide all modal display containers
    const modalSingleMatchDisplay = document.getElementById('modalSingleMatchDisplay');
    const modalTagTeamMatchDisplay = document.getElementById('modalTagTeamMatchDisplay');
    const modalTeam1Display = document.getElementById('modalTeam1Display');
    const modalTeam2Display = document.getElementById('modalTeam2Display');

    if (modalSingleMatchDisplay) modalSingleMatchDisplay.classList.add('hidden');
    if (modalTagTeamMatchDisplay) modalTagTeamMatchDisplay.classList.add('hidden');
    if (modalTeam1Display) modalTeam1Display.classList.add('hidden');
    if (modalTeam2Display) modalTeam2Display.classList.add('hidden');

    // Clear and hide individual modal wrestler display zones
    const modalSinglePlayer1 = document.getElementById('modalSinglePlayer1');
    const modalSinglePlayer2 = document.getElementById('modalSinglePlayer2');
    const modalTeam1Player1 = document.getElementById('modalTeam1Player1');
    const modalTeam1Player2 = document.getElementById('modalTeam1Player2');
    const modalTeam2Player1 = document.getElementById('modalTeam2Player1');
    const modalTeam2Player2 = document.getElementById('modalTeam2Player2');

    [modalSinglePlayer1, modalSinglePlayer2, modalTeam1Player1, modalTeam1Player2, modalTeam2Player1, modalTeam2Player2].forEach(el => {
        if (el) {
            el.classList.add('hidden');
            el.innerHTML = ''; // Clear content
        }
    });

    // Reset matchState
    matchState = {
        currentHp: {}, currentStamina: {}, initialHp: {}, initialStamina: {},
        currentRound: 0, matchLogEntries: [], maxRounds: 0, modalDisplayZoneMap: {},
        finisherUsed: {}, // Reset finisher usage
        currentMomentum: {} // Reset momentum
    };

    // Ensure the correct match layout is shown based on currentMatchType
    const singleMatchLayout = document.getElementById('singleMatchLayout');
    const tagTeamMatchLayout = document.getElementById('tagTeamMatchLayout');
    const singleMatchBtn = document.getElementById('singleMatchBtn');
    const tagTeamMatchBtn = document.getElementById('tagTeamMatchBtn');

    toggleMatchLayout(currentMatchType, singleMatchLayout, tagTeamMatchLayout, singleMatchBtn, tagTeamMatchBtn);
    checkAndToggleStartButton(); // Re-evaluate start button visibility
    renderSortedRoster(); // Re-render roster to ensure all cards are visible and sorted
}


/**
 * Starts the match simulation.
 */
function simulateMatch() {
    // Reset matchState for a new match
    matchState = {
        currentHp: {},
        currentStamina: {},
        initialHp: {},
        initialStamina: {},
        currentRound: 0,
        matchLogEntries: [],
        maxRounds: 0,
        modalDisplayZoneMap: {},
        finisherUsed: {}, // Reset finisher usage
        currentMomentum: {} // Reset momentum
    };

    // DOM elements for modal display (fetched inside function for safety)
    const modalSingleMatchDisplay = document.getElementById('modalSingleMatchDisplay');
    const modalTagTeamMatchDisplay = document.getElementById('modalTagTeamMatchDisplay');
    const modalTeam1Display = document.getElementById('modalTeam1Display');
    const modalTeam2Display = document.getElementById('modalTeam2Display');
    const matchLogModal = document.getElementById('resultModal'); // Corrected to matchLogModal
    const nextTurnBtn = document.getElementById('nextTurnBtn');
    const winnerModal = document.getElementById('winnerModal');
    const modalTitle = document.getElementById('modalTitle'); // Assuming this is for the match log modal title


    // Clear and hide all modal display containers initially
    if (modalSingleMatchDisplay) modalSingleMatchDisplay.classList.add('hidden');
    if (modalTagTeamMatchDisplay) modalTagTeamMatchDisplay.classList.add('hidden');
    if (modalTeam1Display) modalTeam1Display.classList.add('hidden');
    if (modalTeam2Display) modalTeam2Display.classList.add('hidden');


    // Clear and hide individual modal wrestler display zones initially
    const modalSinglePlayer1 = document.getElementById('modalSinglePlayer1');
    const modalSinglePlayer2 = document.getElementById('modalSinglePlayer2');
    const modalTeam1Player1 = document.getElementById('modalTeam1Player1');
    const modalTeam1Player2 = document.getElementById('modalTeam1Player2');
    const modalTeam2Player1 = document.getElementById('modalTeam2Player1');
    const modalTeam2Player2 = document.getElementById('modalTeam2Player2');

    [modalSinglePlayer1, modalSinglePlayer2, modalTeam1Player1, modalTeam1Player2, modalTeam2Player1, modalTeam2Player2].forEach(el => {
        if (el) {
            el.classList.add('hidden');
            el.innerHTML = ''; // Clear content
        }
    });

    if (currentMatchType === 'single') {
        const p1 = selectedWrestlers.player1;
        const p2 = selectedWrestlers.player2;

        if (!p1 || !p2) {
            showModal(document.getElementById('resultModal'), 'Match Error', 'Please select two wrestlers for a single match.');
            return;
        }

        // Show single match display container
        if (modalSingleMatchDisplay) modalSingleMatchDisplay.classList.remove('hidden');
        if (modalTitle) modalTitle.textContent = "Single Match Log";


        // Initialize HP and Stamina for single match
        // Effective HP is baseHp + stamina
        matchState.initialHp[p1.id] = p1.baseHp + p1.stats.stamina;
        matchState.currentHp[p1.id] = p1.baseHp + p1.stats.stamina;
        matchState.initialStamina[p1.id] = p1.stats.stamina;
        matchState.currentStamina[p1.id] = p1.stats.stamina;
        matchState.currentMomentum[p1.id] = 50; // Initialize momentum

        matchState.initialHp[p2.id] = p2.baseHp + p2.stats.stamina;
        matchState.currentHp[p2.id] = p2.baseHp + p2.stats.stamina;
        matchState.initialStamina[p2.id] = p2.stats.stamina;
        matchState.currentStamina[p2.id] = p2.stats.stamina;
        matchState.currentMomentum[p2.id] = 50; // Initialize momentum

        // Reduced max rounds for single matches
        matchState.maxRounds = 75;

        // Map wrestler IDs to their modal display zones
        matchState.modalDisplayZoneMap[p1.id] = modalSinglePlayer1;
        matchState.modalDisplayZoneMap[p2.id] = modalSinglePlayer2;

        // Render wrestlers in modal display zones
        renderWrestlerInModalDisplay(modalSinglePlayer1, p1, matchState.currentHp[p1.id], matchState.initialHp[p1.id], matchState.currentStamina[p1.id], matchState.initialStamina[p1.id], matchState.currentMomentum[p1.id]);
        renderWrestlerInModalDisplay(modalSinglePlayer2, p2, matchState.currentHp[p2.id], matchState.initialHp[p2.id], matchState.currentStamina[p2.id], matchState.initialStamina[p2.id], matchState.currentMomentum[p2.id]);

        updateMatchLog(`<h3>--- Single Match: ${p1.name} vs. ${p2.name} ---</h3>`);
        updateMatchLog(`<p>${p1.name} HP: ${matchState.currentHp[p1.id]} | Stamina: ${matchState.currentStamina[p1.id].toFixed(0)} | Momentum: ${matchState.currentMomentum[p1.id].toFixed(0)} | ${p2.name} HP: ${matchState.currentHp[p2.id]} | Stamina: ${matchState.currentStamina[p2.id].toFixed(0)} | Momentum: ${matchState.currentMomentum[p2.id].toFixed(0)}</p>`);

    } else if (currentMatchType === 'tagTeam') {
        const team1_p1 = selectedWrestlers.team1Player1;
        const team1_p2 = selectedWrestlers.team1Player2;
        const team2_p1 = selectedWrestlers.team2Player1;
        const team2_p2 = selectedWrestlers.team2Player2;

        if (!team1_p1 || !team1_p2 || !team2_p1 || !team2_p2) {
            showModal(document.getElementById('resultModal'), 'Match Error', 'Please select four wrestlers for a tag team match.');
            return;
        }

        // Show tag team display container
        if (modalTagTeamMatchDisplay) modalTagTeamMatchDisplay.classList.remove('hidden');
        if (modalTeam1Display) modalTeam1Display.classList.remove('hidden');
        if (modalTeam2Display) modalTeam2Display.classList.remove('hidden');
        if (modalTitle) modalTitle.textContent = "Tag Team Match Log";


        // Initialize HP and Stamina for tag team match
        matchState.initialHp[team1_p1.id] = team1_p1.baseHp + team1_p1.stats.stamina;
        matchState.currentHp[team1_p1.id] = team1_p1.baseHp + team1_p1.stats.stamina;
        matchState.initialStamina[team1_p1.id] = team1_p1.stats.stamina;
        matchState.currentStamina[team1_p1.id] = team1_p1.stats.stamina;
        matchState.currentMomentum[team1_p1.id] = 50; // Initialize momentum

        matchState.initialHp[team1_p2.id] = team1_p2.baseHp + team1_p2.stats.stamina;
        matchState.currentHp[team1_p2.id] = team1_p2.baseHp + team1_p2.stats.stamina;
        matchState.initialStamina[team1_p2.id] = team1_p2.stats.stamina;
        matchState.currentStamina[team1_p2.id] = team1_p2.stats.stamina;
        matchState.currentMomentum[team1_p2.id] = 50; // Initialize momentum

        matchState.initialHp[team2_p1.id] = team2_p1.baseHp + team2_p1.stats.stamina;
        matchState.currentHp[team2_p1.id] = team2_p1.baseHp + team2_p1.stats.stamina;
        matchState.initialStamina[team2_p1.id] = team2_p1.stats.stamina;
        matchState.currentStamina[team2_p1.id] = team2_p1.stats.stamina;
        matchState.currentMomentum[team2_p1.id] = 50; // Initialize momentum

        matchState.initialHp[team2_p2.id] = team2_p2.baseHp + team2_p2.stats.stamina;
        matchState.currentHp[team2_p2.id] = team2_p2.baseHp + team2_p2.stats.stamina;
        matchState.initialStamina[team2_p2.id] = team2_p2.stats.stamina;
        matchState.currentStamina[team2_p2.id] = team2_p2.stats.stamina;
        matchState.currentMomentum[team2_p2.id] = 50; // Initialize momentum

        // Reduced max rounds for tag team matches
        matchState.maxRounds = 125;

        // Map wrestler IDs to their modal display zones
        matchState.modalDisplayZoneMap[team1_p1.id] = document.getElementById('modalTeam1Player1');
        matchState.modalDisplayZoneMap[team1_p2.id] = document.getElementById('modalTeam1Player2');
        matchState.modalDisplayZoneMap[team2_p1.id] = document.getElementById('modalTeam2Player1');
        matchState.modalDisplayZoneMap[team2_p2.id] = document.getElementById('modalTeam2Player2');

        // Render wrestlers in modal display zones
        renderWrestlerInModalDisplay(matchState.modalDisplayZoneMap[team1_p1.id], team1_p1, matchState.currentHp[team1_p1.id], matchState.initialHp[team1_p1.id], matchState.currentStamina[team1_p1.id], matchState.initialStamina[team1_p1.id], matchState.currentMomentum[team1_p1.id]);
        renderWrestlerInModalDisplay(matchState.modalDisplayZoneMap[team1_p2.id], team1_p2, matchState.currentHp[team1_p2.id], matchState.initialHp[team1_p2.id], matchState.currentStamina[team1_p2.id], matchState.initialStamina[team1_p2.id], matchState.currentMomentum[team1_p2.id]);
        renderWrestlerInModalDisplay(matchState.modalDisplayZoneMap[team2_p1.id], team2_p1, matchState.currentHp[team2_p1.id], matchState.initialHp[team2_p1.id], matchState.currentStamina[team2_p1.id], matchState.initialStamina[team2_p1.id], matchState.currentMomentum[team2_p1.id]);
        renderWrestlerInModalDisplay(matchState.modalDisplayZoneMap[team2_p2.id], team2_p2, matchState.currentHp[team2_p2.id], matchState.initialHp[team2_p2.id], matchState.currentStamina[team2_p2.id], matchState.initialStamina[team2_p2.id], matchState.currentMomentum[team2_p2.id]);

        updateMatchLog(`<h3>--- Tag Team Match: ${team1_p1.name} & ${team1_p2.name} vs. ${team2_p1.name} & ${team2_p2.name} ---</h3>`);
        updateMatchLog(`<p>Team 1: ${team1_p1.name} HP: ${matchState.currentHp[team1_p1.id]} | Stamina: ${matchState.currentStamina[team1_p1.id].toFixed(0)} | Momentum: ${matchState.currentMomentum[team1_p1.id].toFixed(0)} | ${team1_p2.name} HP: ${matchState.currentHp[team1_p2.id]} | Stamina: ${matchState.currentStamina[team1_p2.id].toFixed(0)} | Momentum: ${matchState.currentMomentum[team1_p2.id].toFixed(0)}</p>`);
        updateMatchLog(`<p>Team 2: ${team2_p1.name} HP: ${matchState.currentHp[team2_p1.id]} | Stamina: ${matchState.currentStamina[team2_p1.id].toFixed(0)} | Momentum: ${matchState.currentMomentum[team2_p1.id].toFixed(0)} | ${team2_p2.name} HP: ${matchState.currentStamina[team2_p2.id].toFixed(0)} | Stamina: ${matchState.currentStamina[team2_p2.id].toFixed(0)} | Momentum: ${matchState.currentMomentum[team2_p2.id].toFixed(0)}</p>`);
    }

    showModal(matchLogModal, modalTitle ? modalTitle.textContent : '', ''); // Pass the specific modal element
    if (nextTurnBtn) {
        nextTurnBtn.classList.remove('hidden');
    }
    if (winnerModal) {
        hideModal(winnerModal); // Ensure winner modal is hidden
    }
    advanceTurn(); // Start the first turn
}

/**
 * Sorts the loaded wrestlers based on the current sort key and order.
 * @returns {Array<object>} The sorted array of wrestler objects.
 */
function sortWrestlers() {
    const sortedWrestlers = [...loadedWrestlers]; // Create a shallow copy to sort

    sortedWrestlers.sort((a, b) => {
        let valA, valB;

        if (currentSortKey === 'name') {
            valA = a.name.toLowerCase();
            valB = b.name.toLowerCase();
            if (currentSortOrder === 'asc') {
                return valA.localeCompare(valB);
            } else {
                return valB.localeCompare(a.name.toLowerCase()); // Corrected for descending
            }
        } else if (currentSortKey === 'overall') {
            valA = a.overall;
            valB = b.overall;
        } else if (a.stats && b.stats && a.stats[currentSortKey] !== undefined && b.stats[currentSortKey] !== undefined) {
            // For specific stats like strength, stamina, technicalAbility, brawlingAbility, aerialAbility, toughness
            valA = a.stats[currentSortKey];
            valB = b.stats[currentSortKey];
        } else {
            // Fallback for unknown sort keys or missing stats
            return 0; // No change in order if key is invalid or stats are missing
        }

        // Handle numerical sorting
        if (currentSortOrder === 'asc') {
            return valA - valB;
        } else {
            return valB - valA;
        }
    });
    return sortedWrestlers;
}

/**
 * Renders the roster with the currently selected sort options.
 */
function renderSortedRoster() {
    const wrestlerRosterElement = document.getElementById('wrestlerRoster');
    if (!wrestlerRosterElement) {
        console.warn('wrestlerRosterElement not found for rendering sorted roster.');
        return;
    }

    const sorted = sortWrestlers();
    initializeRoster(sorted, wrestlerRosterElement, handleDragStart, handleTouchStart, handleDragEnd);

    // After re-rendering, ensure any already selected wrestlers remain hidden
    for (const key in selectedWrestlers) {
        if (selectedWrestlers[key]) {
            hideWrestlerCardInRoster(selectedWrestlers[key].id);
        }
    }
}

// --- DOMContentLoaded and Event Listener Setup ---
document.addEventListener('DOMContentLoaded', async () => {
    console.log('[DOMContentLoaded] Script execution started.');

    // DOM Elements (fetched once inside DOMContentLoaded)
    const singleMatchBtn = document.getElementById('singleMatchBtn');
    const tagTeamMatchBtn = document.getElementById('tagTeamMatchBtn');
    const randomMatchupBtn = document.getElementById('randomMatchupBtn');
    const simulate100xBtn = document.getElementById('simulate100xBtn');
    const calculateOddsBtn = document.getElementById('calculateOddsBtn'); // New button for odds
    const singleMatchLayout = document.getElementById('singleMatchLayout');
    const tagTeamMatchLayout = document.getElementById('tagTeamMatchLayout');
    const startMatchBtn = document.getElementById('startMatchBtn');
    const matchLogModal = document.getElementById('resultModal');
    const resetMatchBtnModal = document.getElementById('resetMatchBtnModal');
    const resetMatchBtn = document.getElementById('resetMatchBtn'); // This is the main reset button
    const wrestlerRosterElement = document.getElementById('wrestlerRoster');

    // New DOM elements for the turn-based system and winner modal
    const nextTurnBtn = document.getElementById('nextTurnBtn');
    const winnerModal = document.getElementById('winnerModal');
    const resetMatchBtnWinnerModal = document.getElementById('resetMatchBtnWinnerModal');

    // Sort controls - UPDATED TO SELECT MENU
    const sortSelect = document.getElementById('sortSelect'); // Get the new select element
    const toggleSortOrderBtn = document.getElementById('toggleSortOrderBtn');
    const sortOrderText = document.getElementById('sortOrderText');

    // New: Toggle Match Setup button and Match Setup container
    const toggleMatchSetupBtn = document.getElementById('toggleMatchSetupBtn');
    const matchSetup = document.getElementById('matchSetup');


    // Await the resolution of the wrestlers promise from data.js
    loadedWrestlers = await wrestlers;

    if (wrestlerRosterElement) {
        // Initial render of the roster with default sort
        renderSortedRoster();
        // These styles should ideally be in CSS or applied once, but keeping for consistency with original code
        wrestlerRosterElement.style.position = 'relative';
        wrestlerRosterElement.style.zIndex = '10';
        wrestlerRosterElement.style.pointerEvents = 'auto';
    } else {
        console.warn('[DOMContentLoaded] wrestlerRosterElement (ID: "wrestlerRoster") not found. Listener not attached.');
    }

    // Setup drag and drop listeners for drop zones
    setupDragAndDropListeners(handleDragOver, handleDrop);

    // Event listeners for match type selection
    if (singleMatchBtn) {
        singleMatchBtn.addEventListener('click', () => {
            currentMatchType = 'single';
            toggleMatchLayout('single', singleMatchLayout, tagTeamMatchLayout, singleMatchBtn, tagTeamMatchBtn);
            resetMatch(); // Reset to clear drop zones and re-enable all wrestlers
        });
    } else {
        console.warn('[DOMContentLoaded] singleMatchBtn not found. Listener not attached.');
    }

    if (tagTeamMatchBtn) {
        tagTeamMatchBtn.addEventListener('click', () => {
            currentMatchType = 'tagTeam';
            toggleMatchLayout('tagTeam', singleMatchLayout, tagTeamMatchLayout, singleMatchBtn, tagTeamMatchBtn);
            resetMatch(); // Reset to clear drop zones and re-enable all wrestlers
        });
    } else {
        console.warn('[DOMContentLoaded] tagTeamMatchBtn not found. Listener not attached.');
    }

    if (randomMatchupBtn) {
        randomMatchupBtn.addEventListener('click', createRandomMatchup);
    } else {
        console.warn('[DOMContentLoaded] randomMatchupBtn not found. Listener not attached.');
    }

    if (startMatchBtn) {
        startMatchBtn.addEventListener('click', simulateMatch);
    } else {
        // Removed console.warn here as it was preventing listener attachment
    }

    if (simulate100xBtn) {
        simulate100xBtn.addEventListener('click', runMultipleSimulations);
    } else {
        // Removed console.warn here as it was preventing listener attachment
    }

    // New: Event listener for Calculate Betting Odds button
    if (calculateOddsBtn) {
        calculateOddsBtn.addEventListener('click', () => {
            // Pass necessary arguments to calculateBettingOdds
            calculateBettingOdds(selectedWrestlers, currentMatchType, findWrestlerById, {
                currentHp: {}, currentStamina: {}, initialHp: {}, initialStamina: {},
                currentRound: 0, matchLogEntries: [], modalDisplayZoneMap: {},
                finisherUsed: {}, currentMomentum: {}
            });
        });
    } else {
        // Removed console.warn here as it was preventing listener attachment
    }

    // Event listeners for sorting controls
    if (sortSelect) {
        sortSelect.addEventListener('change', (event) => {
            currentSortKey = event.target.value;
            console.log(`[DOMContentLoaded] Sort key changed to: ${currentSortKey}`);
            renderSortedRoster();
        });
    } else {
        console.warn('[DOMContentLoaded] sortSelect not found. Listener not attached.');
    }

    if (toggleSortOrderBtn) {
        toggleSortOrderBtn.addEventListener('click', () => {
            currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
            if (sortOrderText) {
                sortOrderText.textContent = currentSortOrder === 'asc' ? 'Ascending' : 'Descending';
            }
            console.log(`[DOMContentLoaded] Sort order toggled to: ${currentSortOrder}`);
            renderSortedRoster();
        });
    } else {
        console.warn('[DOMContentLoaded] toggleSortOrderBtn not found. Listener not attached.');
    }

    // New: Event listener for toggleMatchSetupBtn
    if (toggleMatchSetupBtn && matchSetup) {
        toggleMatchSetupBtn.addEventListener('click', () => {
            matchSetup.classList.toggle('hidden');
        });
    } else {
        console.warn('[DOMContentLoaded] toggleMatchSetupBtn or matchSetup not found. Listener not attached.');
    }


    // Close button for modals (general handler for all modals with 'close-button' class)
    document.querySelectorAll('.close-button').forEach(button => {
        button.addEventListener('click', (event) => {
            const modalToClose = event.target.closest('.modal');
            if (modalToClose) {
                hideModal(modalToClose);
            }
        });
    });

    if (nextTurnBtn) {
        nextTurnBtn.addEventListener('click', async () => {
            nextTurnBtn.disabled = true;
            await advanceTurn(); // Call with default isBulkSimulation = false
            nextTurnBtn.disabled = false;
        });
    } else {
        console.warn('[DOMContentLoaded] nextTurnBtn not found. Listener not attached.');
    }

    if (resetMatchBtnModal) {
        resetMatchBtnModal.addEventListener('click', resetMatch);
    } else {
        console.warn('[DOMContentLoaded] resetMatchBtnModal not found. Listener not attached.');
    }

    if (resetMatchBtn) { // Main reset button
        resetMatchBtn.addEventListener('click', resetMatch);
    } else {
        // Added a more specific warning for the resetMatchBtn
        console.warn('[DOMContentLoaded] resetMatchBtn (main reset button) not found. Listener not attached. Please ensure this element exists in your HTML.');
    }

    if (resetMatchBtnWinnerModal) {
        resetMatchBtnWinnerModal.addEventListener('click', resetMatch);
    } else {
        console.warn('[DOMContentLoaded] resetMatchBtnWinnerModal not found. Listener not attached.');
    }

    // Initial layout setup
    toggleMatchLayout(currentMatchType, singleMatchLayout, tagTeamMatchLayout, singleMatchBtn, tagTeamMatchBtn);
    checkAndToggleStartButton(); // Re-evaluate start button visibility
});
