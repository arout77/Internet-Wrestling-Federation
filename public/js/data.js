// public/js/data.js (Corrected)

/**
 * Fetches the complete wrestler roster from the API.
 * @returns {Promise<Array|null>} - An array of wrestler objects or null on error.
 */
export async function fetchWrestlerData() {
    try {
        const response = await fetch(`${baseUrl}/api/get_all_wrestlers`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('[fetchWrestlerData] Error:', error);
        return null;
    }
}

/**
 * Fetches all available moves from the API.
 * @returns {Promise<Array|null>} - An array of move objects or null on error.
 */
export async function fetchMovesData() {
    try {
        const response = await fetch(`${baseUrl}/api/get_moves`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('[fetchMovesData] Error:', error);
        return null;
    }
}

/**
 * Fetches all tag teams from the API.
 * @returns {Promise<object|null>} - An object containing tag team data or null on error.
 */
export async function fetchTagTeamData() {
    try {
        const response = await fetch(`${baseUrl}/api/tagTeams`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('[fetchTagTeamData] Error:', error);
        return null;
    }
}

/**
 * Runs a single match simulation.
 * @param {number} wrestler1Id - The ID of the first wrestler.
 * @param {number} wrestler2Id - The ID of the second wrestler.
 * @returns {Promise<object|null>} - The simulation result or null on error.
 */
export async function runSimulation(wrestler1Id, wrestler2Id) {
    try {
        const response = await fetch(`${baseUrl}/api/run_simulation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                wrestler1_id: wrestler1Id,
                wrestler2_id: wrestler2Id
            })
        });
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(`HTTP error! status: ${response.status} - ${errorData.error || 'Unknown server error'}`);
        }
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('[runSimulation] Error:', error);
        return null;
    }
}

/**
 * Runs a bulk simulation to calculate match odds.
 * @param {number} wrestler1Id - The ID of the first wrestler.
 * @param {number} wrestler2Id - The ID of the second wrestler.
 * @param {number} numSimulations - The number of matches to simulate.
 * @returns {Promise<object|null>} - The simulation results or null on error.
 */
export async function runBulkSimulation(wrestler1Id, wrestler2Id, numSimulations) {
    try {
        const response = await fetch(`${baseUrl}/api/run_bulk_simulation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                wrestler1_id: wrestler1Id,
                wrestler2_id: wrestler2Id,
                num_simulations: numSimulations
            })
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || `HTTP error! status: ${response.status}`);
        }
        
        // --- FINAL FIX ---
        // The API returns { success: true, data: {...} }. We must return the nested 'data' object.
        if (data.success && data.data) {
            return data.data;
        } else {
            // If the server reports success: false, treat it as an error.
            throw new Error(data.error || 'The server reported an unsuccessful simulation.');
        }
        // --- END FINAL FIX ---

    } catch (error) {
        console.error('[runBulkSimulation] Error:', error);
        return null; // Ensure null is returned on any kind of failure.
    }
}