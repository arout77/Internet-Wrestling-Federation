document.addEventListener('DOMContentLoaded', () => {
    // Data from Twig
    const rosterData = RosterData || [];

    // DOM Elements
    const rosterContainer = document.getElementById('rosterContainer');
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
    
    // Match Type Elements
    const singleMatchBtn = document.getElementById('singleMatchBtn');
    const tagTeamMatchBtn = document.getElementById('tagTeamMatchBtn');
    const singleMatchLayout = document.getElementById('singleMatchLayout');
    const tagTeamMatchLayout = document.getElementById('tagTeamMatchLayout');

    // Drop Zones
    const dropZones = {
        player1DropZone: document.getElementById('player1DropZone'),
        player2DropZone: document.getElementById('player2DropZone'),
        team1_player1DropZone: document.getElementById('team1_player1DropZone'),
        team1_player2DropZone: document.getElementById('team1_player2DropZone'),
        team2_player1DropZone: document.getElementById('team2_player1DropZone'),
        team2_player2DropZone: document.getElementById('team2_player2DropZone')
    };
    
    // State
    let selectedWrestlers = {
        player1: null,
        player2: null,
        team1_player1: null,
        team1_player2: null,
        team2_player1: null,
        team2_player2: null
    };
    let matchType = 'single'; // 'single' or 'tag'
    let currentSort = { key: 'name', order: 'asc' };

    // --- INITIALIZATION ---
    
    filterSortAndRenderRoster();
    setupEventListeners();

    // --- EVENT LISTENERS SETUP ---
    
    function setupEventListeners() {
        // Match Type Selection
        singleMatchBtn.addEventListener('click', () => switchMatchType('single'));
        tagTeamMatchBtn.addEventListener('click', () => switchMatchType('tag'));

        // Action Buttons
        startMatchBtn.addEventListener('click', runSimulation);
        resetMatchBtn.addEventListener('click', resetMatch);
        randomMatchupBtn.addEventListener('click', createRandomMatchup);
        calculateOddsBtn.addEventListener('click', () => handleBulkSim(1000));
        simulate100xBtn.addEventListener('click', () => handleBulkSim(100));

        // Roster Interaction
        rosterContainer.addEventListener('click', handleRosterClick);
        rosterContainer.addEventListener('dragstart', handleRosterDragStart);
        
        // Drop Zones
        Object.keys(dropZones).forEach(key => {
            if (dropZones[key]) {
                setupDropZone(dropZones[key], key.replace('DropZone', ''));
            }
        });

        // Sorting and Searching
        sortSelect.addEventListener('change', handleSortChange);
        sortOrderBtn.addEventListener('click', handleSortOrderToggle);
        searchInput.addEventListener('input', filterSortAndRenderRoster);
        searchInput.addEventListener('click', () => searchInput.classList.add('ml-8'));
        searchInput.addEventListener('blur', () => searchInput.classList.remove('ml-8'));
        searchContainer.addEventListener('click', () => searchContainer.classList.add('expanded'));
        searchInput.addEventListener('blur', () => { if (!searchInput.value) searchContainer.classList.remove('expanded'); });

        // Modals
        closeModalBtn.addEventListener('click', () => genericModal.classList.add('hidden'));
        helpBtn.addEventListener('click', () => helpModal.classList.remove('hidden'));
        closeHelpModalBtn.addEventListener('click', () => helpModal.classList.add('hidden'));
    }

    // --- CORE FUNCTIONS ---

    function switchMatchType(type) {
        matchType = type;
        if (type === 'single') {
            singleMatchLayout.classList.remove('hidden');
            tagTeamMatchLayout.classList.add('hidden');
            singleMatchBtn.classList.replace('bg-gray-700', 'bg-yellow-500');
            tagTeamMatchBtn.classList.replace('bg-yellow-500', 'bg-gray-700');
        } else {
            singleMatchLayout.classList.add('hidden');
            tagTeamMatchLayout.classList.remove('hidden');
            tagTeamMatchBtn.classList.replace('bg-gray-700', 'bg-yellow-500');
            singleMatchBtn.classList.replace('bg-yellow-500', 'bg-gray-700');
        }
        resetMatch(); // Reset selections when switching modes
    }
    
    function filterSortAndRenderRoster() {
        const searchTerm = searchInput.value.toLowerCase();
        const filtered = rosterData.filter(w => w.name.toLowerCase().includes(searchTerm));
        
        const sorted = filtered.sort((a, b) => {
            const valA = a[currentSort.key] || 0;
            const valB = b[currentSort.key] || 0;
            if (currentSort.order === 'asc') {
                return typeof valA === 'string' ? valA.localeCompare(valB) : valA - valB;
            } else {
                return typeof valA === 'string' ? valB.localeCompare(valA) : valB - valA;
            }
        });

        rosterContainer.innerHTML = sorted.map(renderWrestlerCard).join('');
    }

    function renderWrestlerCard(wrestler) {
        // NEW: Generate HTML for traits if they exist
        let traitsHtml = '';
        if (wrestler.traits && wrestler.traits.length > 0) {
            const traitBadges = wrestler.traits.map(trait => 
                `<span class="bg-indigo-500 text-white text-xs font-semibold mr-2 px-2.5 py-0.5 rounded">${trait}</span>`
            ).join('');
            traitsHtml = `<div class="mt-2 mb-2">${traitBadges}</div>`;
        }

        return `
            <div id="wrestler-card-${wrestler.wrestler_id}" class="wrestler-card rounded-lg shadow-md p-4 text-center transform hover:scale-105 transition-transform duration-200 cursor-pointer border-2 border-indigo-900" data-wrestler-id="${wrestler.wrestler_id}" draggable="true">
                <img src="../public/media/images/${wrestler.image}.webp" alt="${wrestler.name}" class="w-36 h-36 rounded-full mx-auto mb-2 object-cover border-2 border-gray-600 pointer-events-none">
                <h3 class="text-xl font-bold text-yellow-400 mb-2 pointer-events-none">${wrestler.name}</h3>
                ${traitsHtml} <p class="text-lg font-bold text-yellow-200 mb-3 pointer-events-none">Overall: ${wrestler.overall}</p>
                <div class="text-left text-sm text-gray-300 grid grid-cols-1 lg:grid-cols-2 gap-x-1 lg:gap-x-10 pointer-events-none">
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

    function addWrestlerToSlot(wrestler, slotKey) {
        // Check if wrestler is already selected in any slot
        for (const key in selectedWrestlers) {
            if (selectedWrestlers[key] && selectedWrestlers[key].wrestler_id === wrestler.wrestler_id) {
                return; // Don't add if already present
            }
        }
        
        selectedWrestlers[slotKey] = wrestler;
        const dropZone = dropZones[slotKey + 'DropZone'];
        renderWrestlerInDropZone(dropZone, wrestler);
        updateActionButtons();
    }
    
    function renderWrestlerInDropZone(dropZone, wrestler) {
        // Using the detailed display from your original code
        dropZone.innerHTML = `
            <div class="grid grid-cols-3 gap-4 items-center w-full p-4">
                <div class="col-span-1 text-center">
                    <img src="../public/media/images/${wrestler.image}.webp" alt="${wrestler.name}" class="w-48 h-48 rounded-full mx-auto object-cover border-0 border-yellow-500">
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
        let isReady = false;
        if (matchType === 'single') {
            isReady = selectedWrestlers.player1 && selectedWrestlers.player2;
        } else { // tag
            isReady = selectedWrestlers.team1_player1 && selectedWrestlers.team1_player2 && selectedWrestlers.team2_player1 && selectedWrestlers.team2_player2;
        }

        const actionButtons = document.getElementById('actionButtons');
        actionButtons.querySelectorAll('button').forEach(btn => {
            btn.classList.toggle('hidden', !isReady);
        });
    }

    function resetMatch() {
        // Reset state object
        for (const key in selectedWrestlers) {
            selectedWrestlers[key] = null;
        }

        // Reset all drop zone UI
        Object.values(dropZones).forEach(zone => {
            if (zone) {
                const isTeam1 = zone.id.includes('team1');
                const isTeam2 = zone.id.includes('team2');
                let text = 'Drag Wrestler Here';
                if (isTeam1) text = 'Drag Team 1 Wrestler';
                if (isTeam2) text = 'Drag Team 2 Wrestler';
                zone.innerHTML = text;
                zone.classList.add('border-dashed', 'items-center', 'justify-center');
                zone.classList.remove('items-start');
            }
        });
        
        updateActionButtons();
        resultsContainer.classList.add('hidden');
    }

    function createRandomMatchup() {
        resetMatch();
        let rosterCopy = [...rosterData];
        
        if (matchType === 'single') {
            const index1 = Math.floor(Math.random() * rosterCopy.length);
            const wrestler1 = rosterCopy.splice(index1, 1)[0];
            const index2 = Math.floor(Math.random() * rosterCopy.length);
            const wrestler2 = rosterCopy[index2];
            addWrestlerToSlot(wrestler1, 'player1');
            addWrestlerToSlot(wrestler2, 'player2');
        } else { // tag
            const team1_p1_index = Math.floor(Math.random() * rosterCopy.length);
            const team1_p1 = rosterCopy.splice(team1_p1_index, 1)[0];
            const team1_p2_index = Math.floor(Math.random() * rosterCopy.length);
            const team1_p2 = rosterCopy.splice(team1_p2_index, 1)[0];
            
            const team2_p1_index = Math.floor(Math.random() * rosterCopy.length);
            const team2_p1 = rosterCopy.splice(team2_p1_index, 1)[0];
            const team2_p2_index = Math.floor(Math.random() * rosterCopy.length);
            const team2_p2 = rosterCopy[team2_p2_index];

            addWrestlerToSlot(team1_p1, 'team1_player1');
            addWrestlerToSlot(team1_p2, 'team1_player2');
            addWrestlerToSlot(team2_p1, 'team2_player1');
            addWrestlerToSlot(team2_p2, 'team2_player2');
        }
    }

    async function runSimulation() {
        startMatchBtn.disabled = true;
        startMatchBtn.textContent = 'Simulating...';
        resultsContainer.classList.remove('hidden');
        winnerDisplay.innerHTML = '';
        matchLog.innerHTML = '<p class="text-center text-gray-400">The simulation is running...</p>';

        let matchData;
        if (matchType === 'single') {
            matchData = {
                type: 'single',
                wrestler1_id: selectedWrestlers.player1.wrestler_id,
                wrestler2_id: selectedWrestlers.player2.wrestler_id
            };
        } else {
            matchData = {
                type: 'tag',
                team1: [selectedWrestlers.team1_player1.wrestler_id, selectedWrestlers.team1_player2.wrestler_id],
                team2: [selectedWrestlers.team2_player1.wrestler_id, selectedWrestlers.team2_player2.wrestler_id]
            };
        }

        try {
            // NOTE: You will need to update your backend to handle the new 'type' and 'teams' structure
            const response = await fetch(baseUrl + 'api/run_simulation', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(matchData)
            });
            const result = await response.json();
            if (response.ok) {
                displayResults(result);
            } else {
                matchLog.innerHTML = `<p class="text-red-500 text-center">Error: ${result.error || 'Unknown error'}</p>`;
            }
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
        
        let simData = { sim_count: simCount };
        if (matchType === 'single') {
            simData.wrestler1_id = selectedWrestlers.player1.wrestler_id;
            simData.wrestler2_id = selectedWrestlers.player2.wrestler_id;
        } else {
            simData.type = 'tag';
            simData.team1_ids = [selectedWrestlers.team1_player1.wrestler_id, selectedWrestlers.team1_player2.wrestler_id];
            simData.team2_ids = [selectedWrestlers.team2_player1.wrestler_id, selectedWrestlers.team2_player2.wrestler_id];
        }

        try {
            const response = await fetch(baseUrl + 'api/run_bulk_simulation', { 
                 method: 'POST', 
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(simData)
            });
            const result = await response.json();
            
            if (response.ok) {
                let html = '<div class="space-y-3">';
                
                // Use team names from result if it's a tag match
                const team1Name = result.team1_names || 'Team 1';
                const team2Name = result.team2_names || 'Team 2';

                for (const winnerName in result.wins) {
                    let displayName = winnerName;
                    if (winnerName === 'Team 1' && result.team1_names) {
                        displayName = result.team1_names;
                    } else if (winnerName === 'Team 2' && result.team2_names) {
                        displayName = result.team2_names;
                    }

                    if (winnerName.toLowerCase() === 'draw') {
                        const percentage = ((result.wins[winnerName] / simCount) * 100).toFixed(1);
                        html += `
                            <div class="grid grid-cols-2 items-center text-lg">
                                <span class="font-semibold">${displayName}:</span>
                                <span class="text-right">${result.wins[winnerName]} outcomes (${percentage}%)</span>
                            </div>`;
                        continue;
                    }

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
                            <h3 class="text-xl font-bold text-white">${displayName}</h3>
                            <div class="flex justify-between items-center text-gray-300 mt-2">
                                <span>Win Count:</span>
                                <span class="font-semibold">${result.wins[winnerName]} of ${simCount} (${percentage}%)</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-300 mt-1">
                                <span>Moneyline Odds:</span>
                                <span class="font-bold text-lg ${moneyline > 0 ? 'text-green-400' : 'text-red-400'}">${moneyline > 0 ? '+' : ''}${moneyline}</span>
                            </div>
                            <div class="flex justify-between items-center text-gray-300 mt-1 pt-2 border-t border-gray-600">
                                <span>A 100 Gold Bet On ${displayName} Pays Out:</span>
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

    // --- EVENT HANDLER HELPERS ---
    
    function handleRosterClick(e) {
        const card = e.target.closest('[data-wrestler-id]');
        if (!card) return;
        const wrestlerId = card.dataset.wrestlerId;
        const wrestler = rosterData.find(w => w.wrestler_id == wrestlerId);
        
        if (wrestler) {
            // Find the next available slot and add the wrestler
            let availableSlot = null;
            if (matchType === 'single') {
                if (!selectedWrestlers.player1) availableSlot = 'player1';
                else if (!selectedWrestlers.player2) availableSlot = 'player2';
            } else { // tag
                if (!selectedWrestlers.team1_player1) availableSlot = 'team1_player1';
                else if (!selectedWrestlers.team1_player2) availableSlot = 'team1_player2';
                else if (!selectedWrestlers.team2_player1) availableSlot = 'team2_player1';
                else if (!selectedWrestlers.team2_player2) availableSlot = 'team2_player2';
            }
            if (availableSlot) {
                addWrestlerToSlot(wrestler, availableSlot);
            }
        }
    }

    function handleRosterDragStart(e) {
        const card = e.target.closest('[data-wrestler-id]');
        if (card) e.dataTransfer.setData('text/plain', card.dataset.wrestlerId);
    }

    function setupDropZone(zone, key) {
        zone.addEventListener('dragover', (e) => {
             e.preventDefault();
             zone.classList.add('border-green-500');
        });
        zone.addEventListener('dragleave', (e) => {
             zone.classList.remove('border-green-500');
        });
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('border-green-500');
            const wrestlerId = e.dataTransfer.getData('text/plain');
            const wrestler = rosterData.find(w => w.wrestler_id == wrestlerId);
            if (wrestler) {
                addWrestlerToSlot(wrestler, key);
            }
        });
    }

    function handleSortChange() {
        currentSort.key = sortSelect.value;
        filterSortAndRenderRoster();
    }

    function handleSortOrderToggle() {
        currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
        sortOrderIcon.textContent = currentSort.order === 'asc' ? '↓' : '↑';
        filterSortAndRenderRoster();
    }
});

