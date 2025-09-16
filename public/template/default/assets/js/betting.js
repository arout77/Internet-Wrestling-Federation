// betting.js

import { showModal, hideModal } from './dom.js';
import { getRandomInt } from './data.js';
import { probabilityToDecimalOdds, probabilityToFractionalOdds, probabilityToMoneylineOdds, determineWinner } from './utils.js';

export const NUM_ODDS_SIMULATIONS = 1000; // Number of simulations for betting odds calculation

/**
 * Runs a large number of simulations via the backend to calculate betting odds.
 * @param {Object} selectedWrestlers - The currently selected wrestlers for the match.
 * @param {string} currentMatchType - The current match type ('single' or 'tagTeam').
 * @param {Array<Object>} allWrestlers - The full array of all wrestler data.
 * @param {Object} initialMatchStateTemplate - A template for the matchState.
 */
export async function calculateBettingOdds(selectedWrestlers, currentMatchType, allWrestlers, initialMatchStateTemplate) {
    const bettingOddsModal = document.getElementById('bettingOddsModal');
    const bettingOddsModalMessage = document.getElementById('bettingOddsModalMessage');
    const oddsResultsContainer = document.getElementById('oddsResultsContainer');

    // Basic validation to ensure wrestlers are selected
    if ((currentMatchType === 'single' && (!selectedWrestlers.player1 || !selectedWrestlers.player2)) ||
        (currentMatchType === 'tagTeam' && (!selectedWrestlers.team1Player1 || !selectedWrestlers.team1Player2 || !selectedWrestlers.team2Player1 || !selectedWrestlers.team2Player2))) {
        
        showModal(bettingOddsModal, "Cannot Calculate Odds", "Please ensure all wrestlers are selected.");
        return;
    }

    // 1. Show a "calculating" message in the modal.
    if (bettingOddsModalMessage) {
        bettingOddsModalMessage.textContent = `Running ${NUM_ODDS_SIMULATIONS} simulations on the server...`;
    }
    if (oddsResultsContainer) {
        oddsResultsContainer.innerHTML = '<div class="text-center text-gray-400">Calculating...</div>'; 
    }
    showModal(bettingOddsModal, "Calculating Odds", `Please wait while we simulate the match ${NUM_ODDS_SIMULATIONS} times.`);

    // 2. Prepare the payload for the backend
    const payload = {
        selectedWrestlers,
        currentMatchType,
        allWrestlers,
        initialMatchStateTemplate,
        numSimulations: NUM_ODDS_SIMULATIONS
    };

    try {
        // 3. Call the new backend endpoint that handles all simulations
        const response = await fetch(baseUrl + '/api/simulate_odds', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Failed to calculate odds: ${response.status} - ${errorText}`);
        }

        const result = await response.json();

        if (result.error) {
            throw new Error(`Backend error: ${result.error}`);
        }
        
        // 4. Render the results returned from the backend
        if (bettingOddsModalMessage) {
             bettingOddsModalMessage.textContent = `Odds based on ${NUM_ODDS_SIMULATIONS} simulations.`;
        }
        renderOddsResults(result.probabilities, currentMatchType, selectedWrestlers);

    } catch (error) {
        console.error("Error calculating betting odds:", error);
        if (oddsResultsContainer) {
            oddsResultsContainer.innerHTML = `<div class="text-center text-red-400">Error: ${error.message}</div>`;
        }
    }
}

/**
 * Renders the calculated betting odds to the DOM.
 * @param {object} probabilities - The probabilities of each outcome.
 * @param {string} currentMatchType - The current match type.
 * @param {object} selectedWrestlers - The wrestlers for the match.
 */
export function renderOddsResults(probabilities, currentMatchType, selectedWrestlers) {
    const oddsResultsContainer = document.getElementById('oddsResultsContainer');
    if (!oddsResultsContainer) {
        console.error("[renderOddsResults] Element with ID 'oddsResultsContainer' not found.");
        return;
    }
    // Clear the "Calculating..." message
    oddsResultsContainer.innerHTML = '';

    const updateOddsDisplay = (id, name, probability) => {
        // Create the container for this odds display
        const oddsContainer = document.createElement('div');
        oddsContainer.id = `odds${id}`;
        oddsContainer.className = 'bg-gray-700 p-4 rounded-lg shadow-inner';

        // Check if probability is valid, otherwise show N/A
        const isValidProbability = typeof probability === 'number' && !isNaN(probability);

        oddsContainer.innerHTML = `
            <h3 id="odds${id}Name" class="text-xl font-bold text-yellow-400 mb-2">${name}</h3>
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-left">
                <p class="font-semibold text-gray-300">Win Probability:</p>
                <p id="odds${id}Probability" class="text-white">${isValidProbability ? (probability * 100).toFixed(2) + '%' : 'N/A'}</p>
                
                <p class="font-semibold text-gray-300">Decimal Odds:</p>
                <p id="odds${id}Decimal" class="text-white">${isValidProbability ? probabilityToDecimalOdds(probability) : 'N/A'}</p>
                
                <p class="font-semibold text-gray-300">Fractional Odds:</p>
                <p id="odds${id}Fractional" class="text-white">${isValidProbability ? probabilityToFractionalOdds(probability) : 'N/A'}</p>
                
                <p class="font-semibold text-gray-300">Moneyline Odds:</p>
                <p id="odds${id}Moneyline" class="text-white">${isValidProbability ? probabilityToMoneylineOdds(probability) : 'N/A'}</p>
            </div>
            <p id="odds${id}MoneylineNote" class="text-xs text-gray-400 mt-2"></p>
        `;
        
        oddsResultsContainer.appendChild(oddsContainer);

        // Now that the elements are in the DOM, we can update the moneyline note
        const moneylineNoteElement = document.getElementById(`odds${id}MoneylineNote`);
        if (moneylineNoteElement && isValidProbability) {
            const moneyline = probabilityToMoneylineOdds(probability);
            if (moneyline.startsWith('+')) {
                const payout = parseInt(moneyline.substring(1));
                moneylineNoteElement.textContent = `A $100 bet wins you $${payout.toFixed(2)}.`;
            } else if (moneyline.startsWith('-')) {
                const betAmount = Math.abs(parseInt(moneyline));
                 moneylineNoteElement.textContent = `You must bet $${betAmount.toFixed(2)} to win $100.`;
            } else {
                moneylineNoteElement.textContent = ''; 
            }
        }
    };

    if (currentMatchType === 'single') {
        const p1 = selectedWrestlers.player1;
        const p2 = selectedWrestlers.player2;

        if (p1) updateOddsDisplay('Player1', p1.name, probabilities[p1.name]);
        if (p2) updateOddsDisplay('Player2', p2.name, probabilities[p2.name]);
        updateOddsDisplay('Draw', 'Draw', probabilities['draw']);

    } else if (currentMatchType === 'tagTeam') {
        const team1Name = `${selectedWrestlers.team1Player1.name} & ${selectedWrestlers.team1Player2.name}`;
        const team2Name = `${selectedWrestlers.team2Player1.name} & ${selectedWrestlers.team2Player2.name}`;

        updateOddsDisplay('Player1', `Team 1 (${team1Name})`, probabilities['Team 1']);
        updateOddsDisplay('Player2', `Team 2 (${team2Name})`, probabilities['Team 2']);
        updateOddsDisplay('Draw', 'Draw', probabilities['draw']);
    }
}
