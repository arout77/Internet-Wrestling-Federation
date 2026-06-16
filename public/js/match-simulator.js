document.addEventListener('DOMContentLoaded', () => {
    // --- Get Elements ---
    const bulkModal = document.getElementById('bulk-results-modal');
    const singleMatchModal = document.getElementById('simulation-modal');
    const bettingModal = document.getElementById('betting-modal');
    const simButtons = document.querySelectorAll('.sim-button');
    const resetMatchBtn = document.getElementById('reset-match-btn');
    const randomMatchBtn = document.getElementById('random-matchup-btn');
    const openBettingModalBtn = document.getElementById('open-betting-modal-btn');
    const closeBulkModalBtn = bulkModal?.querySelector('.modal-close');
    const closeSingleModalBtn = document.getElementById('close-sim-modal-btn');
    const closeBettingModalBtn = document.getElementById('close-betting-modal-btn');
    const confirmBetBtn = document.getElementById('confirm-bet-btn');
    const betOnTeam1Btn = document.getElementById('bet-on-team1-btn');
    const betOnTeam2Btn = document.getElementById('bet-on-team2-btn');
    const team1Dropzone = document.getElementById('team1-dropzone');
    const team2Dropzone = document.getElementById('team2-dropzone');
    const dropzones = [team1Dropzone, team2Dropzone].filter(Boolean);
    const rosterParent = document.getElementById('roster-parent');
    const tagTeamParent = document.getElementById('tag-team-parent');
    const tabWrestlers = document.getElementById('tab-wrestlers');
    const tabTagTeams = document.getElementById('tab-tag-teams');
    const rosterControls = document.getElementById('roster-controls');
    const rosterSearch = document.getElementById('roster-search');
    const rosterSort = document.getElementById('roster-sort');
    // ... all other modal/UI elements ...
    const simTeam1Display = document.getElementById('sim-team1-display');
    const simTeam2Display = document.getElementById('sim-team2-display');
    const matchLog = document.getElementById('match-log');
    const totalSimsDisplay = document.getElementById('total-sims-display');
    const team1BulkWrestlers = document.getElementById('team1-bulk-wrestlers');
    const team1WinsDisplay = document.getElementById('team1-wins-display');
    const team1PctDisplay = document.getElementById('team1-pct-display');
    const team1OddsDisplay = document.getElementById('team1-odds-display');
    const team1PayoutDisplay = document.getElementById('team1-payout-display');
    const team2BulkWrestlers = document.getElementById('team2-bulk-wrestlers');
    const team2WinsDisplay = document.getElementById('team2-wins-display');
    const team2PctDisplay = document.getElementById('team2-pct-display');
    const team2OddsDisplay = document.getElementById('team2-odds-display');
    const team2PayoutDisplay = document.getElementById('team2-payout-display');
    const betAmountInput = document.getElementById('bet-amount');
    const betTeam1Name = document.getElementById('bet-team1-name');
    const betTeam1Odds = document.getElementById('bet-team1-odds');
    const betTeam1WinPct = document.getElementById('bet-team1-winpct');
    const betTeam1Payout100 = document.getElementById('bet-team1-payout100');
    // *** FIX: Get image elements for the betting modal ***
    const betTeam1Img = document.getElementById('bet-team1-img');
    const betTeam2Name = document.getElementById('bet-team2-name');
    const betTeam2Odds = document.getElementById('bet-team2-odds');
    const betTeam2WinPct = document.getElementById('bet-team2-winpct');
    const betTeam2Payout100 = document.getElementById('bet-team2-payout100');
    // *** FIX: Get image elements for the betting modal ***
    const betTeam2Img = document.getElementById('bet-team2-img');
    const potentialPayoutDisplay = document.getElementById('potential-payout');


    console.log("DOM fully loaded and parsed");

    // --- State ---
    let draggedCardElement = null; // Reference to the *original roster card* being dragged
    // REPLICATION: Use state to track teams, not DOM query
    let teamState = { team1: [], team2: [] };
    let allRosterCards = rosterParent ? Array.from(rosterParent.querySelectorAll('.wrestler-card[data-id]')) : [];
    let bettingState = {
        team1: null, team2: null, betOn: null, amount: 0, odds: null, potentialPayout: 0
    };
    const baseUrl = window.baseUrl || '';

    // --- HELPER: Generate Team Dropzone Card HTML ---
    function getTeamCardHTML(cardData) {
        let traitsHTML = '<div class="h-4 mb-1"></div>'; // Smaller placeholder
        try {
            let traitsArray = [];
            // REPLICATION: Check if traits data is a stringified JSON and parse it
            if (typeof cardData.traits === 'string' && cardData.traits.startsWith('[')) {
                try {
                    // Replace escaped quotes before parsing
                    traitsArray = JSON.parse(cardData.traits.replace(/&quot;/g, '"'));
                } catch (e) { console.warn("Could not parse team traits JSON:", cardData.traits, e); }
            } else if (Array.isArray(cardData.traits)) {
                // This case might not happen if data comes from data-* attributes
                traitsArray = cardData.traits;
            }

            if (traitsArray && traitsArray.length > 0) {
                traitsHTML = '<div class="flex flex-wrap justify-start gap-1 mb-1">';
                traitsArray.forEach(trait => {
                    // Ensure trait is an object with a name
                    if (typeof trait === 'object' && trait !== null && trait.name) {
                        let bgColorClass = 'bg-blue-600';
                        if (trait.name === 'Resilient') bgColorClass = 'bg-blue-600';
                        else if (trait.name === 'Brawler') bgColorClass = 'bg-red-600';
                        else if (trait.name === 'Powerhouse') bgColorClass = 'bg-pink-500';
                        else if (trait.name === 'High Flyer') bgColorClass = 'bg-purple-600';
                        else if (trait.name === 'Technician') bgColorClass = 'bg-green-600';
                        else if (trait.name === 'Submission Specialist') bgColorClass = 'bg-orange-600';
                        else if (trait.name === 'Comeback Kid') bgColorClass = 'bg-purple-600';
                        else if (trait.name === 'Giant') bgColorClass = 'bg-amber-500';
                        else if (trait.name === 'Brick Wall') bgColorClass = 'bg-amber-900';
                        // Add more trait colors
                        traitsHTML += `<span class="text-xs text-white ${bgColorClass} px-1.5 py-0.5 rounded-full">${trait.name}</span>`;
                    } else { console.warn("Invalid team trait format:", trait); }
                });
                traitsHTML += '</div>';
            }
        } catch (e) { console.error("Error processing traits data for team card:", cardData.traits, e); }

        // This is the two-column HTML structure for the dropzone
        return `
            <div class="wrestler-card-content grid grid-cols-3 gap-2 items-start w-full">
                <div class="col-span-1 flex flex-col items-center">
                    <!-- Check cardData.imagePath first, then fallback to filename/manual path -->
                    <img src="${baseUrl + '/public/images/' + cardData.image + '.webp'}" alt="${cardData.name || '?'}"
                         class="w-20 h-20 mb-1 border-2 border-yellow-500 object-cover rounded-full"
                         onerror="this.onerror=null; this.src='https://placehold.co/80x80/333/999?text=?';">
                    <h3 class="text-base font-semibold text-white mb-0.5 text-center truncate w-full">${cardData.name || 'Unknown'}</h3>
                    <p class="text-gray-400 text-xs mb-1">Overall: <span class="font-bold text-yellow-400">${cardData.overall || '?'}</span></p>
                    ${traitsHTML}
                </div>
                <div class="col-span-2 description-column">
                    <p>${cardData.description || 'No description available.'}</p>
                </div>
            </div>
            <button class="remove-wrestler-btn" title="Remove Wrestler">&times;</button>
        `;
    }


    // --- DRAG & DROP (REFACTORED to replicate old logic) ---
    function initializeDragDrop() {
        console.log("Init D&D");
        // Find all draggable cards *in the roster*
        allRosterCards = rosterParent ? Array.from(rosterParent.querySelectorAll('.wrestler-card[data-id]')) : [];
        console.log(`Found ${allRosterCards.length} draggable cards in roster.`);

        allRosterCards.forEach(card => {
            // No need to generate HTML, it's already rendered by Twig
            card.addEventListener('dragstart', onRosterDragStart);
            card.addEventListener('dragend', onRosterDragEnd);
        });

        // Setup team dropzones
        dropzones.forEach(zone => {
            if (zone) {
                zone.addEventListener('dragover', onDragOver);
                zone.addEventListener('dragleave', onDragLeave);
                zone.addEventListener('drop', onDropOnTeamZone); // Specific handler
            } else { console.error("Dropzone element not found during init."); }
        });

        // Add event delegation for remove buttons (since they are created dynamically)
        if (team1Dropzone) team1Dropzone.addEventListener('click', onTeamCardClick);
        if (team2Dropzone) team2Dropzone.addEventListener('click', onTeamCardClick);
    }

    // When dragging *from* the ROSTER
    function onRosterDragStart(e) {
        if (this.classList.contains('in-use')) { // Check if already disabled
            e.preventDefault();
            return;
        }
        draggedCardElement = this; // Store the *original roster card*
        setTimeout(() => {
            if (draggedCardElement) draggedCardElement.classList.add('opacity-50');
        }, 0); // Visual feedback
        try {
            e.dataTransfer.setData('text/plain', this.dataset.id);
            e.dataTransfer.effectAllowed = "move";
        } catch (err) { console.error("Error setting dataTransfer:", err); }
    }

    // After dragging *from* the ROSTER
    function onRosterDragEnd(e) {
        if (draggedCardElement) {
            draggedCardElement.classList.remove('opacity-50'); // Clean up visual
        }
        draggedCardElement = null; // Clear reference
    }

    // Generic drag over handler for *team dropzones*
    function onDragOver(e) {
        e.preventDefault();
        const teamKey = this.id.includes('team1') ? 'team1' : 'team2';
        // Allow drop only if team has space
        if (teamState[teamKey].length < 2) {
            this.classList.add('bg-gray-700'); // Highlight
            const placeholder = this.querySelector('.dropzone-placeholder');
            if (placeholder) placeholder.classList.add('opacity-0');
            e.dataTransfer.dropEffect = "move";
        } else {
            e.dataTransfer.dropEffect = "none"; // Deny drop
        }
    }

    // Generic drag leave handler for *team dropzones*
    function onDragLeave(e) {
        this.classList.remove('bg-gray-700');
        updateDropzonePlaceholder(this);
        const placeholder = this.querySelector('.dropzone-placeholder');
        if (placeholder) placeholder.classList.remove('opacity-0');
    }

    // When dropping *onto* a TEAM ZONE
    function onDropOnTeamZone(e) {
        e.preventDefault();
        this.classList.remove('bg-gray-700');
        const placeholder = this.querySelector(':scope > .dropzone-placeholder');
        if (placeholder) placeholder.classList.remove('opacity-0');

        // Ensure we're dragging a valid roster card
        if (!draggedCardElement || !draggedCardElement.classList.contains('wrestler-card')) {
            console.warn("Drop ignored: Not a valid roster card drag.");
            return;
        }

        const wrestlerId = draggedCardElement.dataset.id;
        
        // --- FIX: Safely extract the image filename regardless of how the dataset is populated. ---
        let imageFileName = draggedCardElement.dataset.imageFileName;
        
        // **NEW FALLBACK: Read the SRC of the image element itself**
        if (!imageFileName) {
            const imgElement = draggedCardElement.querySelector('img');
            if (imgElement && imgElement.src) {
                // Parse 'filename' from the loaded SRC attribute
                const fullFileName = imgElement.src.split('/').pop();
                // Ensure we strip the .webp extension (or any other extension)
                imageFileName = fullFileName.replace(/\.(webp|png|jpg|jpeg)$/i, '');
            }
        }
        // **SAFETY CHECK: If the filename is still undefined, use a generic placeholder name**
        if (!imageFileName) {
             console.error("Image filename could not be determined. Using generic avatar.");
             imageFileName = 'gen'; // Fallback to a safe generic filename (e.g., 'gen.png')
        }

        const cardData = { 
            ...draggedCardElement.dataset,  // Get data from original card
            imageFileName: draggedCardElement.dataset.imageFileName || (draggedCardElement.querySelector('img') ? draggedCardElement.querySelector('img').src.split('/').pop().replace('.webp', '') : undefined) 
        };
        const rosterCard = draggedCardElement; // Alias for clarity

        // Check if card is already in use
        if (rosterCard.classList.contains('in-use')) {
            console.log("Drop aborted: Card already in use.");
            return;
        }

        const teamKey = this.id.includes('team1') ? 'team1' : 'team2';

        // Check team capacity
        if (teamState[teamKey].length < 2) {
            console.log(`Adding ${cardData.name} to ${teamKey}`);

            // REPLICATION: Create a *new* element for the team zone
            const teamCard = document.createElement('div');
            teamCard.className = 'wrestler-card-team'; // New class for team cards
            teamCard.dataset.id = cardData.id; // Store ID for removal
            teamCard.innerHTML = getTeamCardHTML(cardData); // Generate 2-column HTML

            // Append the *new* card
            this.appendChild(teamCard);

            // REPLICATION: Update internal state
            teamState[teamKey].push(cardData);

            // REPLICATION: Disable the *original* roster card
            rosterCard.classList.add('in-use'); // 'in-use' class handles opacity/pointer-events
            rosterCard.draggable = false; // Disable dragging

            updateDropzonePlaceholder(this);
            updateSimButtonState();
        } else {
            console.log(`Team ${teamKey} is full.`);
        }
        draggedCardElement = null; // Clear drag reference
    }

    // REPLICATION: Handle click on the *new* team card (or its remove button)
    function onTeamCardClick(e) {
        // Find the closest remove button
        const removeBtn = e.target.closest('.remove-wrestler-btn');
        // If the remove button wasn't clicked, ignore the click
        if (!removeBtn) return;

        const cardToRemove = removeBtn.closest('.wrestler-card-team');
        if (!cardToRemove) return; // Should not happen if button is found

        const wrestlerId = cardToRemove.dataset.id;
        if (!wrestlerId) return;

        const parentZone = cardToRemove.parentNode;
        const teamKey = parentZone.id.includes('team1') ? 'team1' : 'team2';

        console.log(`Removing ${wrestlerId} from ${teamKey}`);

        // Remove from state
        teamState[teamKey] = teamState[teamKey].filter(w => w.id !== wrestlerId);

        // Remove from DOM
        parentZone.removeChild(cardToRemove);

        // Re-enable the original roster card
        const rosterCard = rosterParent ? rosterParent.querySelector(`.wrestler-card[data-id='${wrestlerId}']`) : null;
        if (rosterCard) {
            rosterCard.classList.remove('in-use', 'opacity-50', 'pointer-events-none');
            rosterCard.draggable = true;
        } else {
            console.warn(`Could not find roster card with ID ${wrestlerId} to re-enable.`);
        }

        updateDropzonePlaceholder(parentZone);
        updateSimButtonState();
    }

    // REPLICATION: Update placeholder based on *team card* count
    function updateDropzonePlaceholder(zone) {
        if (!zone || !zone.classList.contains('dropzone')) return;
        const placeholder = zone.querySelector(':scope > .dropzone-placeholder');
        if (!placeholder) return;
        const cardCount = zone.querySelectorAll(':scope > .wrestler-card-team').length; // Look for team cards

        if (cardCount > 0) {
            placeholder.style.display = 'none';
            zone.classList.remove('justify-center'); zone.classList.add('justify-start');
        } else {
            placeholder.style.display = 'flex';
            zone.classList.remove('justify-start'); zone.classList.add('justify-center');
        }
    }

    function updateAllPlaceholders() {
        dropzones.forEach(updateDropzonePlaceholder);
    }

    // REPLICATION: Filter/Sort logic acts on the *original* roster cards
    function filterAndSortRoster() {
        if (!rosterParent) return;
        const searchTerm = rosterSearch?.value?.toLowerCase() || '';
        const sortValue = rosterSort?.value || 'name_asc';

        // Use the stored full list
        let cardsInRoster = allRosterCards; 

        // Filter logic
        cardsInRoster.forEach(card => {
            const name = card.dataset.name?.toLowerCase() || '';
            const nameMatch = name.includes(searchTerm);
            if (nameMatch) {
                card.classList.remove('hidden');
                // Force display style if 'hidden' class toggling isn't enough due to specificity
                // card.style.display = ''; 
            } else {
                card.classList.add('hidden');
                // card.style.display = 'none';
            }
        });

        // Sort logic
        cardsInRoster.sort((a, b) => {
            const aData = a.dataset; const bData = b.dataset;
            const nameA = aData.name?.toLowerCase() ?? ''; const nameB = bData.name?.toLowerCase() ?? '';
            const overallA = parseInt(aData.overall ?? '0', 10); const overallB = parseInt(bData.overall ?? '0', 10);
            const strengthA = parseInt(aData.strength ?? '0', 10); const strengthB = parseInt(bData.strength ?? '0', 10);
            const technicalA = parseInt(aData.technical ?? '0', 10); const technicalB = parseInt(bData.technical ?? '0', 10);
            const brawlingA = parseInt(aData.brawling ?? '0', 10); const brawlingB = parseInt(bData.brawling ?? '0', 10);
            const staminaA = parseInt(aData.stamina ?? '0', 10); const staminaB = parseInt(bData.stamina ?? '0', 10);
            const aerialA = parseInt(aData.aerial ?? '0', 10); const aerialB = parseInt(bData.aerial ?? '0', 10);
            const toughnessA = parseInt(aData.toughness ?? '0', 10); const toughnessB = parseInt(bData.toughness ?? '0', 10);

            switch (sortValue) {
                case 'name_asc': return nameA.localeCompare(nameB);
                case 'name_desc': return nameB.localeCompare(nameA);
                case 'overall_desc': return overallB - overallA; case 'overall_asc': return overallA - overallB;
                case 'strength_desc': return strengthB - strengthA; case 'strength_asc': return strengthA - strengthB;
                case 'technical_desc': return technicalB - technicalA; case 'technical_asc': return technicalA - technicalB;
                case 'brawling_desc': return brawlingB - brawlingA; case 'brawling_asc': return brawlingA - brawlingB;
                case 'stamina_desc': return staminaB - staminaA; case 'stamina_asc': return staminaA - staminaB;
                case 'aerial_desc': return aerialB - aerialA; case 'aerial_asc': return aerialA - aerialB;
                case 'toughness_desc': return toughnessB - toughnessA; case 'toughness_asc': return toughnessA - toughnessB;
                default: return 0;
            }
        });

        // Re-append sorted cards using DocumentFragment to prevent UI flicker/reflow issues
        const fragment = document.createDocumentFragment();
        cardsInRoster.forEach(card => fragment.appendChild(card));
        rosterParent.appendChild(fragment);
    }

    // --- SIMULATION LOGIC ---
    // REPLICATION: Get data *from state*
    function getTeamData(teamKey) {
        if (!teamState[teamKey]) {
            console.error(`getTeamData: Invalid teamKey '${teamKey}'`); return [];
        }
        return teamState[teamKey]; // Return the array of data objects
    }

    // REPLICATION: Check state for button status
    function updateSimButtonState() {
        if (!team1Dropzone || !team2Dropzone) return;
        const team1Ready = teamState.team1.length > 0;
        const team2Ready = teamState.team2.length > 0;
        const ready = team1Ready && team2Ready;

        simButtons.forEach(btn => btn.disabled = !ready);
        if (openBettingModalBtn) openBettingModalBtn.disabled = !ready;
        if (resetMatchBtn) resetMatchBtn.disabled = !(team1Ready || team2Ready);
    }

    async function runSimulation(simCount) {
        // REPLICATION: Use state data
        const team1Data = getTeamData('team1');
        const team2Data = getTeamData('team2');
        const team1Ids = team1Data.map(d => d.id).filter(id => id);
        const team2Ids = team2Data.map(d => d.id).filter(id => id);

        if (team1Ids.length === 0 || team2Ids.length === 0) {
            console.error("Cannot run simulation: Missing valid IDs.", { team1Ids, team2Ids });
            alert("Error: Could not retrieve wrestler IDs.");
            updateSimButtonState(); return;
        }

        simButtons.forEach(btn => btn.disabled = true);
        if (openBettingModalBtn) openBettingModalBtn.disabled = true;
        if (resetMatchBtn) resetMatchBtn.disabled = true;

        try {
            // *** FIX: This function is for BULK sims, so we *always* call the 'run' (odds) route ***
            // This now points to /api/simulator/run which is mapped to SimulatorController::runMatch
            const fetchUrl = `${baseUrl}/api/simulator/run`;
            
            // *** FIX: Send the payload 'runMatch' (odds) method expects ***
            const body = JSON.stringify({
                wrestler1_id: team1Ids[0], // Assumes 1v1
                wrestler2_id: team2Ids[0], // Assumes 1v1
                num_sims: simCount
            });

            const response = await fetch(fetchUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: body
            });

            if (!response.ok) {
                let errorMsg = `HTTP error! Status: ${response.status} ${response.statusText || 'Error'}`;
                let responseBodyText = await response.text();
                try { const err = JSON.parse(responseBodyText); errorMsg = err.error || errorMsg; }
                catch (e) { /* ignore */ }
                throw new Error(errorMsg);
            }
            const results = await response.json();

            // *** FIX: Parse the *actual* response from the 'runMatch' method ***
            if (!results.success) {
                throw new Error(results.error || "Simulation failed");
            }

            // `results` is {success: true, w1_odds: ..., w1_wins: ...}
            // We must build the object `displayBulkResults` expects
            const bulkData = {
                total_sims: results.total_sims,
                team1: {
                    // *** FIX: Use data-image-path ***
                    wrestlers: team1Data.map(w => ({ name: w.name, image_thumb: w.imagePath })),
                    wins: results.w1_wins,
                    win_pct: results.w1_win_percent,
                    odds: {
                        odds: `${results.w1_odds > 0 ? '+' : ''}${results.w1_odds}`,
                        // *** FIX: Correct payout logic for favorites ***
                        payout: results.w1_odds > 0 ? results.w1_odds : Math.abs(results.w1_odds)
                    }
                },
                team2: {
                    // *** FIX: Use data-image-path ***
                    wrestlers: team2Data.map(w => ({ name: w.name, image_thumb: w.imagePath })),
                    wins: results.w2_wins,
                    win_pct: results.w2_win_percent,
                    odds: {
                        odds: `${results.w2_odds > 0 ? '+' : ''}${results.w2_odds}`,
                        // *** FIX: Correct payout logic for favorites ***
                        payout: results.w2_odds > 0 ? results.w2_odds : Math.abs(results.w2_odds)
                    }
                }
            };
            displayBulkResults(bulkData);

        } catch (error) {
            console.error('Simulation Error:', error);
            alert(`Error running simulation: ${error.message}`);
        } finally {
            updateSimButtonState();
        }
    }

    // --- DISPLAY RESULTS (MODALS) ---
    function displayBulkResults(data) {
        if (!data?.team1?.odds || !data?.team2?.odds) { console.error("Bulk results data invalid:", data); alert("Error displaying results."); return; }
        if (!totalSimsDisplay || !team1BulkWrestlers || !team1WinsDisplay || !team1PctDisplay || !team1OddsDisplay || !team1PayoutDisplay || !team2BulkWrestlers || !team2WinsDisplay || !team2PctDisplay || !team2OddsDisplay || !team2PayoutDisplay || !bulkModal) { console.error("Bulk modal elements missing."); return; }
        totalSimsDisplay.innerText = data.total_sims.toLocaleString();
        team1BulkWrestlers.innerHTML = data.team1.wrestlers.map(w => `<div class="flex items-center justify-center gap-2"><img src="${w.image_thumb || ''}" alt="${w.name || '?'}" class="w-10 h-10 rounded-full object-cover border-2 border-gray-600" onerror="this.onerror=null; this.src='https://placehold.co/40x40/333/999?text=?';"><span class="font-semibold text-white">${w.name || '?'}</span></div>`).join('');
        team1WinsDisplay.innerText = `${(data.team1.wins || 0).toLocaleString()} Wins`;
        team1PctDisplay.innerText = `${(data.team1.win_pct || 0).toFixed(1)}%`;
        team1OddsDisplay.innerText = data.team1.odds.odds || 'N/A';
        const odds1 = data.team1.odds;
        // *** FIX: Payout calculation text for favorites ***
        team1PayoutDisplay.innerText = odds1.odds.startsWith('+') ? `A 100 Gold bet wins ${odds1.payout.toFixed(0)} Gold` : `Bet ${odds1.payout.toFixed(0)} Gold to win 100 Gold`;
        team2BulkWrestlers.innerHTML = data.team2.wrestlers.map(w => `<div class="flex items-center justify-center gap-2"><img src="${w.image_thumb || ''}" alt="${w.name || '?'}" class="w-10 h-10 rounded-full object-cover border-2 border-gray-600" onerror="this.onerror=null; this.src='https://placehold.co/40x40/333/999?text=?';"><span class="font-semibold text-white">${w.name || '?'}</span></div>`).join('');
        team2WinsDisplay.innerText = `${(data.team2.wins || 0).toLocaleString()} Wins`;
        team2PctDisplay.innerText = `${(data.team2.win_pct || 0).toFixed(1)}%`;
        team2OddsDisplay.innerText = data.team2.odds.odds || 'N/A';
        const odds2 = data.team2.odds;
        // *** FIX: Payout calculation text for favorites ***
        team2PayoutDisplay.innerText = odds2.odds.startsWith('+') ? `A 100 Gold bet wins ${odds2.payout.toFixed(0)} Gold` : `Bet ${odds2.payout.toFixed(0)} Gold to win 100 Gold`;
        bulkModal.classList.remove('hidden');
    }

    // *** FIX: Added `betData` parameter to receive bet results ***
    function displaySingleMatch(log, team1Data, team2Data, betData = null) {
        if (!matchLog || !simTeam1Display || !simTeam2Display || !singleMatchModal) {
            console.error("Single match modal elements not found."); return;
        }

        matchLog.innerHTML = ''; // Clear previous log
        let simulationIntervalId = null;

        // *** FIX: Get wrestler IDs from teamData, not the log ***
        const w1_sim_id = team1Data[0]?.id; // Assumes 1v1
        const w2_sim_id = team2Data[0]?.id; // Assumes 1v1

        // Build Team 1 UI in the modal
        simTeam1Display.innerHTML = team1Data.map((w, index) => {
            const isActive = w.id === w1_sim_id; // Check if this is the active wrestler
            return `
            <div id="sim-wrestler-${w.id}" class="mb-4 ${!isActive ? 'opacity-50' : ''}">
                <!-- *** FIX: Use data-image-path *** -->
                <img src="${w.imagePath || ''}" alt="${w.name || 'Wrestler'}" class="w-24 h-24 rounded-full mx-auto mb-2 border-4 ${isActive ? 'border-blue-500' : 'border-gray-700'} object-cover" onerror="this.onerror=null; this.src='https://placehold.co/96x96/333/999?text=?';">
                <h4 class="text-lg font-bold text-white">${w.name || 'Unknown'}</h4>
                <!-- Only show bars for the active wrestler -->
                ${isActive ? `
                <div class="status-bars mt-2 px-4 space-y-1">
                    <div class="status-bar-bg"><div id="sim-health-${w.id}" class="health-bar" style="width: 100%"></div></div>
                    <div class="status-bar-bg"><div id="sim-stamina-${w.id}" class="stamina-bar" style="width: 100%"></div></div>
                    <div class="status-bar-bg"><div id="sim-momentum-${w.id}" class="momentum-bar" style="width: 0%"></div></div>
                </div>
                ` : '<div class="status-bars mt-2 px-4 space-y-1 h-[40px]"></div>'}
            </div>
        `}).join('');

        // Build Team 2 UI in the modal
        simTeam2Display.innerHTML = team2Data.map((w, index) => {
            const isActive = w.id === w2_sim_id; // Check if this is the active wrestler
            return `
            <div id="sim-wrestler-${w.id}" class="mb-4 ${!isActive ? 'opacity-50' : ''}">
                <!-- *** FIX: Use data-image-path *** -->
                <img src="${w.imagePath || ''}" alt="${w.name || 'Wrestler'}" class="w-24 h-24 rounded-full mx-auto mb-2 border-4 ${isActive ? 'border-red-500' : 'border-gray-700'} object-cover" onerror="this.onerror=null; this.src='https://placehold.co/96x96/333/999?text=?';">
                <h4 class="text-lg font-bold text-white">${w.name || 'Unknown'}</h4>
                ${isActive ? `
                <div class="status-bars mt-2 px-4 space-y-1">
                    <div class="status-bar-bg"><div id="sim-health-${w.id}" class="health-bar" style="width: 100%"></div></div>
                    <div class="status-bar-bg"><div id="sim-stamina-${w.id}" class="stamina-bar" style="width: 100%"></div></div>
                    <div class="status-bar-bg"><div id="sim-momentum-${w.id}" class="momentum-bar" style="width: 0%"></div></div>
                </div>
                ` : '<div class="status-bars mt-2 px-4 space-y-1 h-[40px]"></div>'}
            </div>
        `}).join('');

        singleMatchModal.classList.remove('hidden'); // Show the modal

        // --- Process log events with delay ---
        let eventIndex = 0;
        window.clearSimulationInterval = () => {
            if (simulationIntervalId) { clearTimeout(simulationIntervalId); simulationIntervalId = null; }
        };

        function processNextEvent() {
            if (eventIndex >= log.length) {
                simulationIntervalId = null;
                 // *** FIX: Display bet results if they exist at the END of the log ***
                if (betData && betData.bet_amount > 0) {
                    let betMessage = '';
                    if (betData.bet_won) {
                        betMessage = `<p class="text-green-400 font-semibold text-center mt-4">You bet ${betData.bet_amount} and won ${betData.gold_change} Gold! <br> New balance: ${betData.new_balance} Gold.</p>`;
                    } else {
                        betMessage = `<p class="text-red-400 font-semibold text-center mt-4">You bet ${Math.abs(betData.gold_change)} and lost. <br> New balance: ${betData.new_balance} Gold.</p>`;
                    }
                    if (matchLog) {
                         matchLog.innerHTML += betMessage;
                         matchLog.scrollTop = matchLog.scrollHeight;
                    }
                }
                return;
            }
            const event = log[eventIndex];
            let message = '';
            let skipDelay = false;

            // *** FIX: This entire switch is rewritten to parse the PHP log ***
            switch (event.type) {
                case 'start':
                    message = `<p class="text-yellow-400 font-semibold">Match Start! ${event.data.w1.name} vs. ${event.data.w2.name}</p>`;
                    break;
                case 'turn':
                    message = `<p class="text-gray-500 border-b border-gray-700 my-1">Turn ${event.data.number}</p>`;
                    skipDelay = true;
                    break;
                case 'move':
                    const attackerName = event.data.attacker_name || 'Attacker';
                    const moveName = event.data.move_name || 'a move';
                    message = `<p class="text-white">${attackerName} hits ${moveName} for <strong>${event.data.damage}</strong> damage!</p>`;

                    const targetKey = event.data.defender_id; // 'w1' or 'w2'
                    const targetId = (targetKey === 'w1') ? w1_sim_id : w2_sim_id;

                    const img = document.querySelector(`#sim-wrestler-${targetId} img`);
                    if (img) {
                        img.classList.remove('shake');
                        void img.offsetWidth;
                        img.classList.add('shake');
                        // Remove shake class after animation finishes
                        setTimeout(() => {
                            if (img) img.classList.remove('shake');
                        }, 500);
                    }
                    break;
                case 'miss':
                    message = `<p class="text-gray-400 italic">${event.data.attacker_name || 'Attacker'} missed ${event.data.move_name || 'a move'}!</p>`;
                    break;
                case 'reversal':
                    message = `<p class="text-blue-400 font-semibold">${event.data.defender_name || 'Defender'} reversed ${event.data.move_name || 'the move'}!</p>`;
                    break;
                case 'stunned':
                    message = `<p class="text-orange-400 italic"><strong>${event.data.name}</strong> is stunned and skips their turn!</p>`;
                    break;
                case 'exhausted':
                     message = `<p class="text-gray-500 italic"><strong>${event.data.name}</strong> is too tired to use ${event.data.move_name} and rests.</p>`;
                     break;
                case 'end':
                    message = `<p class="text-green-400 text-xl font-bold text-center mt-4">${event.data.winner_name || 'Nobody'} wins by ${event.data.victory_method}!</p>`;
                    // Bet results are now handled *after* the log finishes
                    break;
                // *** FIX: Handle the 'update' event to move bars ***
                case 'update':
                    if (w1_sim_id && event.data.w1) {
                        const w1_data = event.data.w1;
                        const healthBar1 = document.getElementById(`sim-health-${w1_sim_id}`);
                        const stamBar1 = document.getElementById(`sim-stamina-${w1_sim_id}`);
                        const momBar1 = document.getElementById(`sim-momentum-${w1_sim_id}`);
                        if (healthBar1) healthBar1.style.width = `${Math.max(0, (w1_data.hp / w1_data.max_hp * 100))}%`;
                        if (stamBar1) stamBar1.style.width = `${Math.max(0, w1_data.stamina)}%`;
                        if (momBar1) momBar1.style.width = `${Math.min(100, w1_data.momentum)}%`;
                    }
                    if (w2_sim_id && event.data.w2) {
                        const w2_data = event.data.w2;
                        const healthBar2 = document.getElementById(`sim-health-${w2_sim_id}`);
                        const stamBar2 = document.getElementById(`sim-stamina-${w2_sim_id}`);
                        const momBar2 = document.getElementById(`sim-momentum-${w2_sim_id}`);
                        if (healthBar2) healthBar2.style.width = `${Math.max(0, (w2_data.hp / w2_data.max_hp * 100))}%`;
                        if (stamBar2) stamBar2.style.width = `${Math.max(0, w2_data.stamina)}%`;
                        if (momBar2) momBar2.style.width = `${Math.min(100, w2_data.momentum)}%`;
                    }
                    skipDelay = true; // Don't log this, just update bars
                    break;
                default:
                    message = `<p class="text-gray-400">Unknown event: ${event.type}</p>`;
                    skipDelay = true;
            }

            if (message && matchLog) {
                matchLog.innerHTML += message;
                matchLog.scrollTop = matchLog.scrollHeight;
            }
            eventIndex++;
            simulationIntervalId = setTimeout(processNextEvent, skipDelay ? 400 : 1200); // Shorter delay for counts/minor
        }
        window.clearSimulationInterval(); // Clear any previous interval
        simulationIntervalId = setTimeout(processNextEvent, 1200); // Start new
    }


    // --- BETTING LOGIC (Updated) ---
    async function openBettingModal() {
        if (!bettingModal) return;
        // REPLICATION: Use state data
        const team1Data = getTeamData('team1');
        const team2Data = getTeamData('team2');

        if (team1Data.length === 0 || team2Data.length === 0) {
            alert('Please select wrestlers for both teams before placing a bet.'); return;
        }
        if (openBettingModalBtn) {
            openBettingModalBtn.disabled = true; openBettingModalBtn.innerText = 'Calculating Odds...';
        }

        const team1Ids = team1Data.map(d => d.id).filter(id => id);
        const team2Ids = team2Data.map(d => d.id).filter(id => id);

        if (team1Ids.length === 0 || team2Ids.length === 0) {
            alert("Error: Could not retrieve wrestler IDs.");
            if (openBettingModalBtn) { openBettingModalBtn.disabled = false; openBettingModalBtn.innerText = 'Place Bet'; }
            return;
        }

        try {
            // *** FIX: Use the correct /api/simulator/run endpoint (which now points to 'runMatch') ***
            const fetchUrl = `${baseUrl}/api/simulator/run`;
            const response = await fetch(fetchUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                // *** FIX: Send the payload /api/run-match expects ***
                body: JSON.stringify({
                    wrestler1_id: team1Ids[0], // Assumes 1v1
                    wrestler2_id: team2Ids[0], // Assumes 1v1
                    num_sims: 1000
                })
            });

            if (!response.ok) {
                let errorMsg = `HTTP error! Status: ${response.status} ${response.statusText || 'Error'}`;
                let responseBodyText = await response.text();
                try { const err = JSON.parse(responseBodyText); errorMsg = err.error || errorMsg; }
                catch (e) { /* ignore */ }
                throw new Error(errorMsg);
            }

            // *** FIX: Parse the *actual* response from the 'runMatch' controller method ***
            const results = await response.json();
            if (!results.success || results.w1_odds === undefined) {
                throw new Error(results.error || "Received invalid odds data from server.");
            }

            // --- Populate Betting Modal ---
            // Store the data in the format the rest of the modal logic expects
            bettingState.team1 = {
                odds: {
                    odds: `${results.w1_odds > 0 ? '+' : ''}${results.w1_odds}`,
                    // *** FIX: Correct payout logic for favorites ***
                    payout: results.w1_odds > 0 ? results.w1_odds : Math.abs(results.w1_odds)
                }
            };
            bettingState.team2 = {
                odds: {
                    odds: `${results.w2_odds > 0 ? '+' : ''}${results.w2_odds}`,
                    // *** FIX: Correct payout logic for favorites ***
                    payout: results.w2_odds > 0 ? results.w2_odds : Math.abs(results.w2_odds)
                }
            };

            // *** FIX: Populate images in the modal ***
            if (betTeam1Img) betTeam1Img.src = results.w1_image;
            if (betTeam1Name) betTeam1Name.innerText = results.w1_name;
            if (betTeam1WinPct) betTeam1WinPct.innerText = `Win: ${results.w1_win_percent.toFixed(1)}%`;
            if (betTeam1Odds) betTeam1Odds.innerText = `Odds: ${results.w1_odds > 0 ? '+' : ''}${results.w1_odds}`;
            if (betTeam1Payout100) {
                const odds1 = results.w1_odds;
                // *** FIX: Payout calculation text for favorites ***
                betTeam1Payout100.innerText = odds1 > 0 ? `100 Gold wins ${odds1.toFixed(0)} Gold` : `Bet ${Math.abs(odds1).toFixed(0)} Gold to win 100 Gold`;
            }
            if (betOnTeam1Btn) betOnTeam1Btn.disabled = false;

            // *** FIX: Populate images in the modal ***
            if (betTeam2Img) betTeam2Img.src = results.w2_image;
            if (betTeam2Name) betTeam2Name.innerText = results.w2_name;
            if (betTeam2WinPct) betTeam2WinPct.innerText = `Win: ${results.w2_win_percent.toFixed(1)}%`;
            if (betTeam2Odds) betTeam2Odds.innerText = `Odds: ${results.w2_odds > 0 ? '+' : ''}${results.w2_odds}`;
            if (betTeam2Payout100) {
                const odds2 = results.w2_odds;
                // *** FIX: Payout calculation text for favorites ***
                betTeam2Payout100.innerText = odds2 > 0 ? `100 Gold wins ${odds2.toFixed(0)} Gold` : `Bet ${Math.abs(odds2).toFixed(0)} Gold to win 100 Gold`;
            }
            if (betOnTeam2Btn) betOnTeam2Btn.disabled = false;
            // --- End Populate ---

            if (betOnTeam1Btn) betOnTeam1Btn.classList.remove('selected');
            if (betOnTeam2Btn) betOnTeam2Btn.classList.remove('selected');
            bettingState.betOn = null;
            if (betAmountInput) betAmountInput.value = '';
            if (potentialPayoutDisplay) potentialPayoutDisplay.innerText = '0 Gold';
            if (confirmBetBtn) confirmBetBtn.disabled = true;

            bettingModal.classList.remove('hidden');
        } catch (error) {
            console.error('Odds Calculation Error:', error);
            alert(`Could not calculate betting odds: ${error.message}`);
        } finally {
            if (openBettingModalBtn) {
                openBettingModalBtn.disabled = false; openBettingModalBtn.innerText = 'Place Bet';
            }
        }
    }

    function updatePayout() {
        if (!betAmountInput || !potentialPayoutDisplay || !confirmBetBtn) return;
        const amount = parseFloat(betAmountInput.value) || 0;
        bettingState.amount = amount;
        let payout = 0;
        let oddsObj = null;
        if (bettingState.betOn === 'team1') oddsObj = bettingState.team1?.odds;
        else if (bettingState.betOn === 'team2') oddsObj = bettingState.team2?.odds;

        if (oddsObj && amount > 0 && oddsObj.odds) {
            const oddsValue = parseFloat(oddsObj.odds);
            if (!isNaN(oddsValue)) {
                if (oddsValue > 0) payout = amount * (oddsValue / 100);
                else payout = amount * (100 / Math.abs(oddsValue));
                bettingState.potentialPayout = payout;
                potentialPayoutDisplay.innerText = `${payout.toFixed(0)} Gold`;
            } else {
                bettingState.potentialPayout = 0; potentialPayoutDisplay.innerText = '0 Gold';
            }
        } else {
            bettingState.potentialPayout = 0; potentialPayoutDisplay.innerText = '0 Gold';
        }
        confirmBetBtn.disabled = !(amount > 0 && bettingState.betOn);
    }

    // --- EVENT LISTENERS ---
    if (rosterSearch) rosterSearch.addEventListener('input', filterAndSortRoster);
    if (rosterSort) rosterSort.addEventListener('change', filterAndSortRoster);

    simButtons.forEach(btn => btn.addEventListener('click', (event) => {
        event.preventDefault();
        const simCount = parseInt(btn.dataset.count, 10);
        // *** FIX: Do not run a single sim here. This is for BULK only. ***
        if (!isNaN(simCount) && simCount > 1) {
            runSimulation(simCount);
        } else if (simCount === 1) {
             // *** FIX: Run the single-bet match function instead ***
            // This assumes a 0-dollar bet if clicking "Simulate Match"
            bettingState.amount = 0;
            bettingState.betOn = null;
            runSingleBetMatch();
        }
    }));

    // REPLICATION: Update Reset Button Logic
    if (resetMatchBtn) {
        resetMatchBtn.addEventListener('click', () => {
            console.log("Reset clicked.");
            teamState = { team1: [], team2: [] };
            dropzones.forEach(zone => {
                if (zone) {
                    zone.querySelectorAll('.wrestler-card-team').forEach(card => zone.removeChild(card));
                }
            });
            allRosterCards.forEach(card => {
                card.classList.remove('in-use', 'opacity-50', 'pointer-events-none');
                card.draggable = true;
            });
            updateAllPlaceholders();
            updateSimButtonState();
        });
    }

    if (randomMatchBtn) randomMatchBtn.addEventListener('click', () => {
        console.log("Random Match clicked.");
        if (resetMatchBtn) resetMatchBtn.click(); // Clear teams first

        let availableCards = allRosterCards.filter(card => !card.classList.contains('hidden') && !card.classList.contains('in-use')); // Only visible and not in use
        console.log(`Found ${availableCards.length} visible cards for random.`);

        for (let i = availableCards.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [availableCards[i], availableCards[j]] = [availableCards[j], availableCards[i]];
        }
        const teamSize = 1; // 1v1
        if (availableCards.length >= teamSize * 2) {
            // Add to Team 1
            const team1Card = availableCards[0];
            const team1Data = { ...team1Card.dataset };
            const team1NewCard = document.createElement('div');
            team1NewCard.className = 'wrestler-card-team';
            team1NewCard.dataset.id = team1Data.id;
            team1NewCard.innerHTML = getTeamCardHTML(team1Data);
            team1Dropzone.appendChild(team1NewCard);
            teamState.team1.push(team1Data);
            team1Card.classList.add('in-use'); team1Card.draggable = false;

            // Add to Team 2
            const team2Card = availableCards[1];
            const team2Data = { ...team2Card.dataset };
            const team2NewCard = document.createElement('div');
            team2NewCard.className = 'wrestler-card-team';
            team2NewCard.dataset.id = team2Data.id;
            team2NewCard.innerHTML = getTeamCardHTML(team2Data);
            team2Dropzone.appendChild(team2NewCard);
            teamState.team2.push(team2Data);
            team2Card.classList.add('in-use'); team2Card.draggable = false;

            updateAllPlaceholders();
            updateSimButtonState();
        } else {
            alert("Not enough visible wrestlers for a random match.");
        }
    });

    // Modal Close Buttons
    if (closeBulkModalBtn) closeBulkModalBtn.addEventListener('click', () => bulkModal?.classList.add('hidden'));
    if (closeSingleModalBtn) closeSingleModalBtn.addEventListener('click', () => {
        singleMatchModal?.classList.add('hidden');
        if (typeof window.clearSimulationInterval === 'function') window.clearSimulationInterval();
    });
    if (closeBettingModalBtn) closeBettingModalBtn.addEventListener('click', () => bettingModal?.classList.add('hidden'));

    // Betting Modal Interactions
    if (openBettingModalBtn) openBettingModalBtn.addEventListener('click', openBettingModal);
    if (betAmountInput) betAmountInput.addEventListener('input', updatePayout);
    if (betOnTeam1Btn) betOnTeam1Btn.addEventListener('click', () => {
        bettingState.betOn = 'team1';
        betOnTeam1Btn.classList.add('selected'); // Use 'selected' class
        betOnTeam2Btn?.classList.remove('selected');

        updatePayout();
    });
    if (betOnTeam2Btn) betOnTeam2Btn.addEventListener('click', () => {
        bettingState.betOn = 'team2';
        betOnTeam2Btn.classList.add('selected'); // Use 'selected' class
        betOnTeam1Btn?.classList.remove('selected');
        updatePayout();
    });

    // *** FIX: This function now calls the *correct* single-match route ***
    async function runSingleBetMatch() {
        const team1Data = getTeamData('team1');
        const team2Data = getTeamData('team2');
        const team1Ids = team1Data.map(d => d.id).filter(id => id);
        const team2Ids = team2Data.map(d => d.id).filter(id => id);

        if (team1Ids.length === 0 || team2Ids.length === 0) {
             alert("Error: Team data lost. Please reset and try again.");
             return;
        }

        // Show loading state on modal
        if (singleMatchModal) {
            matchLog.innerHTML = '<p class="text-yellow-400 font-semibold text-center">Simulating match...</p>';
            displaySingleMatch([], team1Data, team2Data); // Show modal with just wrestler info
        }

        try {
            // *** FIX: Call the '/api/simulator/run-single' route ***
            const fetchUrl = `${baseUrl}/api/simulator/run-single`;
            const body = JSON.stringify({
                wrestler1_id: team1Ids[0],
                wrestler2_id: team2Ids[0],
                bet_amount: bettingState.amount,
                bet_on_team: bettingState.betOn
            });

            const response = await fetch(fetchUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: body
            });

            if (!response.ok) {
                 let errorMsg = `HTTP error! Status: ${response.status}`;
                 try { const err = await response.json(); errorMsg = err.error || errorMsg; } catch (e) {}
                 throw new Error(errorMsg);
            }

            const results = await response.json();
            if (!results.success) {
                throw new Error(results.error || "Simulation failed");
            }

            // `results` is {success: true, log: [...], bet_won: ...}
            // Re-call displaySingleMatch, this time with the log and bet data
            displaySingleMatch(results.log, team1Data, team2Data, results);

        } catch (error) {
            console.error('Single Match Error:', error);
            alert(`Error running match: ${error.message}`);
             if (singleMatchModal) singleMatchModal.classList.add('hidden'); // Hide modal on error
        } finally {
            // Clear bet state after a match is run
            bettingState.amount = 0;
            bettingState.betOn = null;
        }
    }

    if(confirmBetBtn) confirmBetBtn.addEventListener('click', (event) => {
        event.preventDefault();
        const amount = parseFloat(betAmountInput?.value) || 0;
        if (amount <= 0 || !bettingState.betOn) {
            alert("Please enter a valid bet amount and select a team.");
            return;
        }
        console.log(`Betting ${bettingState.amount} Gold on ${bettingState.betOn}`);
        if (bettingModal) bettingModal.classList.add('hidden');
        // *** FIX: Call the new function for running a single bet match ***
        runSingleBetMatch();
    });

    // Tabs
    if (tabWrestlers && tabTagTeams && rosterParent && tagTeamParent && rosterControls) {
        // Already handled by the inline script in .twig, but keeping for safety
    }
    else { console.warn("Tab elements or content panes not found."); }

    // --- Initial Page Load Setup ---
    console.log("Running initial setup...");
    if (rosterParent && team1Dropzone && team2Dropzone) {
        initializeDragDrop(); // Sets up drag *from* roster
        updateAllPlaceholders();
        updateSimButtonState();
        filterAndSortRoster();
        console.log("Initial setup complete.");
    } else {
        console.error("Initialization failed: Required elements not found.");
    }
});