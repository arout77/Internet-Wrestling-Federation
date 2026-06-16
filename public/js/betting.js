import { showNotification } from './ui-updater.js';
import { runBulkSimulation } from './data.js';
import { showModal, hideModal } from './dom.js';

document.addEventListener('DOMContentLoaded', () => {
    const bettingModal = document.getElementById('betting-modal');
    const openBettingModalBtn = document.getElementById('open-betting-modal-btn');
    const closeBettingModalBtn = document.getElementById('close-betting-modal-btn');
    const runBulkSimBtn = document.getElementById('run-bulk-sim-btn');

    if (openBettingModalBtn) {
        openBettingModalBtn.addEventListener('click', () => {
            // This global `selectedWrestlers` is assumed to be managed by simulator.js
            // This is a bit of a code smell, but works for now.
            if (typeof selectedWrestlers !== 'undefined' && selectedWrestlers.length === 2) {
                document.getElementById('betting-wrestler1-name').textContent = selectedWrestlers[0].name;
                document.getElementById('betting-wrestler2-name').textContent = selectedWrestlers[1].name;
                showModal('betting-modal');
            } else {
                showNotification('Please select two wrestlers to open the betting simulator.', 'error');
            }
        });
    }

    if (closeBettingModalBtn) {
        closeBettingModalBtn.addEventListener('click', () => {
            hideModal('betting-modal');
        });
    }

    if (runBulkSimBtn) {
        runBulkSimBtn.addEventListener('click', async () => {
            const numSimulations = document.getElementById('num-simulations').value;
             if (typeof selectedWrestlers !== 'undefined' && selectedWrestlers.length === 2) {
                showNotification(`Running ${numSimulations} simulations...`, 'info');
                hideModal('betting-modal');
                // renderBulkResults is also a global function from dom.js
                const results = await runBulkSimulation(selectedWrestlers[0].wrestler_id, selectedWrestlers[1].wrestler_id, numSimulations);
                if (results && typeof renderBulkResults !== 'undefined') {
                    renderBulkResults(results);
                } else {
                    showNotification('Failed to run bulk simulation.', 'error');
                }
            } else {
                showNotification('Please select two wrestlers first.', 'error');
            }
        });
    }
});

