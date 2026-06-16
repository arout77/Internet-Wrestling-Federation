import { showNotification } from './ui-updater.js';
import { fetchWrestlerData, fetchMovesData, fetchTagTeamData, runSimulation, runBulkSimulation } from './data.js';
import { createWrestlerCard, updateMatchCard, renderBulkResults, clearWrestlerSelection, createTagTeamCard, renderTagTeamRoster, renderRoster, setupSimulationModal, updateHealthBar, updateStaminaBar, updateMomentumBar, shakeWrestlerImage, showWinnerModal } from './dom.js';

let allWrestlers = [];
let allMoves = [];
let allTagTeams = [];
let selectedWrestlers = [];

// ... (filterSortAndRenderRoster and selectWrestler functions are the same)
function filterSortAndRenderRoster() {
    const filterTextEl = document.getElementById('roster-filter');
    const sortValueEl = document.getElementById('roster-sort');
    if (!filterTextEl || !sortValueEl) return;
    const filterText = filterTextEl.value.toLowerCase();
    const sortValue = sortValueEl.value;
    let filteredWrestlers = allWrestlers.filter(w => w.name.toLowerCase().includes(filterText));
    filteredWrestlers.sort((a, b) => {
        switch (sortValue) {
            case 'name_asc': return a.name.localeCompare(b.name);
            case 'name_desc': return b.name.localeCompare(a.name);
            case 'overall_desc': return (b.overall || 0) - (a.overall || 0);
            case 'strength_desc': return (b.strength || 0) - (a.strength || 0);
            case 'technical_desc': return (b.technicalAbility || 0) - (a.technicalAbility || 0);
            case 'brawling_desc': return (b.brawlingAbility || 0) - (a.brawlingAbility || 0);
            case 'aerial_desc': return (b.aerialAbility || 0) - (a.aerialAbility || 0);
            case 'stamina_desc': return (b.stamina || 0) - (a.stamina || 0);
            case 'toughness_desc': return (b.toughness || 0) - (a.toughness || 0);
            default: return 0;
        }
    });
    renderRoster(filteredWrestlers);
}
function selectWrestler(wrestler) {
    if (selectedWrestlers.length < 2) {
        if (selectedWrestlers.some(w => w.wrestler_id === wrestler.wrestler_id)) {
            showNotification('This wrestler is already selected.', 'error');
            return;
        }
        selectedWrestlers.push(wrestler);
        updateMatchCard(wrestler, selectedWrestlers.length - 1);
    } else {
        showNotification('Both wrestler slots are full. Please reset to select new wrestlers.', 'error');
    }
}


document.addEventListener('DOMContentLoaded', async () => {
    // ... (Initial Data Loading and most Event Listeners are the same)
    try {
        [allWrestlers, allMoves, allTagTeams] = await Promise.all([ fetchWrestlerData(), fetchMovesData(), fetchTagTeamData() ]);
        if (allWrestlers) { filterSortAndRenderRoster(); }
        if (allTagTeams && allTagTeams.teams) { renderTagTeamRoster(allTagTeams.teams, allWrestlers); }
    } catch (error) {
        console.error("Failed to load initial application data:", error);
        showNotification("Could not load necessary game data. Please refresh.", "error");
    }
    const rosterFilter = document.getElementById('roster-filter');
    if (rosterFilter) { rosterFilter.addEventListener('input', filterSortAndRenderRoster); }
    const rosterSort = document.getElementById('roster-sort');
    if (rosterSort) { rosterSort.addEventListener('change', filterSortAndRenderRoster); }
    const rosterContainer = document.getElementById('roster-container');
    if (rosterContainer) {
        rosterContainer.addEventListener('click', (e) => {
            const card = e.target.closest('.wrestler-card');
            if (card) {
                const wrestlerId = parseInt(card.dataset.wrestlerId, 10);
                const wrestler = allWrestlers.find(w => w.wrestler_id == wrestlerId);
                if (wrestler) { selectWrestler(wrestler); }
            }
        });
    }
    const runSimBtn = document.getElementById('simulate-match-btn') || document.getElementById('run-sim-btn');
    if (runSimBtn) {
        runSimBtn.addEventListener('click', async () => {
            if (selectedWrestlers.length === 2) {
                const simModal = document.getElementById('simulation-modal');
                const simLog = document.getElementById('simulation-log');
                if (!simModal || !simLog) { console.error('Simulation modal elements not found!'); return; }
                setupSimulationModal(selectedWrestlers[0], selectedWrestlers[1]);
                simLog.textContent = 'Simulating match...';
                simModal.classList.remove('hidden');
                const result = await runSimulation(selectedWrestlers[0].wrestler_id, selectedWrestlers[1].wrestler_id);
                if (result && result.success) {
                    simLog.textContent = ''; 
                    let logText = '';
                    const processTurn = (index, onComplete) => {
                        if (index >= result.log.length) {
                            if (onComplete) onComplete();
                            return;
                        }
                        const turn = result.log[index];
                        
                        logText += turn.message + '\n';
                        simLog.textContent = logText;
                        simLog.scrollTop = simLog.scrollHeight;
                        if (turn.type === 'damage' && turn.defender_hp_percent !== undefined) {
                            const defenderId = parseInt(turn.defender_id, 10);
                            const attackerId = parseInt(turn.attacker_id, 10);
                            const defenderIndex = (defenderId == selectedWrestlers[0].wrestler_id) ? 1 : 2;
                            updateHealthBar(defenderIndex, turn.defender_hp_percent);
                            shakeWrestlerImage(defenderIndex);
                            const attackerIndex = (attackerId == selectedWrestlers[0].wrestler_id) ? 1 : 2;
                            updateStaminaBar(attackerIndex, turn.attacker_stamina_percent);
                            
                            // Check if momentum data exists before trying to update the bar
                            if (turn.attacker_momentum_percent !== undefined) {
                                updateMomentumBar(attackerIndex, turn.attacker_momentum_percent);
                            }
                        }
                        setTimeout(() => processTurn(index + 1, onComplete), 200);
                    };
                    processTurn(0, () => {
                        const winnerId = result.winner_id;
                        const victoryMethod = result.victoryMethod; 
                        const winner = allWrestlers.find(w => w.wrestler_id == winnerId);
                        if (winner) {
                            showWinnerModal(winner, victoryMethod);
                        } else if (winnerId === null) {
                            showNotification('The match ended in a draw!', 'info');
                            setTimeout(() => simModal.classList.add('hidden'), 2000);
                        }
                    });
                } else {
                    simLog.textContent = `Simulation failed: ${result ? result.error : 'Unknown error'}`;
                }
            } else {
                showNotification('Please select two wrestlers to simulate a match.', 'error');
            }
        });
    }
    const resetMatchBtn = document.getElementById('reset-match-btn');
    if (resetMatchBtn) {
        resetMatchBtn.addEventListener('click', () => {
            selectedWrestlers = [];
            clearWrestlerSelection();
            const bulkResults = document.getElementById('bulk-results-container');
            if (bulkResults) { bulkResults.classList.add('hidden'); }
            showNotification('Match reset.', 'info');
        });
    }
    const closeWinnerModalBtn = document.getElementById('close-winner-modal-btn');
    if (closeWinnerModalBtn) {
        closeWinnerModalBtn.addEventListener('click', () => {
            const winnerModal = document.getElementById('winner-modal');
            if (winnerModal) { winnerModal.classList.add('hidden'); }
        });
    }
    const randomMatchupBtn = document.getElementById('random-matchup-btn');
    if (randomMatchupBtn) {
        randomMatchupBtn.addEventListener('click', () => {
            if (allWrestlers.length < 2) {
                showNotification('Not enough wrestlers loaded to create a random match.', 'error');
                return;
            }

            // Clear any previous selections
            selectedWrestlers = [];
            
            // Pick two different random indexes
            let index1 = Math.floor(Math.random() * allWrestlers.length);
            let index2 = Math.floor(Math.random() * allWrestlers.length);
            while (index1 === index2) {
                index2 = Math.floor(Math.random() * allWrestlers.length);
            }

            // Get the wrestlers and add them to the selected list
            const wrestler1 = allWrestlers[index1];
            const wrestler2 = allWrestlers[index2];
            
            selectedWrestlers.push(wrestler1, wrestler2);

            // Update the UI
            updateMatchCard(wrestler1, 0);
            updateMatchCard(wrestler2, 1);
            showNotification('Random matchup generated!', 'info');
        });
    }
    const openBettingModalBtn = document.getElementById('open-betting-modal-btn');
    if (openBettingModalBtn) {
        openBettingModalBtn.addEventListener('click', () => {
            if (selectedWrestlers.length === 2) {
                document.getElementById('betting-wrestler1-name').textContent = selectedWrestlers[0].name;
                document.getElementById('betting-wrestler2-name').textContent = selectedWrestlers[1].name;
                document.getElementById('betting-modal').classList.remove('hidden');
            } else {
                showNotification('Please select two wrestlers before calculating odds.', 'error');
            }
        });
    }
    const closeBettingModalBtn = document.getElementById('close-betting-modal-btn');
    if (closeBettingModalBtn) {
        closeBettingModalBtn.addEventListener('click', () => {
            document.getElementById('betting-modal').classList.add('hidden');
        });
    }
    const runBulkSimBtn = document.getElementById('run-bulk-sim-btn');
    if (runBulkSimBtn) {
        runBulkSimBtn.addEventListener('click', async () => {
            const numSimulations = document.getElementById('num-simulations').value;
            const btn = runBulkSimBtn;
            document.getElementById('betting-modal').classList.add('hidden');
            btn.disabled = true;
            btn.textContent = 'Calculating...';
            showNotification('Calculating odds, please wait...', 'info');
            const results = await runBulkSimulation(selectedWrestlers[0].wrestler_id, selectedWrestlers[1].wrestler_id, numSimulations);
            btn.disabled = false;
            btn.textContent = 'Calculate Odds';
            if (results) { 
                renderBulkResults(results, selectedWrestlers);
            } else {
                showNotification('There was an error calculating the odds. Please try again.', 'error');
            }
        });
    }
});