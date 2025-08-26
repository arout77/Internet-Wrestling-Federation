document.addEventListener('DOMContentLoaded', () => {
    // Data from Twig
    const rosterData = RosterData || [];

    // DOM Elements
    const rosterContainer = document.getElementById('rosterContainer');
    const player1DropZone = document.getElementById('player1DropZone');
    const player2DropZone = document.getElementById('player2DropZone');
    const startMatchBtn = document.getElementById('startMatchBtn');
    const calculateOddsBtn = document.getElementById('calculateOddsBtn');
    const randomMatchupBtn = document.getElementById('randomMatchupBtn');
    const simulate100xBtn = document.getElementById('simulate100xBtn');
    const resetMatchBtn = document.getElementById('resetMatchBtn');
    const resultsContainer = document.getElementById('resultsContainer');
    const winnerDisplay = document.getElementById('winnerDisplay');
    const matchLog = document.getElementById('matchLog');
    const sortSelect = document.getElementById('sortSelect');
    const sortOrderBtn = document.getElementById('sortOrderBtn');
    const sortOrderIcon = document.getElementById('sortOrderIcon');
    const searchContainer = document.getElementById('searchContainer');
    const searchInput = document.getElementById('searchInput');
    const genericModal = document.getElementById('genericModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const helpBtn = document.getElementById('helpBtn');
    const helpModal = document.getElementById('helpModal');
    const closeHelpModalBtn = document.getElementById('closeHelpModalBtn');

    // State
    let selectedWrestler1 = null;
    let selectedWrestler2 = null;
    let currentSort = { key: 'name', order: 'asc' };

    /**
     * **THE FIX:** Renamed the function and added filtering logic.
     * Filters, sorts, and renders the entire wrestler roster.
     */
    function filterSortAndRenderRoster() {
        const searchTerm = searchInput.value.toLowerCase();

        // 1. Filter the roster based on the search term
        const filteredRoster = rosterData.filter(wrestler => 
            wrestler.name.toLowerCase().includes(searchTerm)
        );

        // 2. Sort the filtered roster
        const sortedRoster = filteredRoster.sort((a, b) => {
            const valA = a[currentSort.key] || 0;
            const valB = b[currentSort.key] || 0;
            if (currentSort.order === 'asc') {
                return typeof valA === 'string' ? valA.localeCompare(valB) : valA - valB;
            } else {
                return typeof valA === 'string' ? valB.localeCompare(valA) : valB - valA;
            }
        });

        // 3. Render the final list
        rosterContainer.innerHTML = sortedRoster.map(renderWrestlerCard).join('');
    }

    // --- (The rest of your code remains the same) ---
    function renderWrestlerCard(wrestler) {
        return `
            <div id="wrestler-card-${wrestler.wrestler_id}" class="wrestler-card rounded-lg shadow-md p-4 text-center transform hover:scale-105 transition-transform duration-200 cursor-pointer border-2 border-indigo-900" data-wrestler-id="${wrestler.wrestler_id}" draggable="true">
                <img src="../public/media/images/${wrestler.image}.webp" alt="${wrestler.name}" class="w-36 h-36 rounded-full mx-auto mb-2 object-cover border-2 border-gray-600 pointer-events-none">
                <h3 class="text-xl font-bold text-yellow-400 mb-2 pointer-events-none">${wrestler.name}</h3>
                <p class="text-lg font-bold text-yellow-200 mb-3 pointer-events-none">Overall: ${wrestler.overall}</p>
                <div class="text-left text-sm text-gray-300 grid grid-cols-2 gap-x-4 pointer-events-none">
                    <div>
                        <div class="flex justify-between"><span><strong>Strength:</strong></span> <span>${wrestler.strength}</span></div>
                        <div class="flex justify-between"><span><strong>Technical:</strong></span> <span>${wrestler.technicalAbility}</span></div>
                        <div class="flex justify-between"><span><strong>Brawling:</strong></span> <span>${wrestler.brawlingAbility}</span></div>
                    </div>
                    <div>
                        <div class="flex justify-between"><span><strong>Aerial:</strong></span> <span>${wrestler.aerialAbility}</span></div>
                        <div class="flex justify-between"><span><strong>Stamina:</strong></span> <span>${wrestler.stamina}</span></div>
                        <div class="flex justify-between"><span><strong>Toughness:</strong></span> <span>${wrestler.toughness}</span></div>
                    </div>
                </div>
            </div>
        `;
    }
    
    function placeWrestler(wrestler) {
        const wrestlerId = wrestler.wrestler_id;
        if ((selectedWrestler1 && selectedWrestler1.wrestler_id == wrestlerId) || (selectedWrestler2 && selectedWrestler2.wrestler_id == wrestlerId)) {
            return; 
        }
        if (!selectedWrestler1) {
            selectedWrestler1 = wrestler;
            renderWrestlerInDropZone(player1DropZone, wrestler);
        } else {
            selectedWrestler2 = wrestler;
            renderWrestlerInDropZone(player2DropZone, wrestler);
        }
        updateActionButtons();
    }

    function renderWrestlerInDropZone(dropZone, wrestler) {
        dropZone.innerHTML = `
            <div class="grid grid-cols-3 gap-4 items-start w-full p-4">
                <div class="col-span-1 text-center">
                    <img src="../public/media/images/${wrestler.image}.webp" alt="${wrestler.name}" class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-yellow-500">
                    <p class="text-white font-bold mt-2">${wrestler.height} / ${wrestler.weight} lbs</p>
                    <p class="text-yellow-300 font-bold">Overall: ${wrestler.overall}</p>
                </div>
                <div class="col-span-2 text-left text-gray-300">
                    <h3 class="text-3xl font-bold text-yellow-300 mb-2">${wrestler.name}</h3>
                    <p class="text-sm h-32 overflow-y-auto pr-2">${wrestler.description}</p>
                </div>
            </div>
        `;
        dropZone.classList.remove('border-dashed', 'items-center', 'justify-center');
        dropZone.classList.add('items-start');
    }
    
    function updateActionButtons() {
        const allSelected = selectedWrestler1 && selectedWrestler2;
        [startMatchBtn, calculateOddsBtn, simulate100xBtn].forEach(btn => {
            if (btn) btn.classList.toggle('hidden', !allSelected);
        });
    }

    function resetMatch() {
        selectedWrestler1 = null;
        selectedWrestler2 = null;
        player1DropZone.innerHTML = 'Drag Wrestler 1 Here';
        player1DropZone.classList.add('border-dashed', 'items-center', 'justify-center');
        player2DropZone.innerHTML = 'Drag Wrestler 2 Here';
        player2DropZone.classList.add('border-dashed', 'items-center', 'justify-center');
        updateActionButtons();
        resultsContainer.classList.add('hidden');
    }

    function createRandomMatchup() {
        resetMatch();
        let rosterCopy = [...rosterData];
        const index1 = Math.floor(Math.random() * rosterCopy.length);
        selectedWrestler1 = rosterCopy.splice(index1, 1)[0];
        const index2 = Math.floor(Math.random() * rosterCopy.length);
        selectedWrestler2 = rosterCopy[index2];
        
        renderWrestlerInDropZone(player1DropZone, selectedWrestler1);
        renderWrestlerInDropZone(player2DropZone, selectedWrestler2);
        updateActionButtons();
    }

    async function runSimulation() {
        startMatchBtn.disabled = true;
        startMatchBtn.textContent = 'Simulating...';
        resultsContainer.classList.remove('hidden');
        winnerDisplay.innerHTML = '';
        matchLog.innerHTML = '<p class="text-center text-gray-400">The simulation is running...</p>';

        const formData = new FormData();
        formData.append('wrestler1_id', selectedWrestler1.wrestler_id);
        formData.append('wrestler2_id', selectedWrestler2.wrestler_id);

        try {
            const response = await fetch('http://localhost/iwf-betting/api/run_simulation', { method: 'POST', body: formData });
            const result = await response.json();
            if (response.ok) displayResults(result);
            else matchLog.innerHTML = `<p class="text-red-500 text-center">Error: ${result.error || 'Unknown error'}</p>`;
        } catch (error) {
            matchLog.innerHTML = `<p class="text-red-500 text-center">${error}</p>`;
        }
        startMatchBtn.disabled = false;
        startMatchBtn.textContent = 'Simulate Match';
    }

    function displayResults(result) {
        winnerDisplay.innerHTML = result.winner && result.winner.name
            ? `<h3 class="text-xl text-white">The winner is...</h3><p class="text-4xl font-bold text-green-400">${result.winner.name}!</p>`
            : `<p class="text-4xl font-bold text-gray-400">The match is a Draw!</p>`;
        
        matchLog.innerHTML = result.log.map(entry => `<p class="mb-1 text-gray-300">${entry}</p>`).join('');
        matchLog.scrollTop = 0;
    }
    
    async function handleBulkSim(simCount) {
        modalTitle.textContent = `Simulating ${simCount} Matches...`;
        modalBody.innerHTML = '<p class="text-center">Please wait, this may take a moment.</p>';
        genericModal.classList.remove('hidden');

        const formData = new FormData();
        formData.append('wrestler1_id', selectedWrestler1.wrestler_id);
        formData.append('wrestler2_id', selectedWrestler2.wrestler_id);
        formData.append('sim_count', simCount);

        try {
            const response = await fetch('http://localhost/iwf-betting/api/run_bulk_simulation', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (response.ok) {
                let html = '<div class="space-y-3">';
                for (const winnerName in result.wins) {
                    if (winnerName.toLowerCase() === 'draw') {
                        const percentage = ((result.wins[winnerName] / simCount) * 100).toFixed(1);
                        html += `
                            <div class="grid grid-cols-2 items-center text-lg">
                                <span class="font-semibold">${winnerName}:</span>
                                <span class="text-right">${result.wins[winnerName]} outcomes (${percentage}%)</span>
                            </div>`;
                        continue;
                    }

                    // **THE FIX:** Convert moneyline to a number before calculation.
                    const percentage = ((result.wins[winnerName] / simCount) * 100).toFixed(1);
                    const moneyline = parseFloat(result.moneyline[winnerName]);
                    let totalReturn = 0;

                    if (moneyline > 0) {
                        totalReturn = 100 + moneyline;
                    } else {
                        const winAmount = (100 / Math.abs(moneyline)) * 100;
                        totalReturn = 100 + winAmount;
                    }
                    
                    html += `
                        <div class="bg-gray-700 p-3 rounded-lg">
                            <h3 class="text-xl font-bold text-white">${winnerName}</h3>
                            <div class="flex justify-between items-center text-gray-300 mt-2">
                                <span>Win Count:</span>
                                <span class="font-semibold">${result.wins[winnerName]} of ${simCount} (${percentage}%)</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-300 mt-1">
                                <span>Moneyline Odds:</span>
                                <span class="font-bold text-lg ${moneyline > 0 ? 'text-green-400' : 'text-red-400'}">${moneyline > 0 ? '+' : ''}${moneyline}</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-300 mt-1 pt-2 border-t border-gray-600">
                                <span>A 100 Gold Bet On ${winnerName} Pays Out:</span>
                                <span class="font-bold text-green-400">${new Intl.NumberFormat('en-US').format(totalReturn.toFixed(0))} Gold</span>
                            </div>
                        </div>
                    `;
                }
                html += '</div>';

                modalTitle.textContent = `Betting Odds (${simCount} Simulations)`;
                modalBody.innerHTML = html;
            } else {
                modalBody.innerHTML = `<p class="text-red-500 text-center">Error: ${result.error || 'Unknown error'}</p>`;
            }
        } catch (error) {
            modalBody.innerHTML = `<p class="text-red-500 text-center">A network error occurred.</p>`;
            console.error("Error during bulk sim:", error);
        }
    }

    // --- Event Listeners ---
    rosterContainer.addEventListener('click', (e) => {
        const card = e.target.closest('[data-wrestler-id]');
        if (!card) return;
        const wrestlerId = card.dataset.wrestlerId;
        const wrestler = rosterData.find(w => w.wrestler_id == wrestlerId);
        if (wrestler) placeWrestler(wrestler);
    });

    rosterContainer.addEventListener('dragstart', (e) => {
        const card = e.target.closest('[data-wrestler-id]');
        if (card) e.dataTransfer.setData('text/plain', card.dataset.wrestlerId);
    });

    [player1DropZone, player2DropZone].forEach(zone => {
        zone.addEventListener('dragover', (e) => e.preventDefault());
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            const wrestlerId = e.dataTransfer.getData('text/plain');
            const wrestler = rosterData.find(w => w.wrestler_id == wrestlerId);
            if (wrestler) {
                if (zone.id === 'player1DropZone') {
                    if (selectedWrestler2 && selectedWrestler2.wrestler_id == wrestlerId) return;
                    selectedWrestler1 = wrestler;
                } else {
                    if (selectedWrestler1 && selectedWrestler1.wrestler_id == wrestlerId) return;
                    selectedWrestler2 = wrestler;
                }
                renderWrestlerInDropZone(zone, wrestler);
                updateActionButtons();
            }
        });
    });

    sortSelect.addEventListener('change', () => {
        currentSort.key = sortSelect.value;
        filterSortAndRenderRoster();
    });

    sortOrderBtn.addEventListener('click', () => {
        currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
        sortOrderIcon.textContent = currentSort.order === 'asc' ? '↓' : '↑';
        filterSortAndRenderRoster();
    });

    searchContainer.addEventListener('click', () => {
        searchContainer.classList.add('expanded');
        searchInput.focus();
    });

    searchInput.addEventListener('blur', () => {
        if (!searchInput.value) searchContainer.classList.remove('expanded');
    });

    searchInput.addEventListener('input', filterSortAndRenderRoster);
    
    startMatchBtn.addEventListener('click', runSimulation);
    resetMatchBtn.addEventListener('click', resetMatch);
    randomMatchupBtn.addEventListener('click', createRandomMatchup);
    
    calculateOddsBtn.addEventListener('click', () => handleBulkSim(1000));
    simulate100xBtn.addEventListener('click', () => handleBulkSim(100));
    closeModalBtn.addEventListener('click', () => genericModal.classList.add('hidden'));

    helpBtn.addEventListener('click', () => helpModal.classList.remove('hidden'));
    closeHelpModalBtn.addEventListener('click', () => helpModal.classList.add('hidden'));

    // --- Initial Page Load ---
    filterSortAndRenderRoster();
});