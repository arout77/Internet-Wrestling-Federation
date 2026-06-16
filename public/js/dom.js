/**
 * Renders the wrestler roster in the DOM.
 * @param {Array<object>} wrestlers - The array of wrestler objects to render.
 */
export function renderRoster(wrestlers) {
    const container = document.getElementById('roster-container');
    if (!container) return;
    container.innerHTML = '';
    wrestlers.forEach(wrestler => {
        container.appendChild(createWrestlerCard(wrestler));
    });
}

/**
 * Renders the tag team roster in the DOM.
 * @param {Array<object>} teams - The array of tag team objects.
 * @param {Array<object>} allWrestlers - The array of all wrestler objects to look up members.
 */
export function renderTagTeamRoster(teams, allWrestlers) {
    const container = document.getElementById('tag-team-roster-container');
    if (!container) return;
    container.innerHTML = '';
    teams.forEach(team => {
        container.appendChild(createTagTeamCard(team, allWrestlers));
    });
}

/**
 * Creates a DOM element for a single tag team card.
 * @param {object} team - The tag team object.
 * @param {Array<object>} allWrestlers - The array of all wrestler objects.
 * @returns {HTMLElement} - The created tag team card element.
 */
export function createTagTeamCard(team, allWrestlers) {
    const card = document.createElement('div');
    card.className = 'tag-team-card bg-gray-800 rounded-lg p-4 text-center text-white';
    
    const memberNames = team.members.map(memberId => {
        const wrestler = allWrestlers.find(w => w.wrestler_id == memberId);
        return wrestler ? wrestler.name : 'Unknown';
    }).join(' & ');

    card.innerHTML = `
        <img src="${baseUrl}/public/images/tagteams/${team.team_image}.webp" alt="${team.team_name}" class="w-24 h-24 mx-auto rounded-lg object-cover mb-2">
        <h3 class="font-bold">${team.team_name}</h3>
        <p class="text-xs text-gray-400 mt-1">${memberNames}</p>
    `;
    return card;
}


/**
 * Creates a DOM element for a single wrestler card.
 * @param {object} wrestler - The wrestler object.
 * @returns {HTMLElement} - The created wrestler card element.
 */
export function createWrestlerCard(wrestler) {
    const card = document.createElement('div');
    card.className = 'wrestler-card bg-gray-800 rounded-lg p-4 text-center text-white cursor-pointer hover:bg-gray-700 transition flex flex-col';
    card.dataset.wrestlerId = wrestler.wrestler_id;

    const overall = wrestler.overall || 0;

    const badgeColors = [
        'bg-blue-900 text-blue-300',
        'bg-green-900 text-green-300',
        'bg-red-900 text-red-300',
        'bg-purple-900 text-purple-300'
    ];

    let traitsHtml = '<div class="h-5 mb-2">';
    if (wrestler.traits && wrestler.traits.length > 0) {
        traitsHtml += wrestler.traits.map((trait, index) => {
            const colorClass = badgeColors[index % badgeColors.length];
            return `<span class="${colorClass} text-xs font-semibold mr-2 px-2.5 py-0.5 rounded">${trait}</span>`;
        }).join('');
    } else {
        traitsHtml += '<span class="text-xs text-gray-500">No Traits</span>';
    }
    traitsHtml += '</div>';

    const attributesHtml = `
        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-left mt-2">
            <div class="flex justify-between">
                <span class="font-semibold text-gray-400">Strength</span>
                <span class="text-white">${wrestler.strength || 0}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-semibold text-gray-400">Technical</span>
                <span class="text-white">${wrestler.technicalAbility || 0}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-semibold text-gray-400">Aerial</span>
                <span class="text-white">${wrestler.aerialAbility || 0}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-semibold text-gray-400">Brawling</span>
                <span class="text-white">${wrestler.brawlingAbility || 0}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-semibold text-gray-400">Stamina</span>
                <span class="text-white">${wrestler.stamina || 0}</span>
            </div>
            <div class="flex justify-between">
                <span class="font-semibold text-gray-400">Toughness</span>
                <span class="text-white">${wrestler.toughness || 0}</span>
            </div>
        </div>
    `;

    card.innerHTML = `
        <div class="flex-grow">
            <img src="${baseUrl}/public/images/${wrestler.image}.webp" alt="${wrestler.name}" class="w-36 h-36 mx-auto rounded-full object-cover mb-2">
            <h3 class="font-bold">${wrestler.name}</h3>
            <p class="text-yellow-400">Overall: ${overall}</p>
        </div>
        <div>
            <div>${traitsHtml}</div>
            <div class="mt-8">${attributesHtml}</div>
        </div>
    `;
    return card;
}

/**
 * Updates a match card slot with a selected wrestler's data.
 * @param {object} wrestler - The wrestler object.
 * @param {number} index - The slot index (0 or 1).
 */
export function updateMatchCard(wrestler, index) {
    const slot = document.getElementById(`matchup-slot-${index}`) || document.getElementById(`wrestler${index}-slot`);

    if (!slot) return;
    
    const description = wrestler.description || 'No description available.';
    const truncatedDescription = description.length > 120 ? description.substring(0, 120) + '...' : description;

    slot.innerHTML = `
        <div class="flex items-center space-x-4 text-left p-4">
            <div class="flex-shrink-0">
                <img src="${baseUrl}/public/images/${wrestler.image}.webp" alt="${wrestler.name}" class="w-36 h-36 rounded-full object-cover border-4 border-yellow-600">
            </div>
            <div>
                <h3 class="text-2xl font-bold text-white">${wrestler.name}</h3>
                <p class="text-sm text-gray-300 mt-1">${truncatedDescription}</p>
            </div>
        </div>
    `;
}

/**
 * Clears the wrestler selection from the match card.
 */
export function clearWrestlerSelection() {
    for (let i = 1; i <= 2; i++) {
        const slot = document.getElementById(`matchup-slot-${i}`) || document.getElementById(`wrestler${i}-slot`);

        if(slot) {
            slot.innerHTML = `<div class="text-gray-500 text-center">Select Wrestler ${i}</div>`;
        }
    }
}

/**
 * Shows the winner modal with the victor's data.
 * @param {object} winner - The winning wrestler's object.
 * @param {string} victoryMethod - The method of victory (e.g., 'Pinfall').
 */
export function showWinnerModal(winner, victoryMethod) {
    const modal = document.getElementById('winner-modal');
    if (!modal) return;

    document.getElementById('winner-name').textContent = winner.name;
    document.getElementById('victory-method').textContent = `by ${victoryMethod || 'Unknown Method'}`;
    document.getElementById('winner-img').src = `${baseUrl}/public/images/${winner.image}.webp`;
    
    modal.classList.remove('hidden');
}


/**
 * Sets up the simulation modal with wrestler data.
 * @param {object} wrestler1 - The first wrestler.
 * @param {object} wrestler2 - The second wrestler.
 */
export function setupSimulationModal(wrestler1, wrestler2) {
    document.getElementById('sim-wrestler-1-name').textContent = wrestler1.name;
    document.getElementById('sim-wrestler-1-img').src = `${baseUrl}/public/images/${wrestler1.image}.webp`;
    document.getElementById('sim-wrestler-2-name').textContent = wrestler2.name;
    document.getElementById('sim-wrestler-2-img').src = `${baseUrl}/public/images/${wrestler2.image}.webp`;

    updateHealthBar(1, 100);
    updateHealthBar(2, 100);
    updateStaminaBar(1, 100);
    updateStaminaBar(2, 100);
    updateMomentumBar(1, 25);
    updateMomentumBar(2, 25);
}

/**
 * Renders the results of a bulk simulation.
 * @param {object} results - The results object from the API.
 * @param {Array<object>} wrestlers - The two wrestlers who were simulated.
 */
export function renderBulkResults(results, wrestlers) {
    const container = document.getElementById('bulk-results-container');
    const content = document.getElementById('bulk-results-content');
    
    try {
        if (!container || !content) { throw new Error('Bulk results DOM elements not found.'); }
        if (!results || !wrestlers || wrestlers.length < 2) { throw new Error('Invalid data passed to renderBulkResults.'); }
        const [w1, w2] = wrestlers;
        const w1Wins = results.wins[w1.name] || 0;
        const w2Wins = results.wins[w2.name] || 0;
        const draws = results.wins['draw'] || 0;
        const w1Prob = (results.probabilities[w1.name] * 100).toFixed(1);
        const w2Prob = (results.probabilities[w2.name] * 100).toFixed(1);
        content.innerHTML = `
            <h3 class="text-2xl font-bold mb-4 text-white">Matchup Odds</h3>
            <div class="grid grid-cols-2 gap-4 text-center">
                <div>
                    <h4 class="font-bold text-lg">${w1.name}</h4>
                    <p class="text-3xl font-bold text-green-400">${w1Prob}%</p>
                    <p class="text-sm text-gray-400">${w1Wins} wins</p>
                    <p class="text-xl font-bold text-yellow-400 mt-2">Moneyline odds: <br>${results.moneyline[w1.name]}</p>
                </div>
                <div>
                    <h4 class="font-bold text-lg">${w2.name}</h4>
                    <p class="text-3xl font-bold text-green-400">${w2Prob}%</p>
                    <p class="text-sm text-gray-400">${w2Wins} wins</p>
                    <p class="text-xl font-bold text-yellow-400 mt-2">Moneyline odds: <br>${results.moneyline[w2.name]}</p>
                </div>
            </div>
            <p class="text-center mt-4 text-gray-400 text-sm">Based on ${w1Wins + w2Wins + draws} simulations.</p>
        `;
        container.classList.remove('hidden');
    } catch (error) {
        console.error('CRITICAL ERROR in renderBulkResults:', error);
    }
}

/**
 * Updates a momentum bar in the simulation modal.
 * @param {number} wrestlerIndex - 1 or 2.
 * @param {number} percentage - The momentum percentage (0-100).
 */
export function updateMomentumBar(wrestlerIndex, percentage) {
    // --- DIAGNOSTIC STEP 2 ---
    const barId = `sim-wrestler-${wrestlerIndex}-momentum`;
    const bar = document.getElementById(barId);
    
    if (bar) {
        bar.style.width = `${percentage}%`;
    }
}

/**
 * Updates a health bar in the simulation modal.
 * @param {number} wrestlerIndex - 1 or 2.
 * @param {number} percentage - The health percentage (0-100).
 */
export function updateHealthBar(wrestlerIndex, percentage) {
    const bar = document.getElementById(`sim-wrestler-${wrestlerIndex}-hp`);
    if (bar) {
        bar.style.width = `${percentage}%`;
        bar.classList.toggle('bg-red-600', percentage < 25);
        bar.classList.toggle('bg-yellow-500', percentage >= 25 && percentage < 50);
        bar.classList.toggle('bg-green-500', percentage >= 50);
    }
}

/**
 * Updates a stamina bar in the simulation modal.
 * @param {number} wrestlerIndex - 1 or 2.
 * @param {number} percentage - The stamina percentage (0-100).
 */
export function updateStaminaBar(wrestlerIndex, percentage) {
    const bar = document.getElementById(`sim-wrestler-${wrestlerIndex}-stamina`);
    if (bar) {
        bar.style.width = `${percentage}%`;
    }
}

/**
 * Briefly shakes a wrestler's image in the simulation modal to show impact.
 * @param {number} wrestlerIndex - 1 or 2.
 */
export function shakeWrestlerImage(wrestlerIndex) {
    const img = document.getElementById(`sim-wrestler-${wrestlerIndex}-img`);
    if (img) {
        img.classList.add('shake');
        setTimeout(() => img.classList.remove('shake'), 300);
    }
}