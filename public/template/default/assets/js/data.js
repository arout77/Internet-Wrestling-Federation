// data.js

// Function to get a random integer within a range
export const getRandomInt = (min, max) => {
    min = Math.ceil(min);
    max = Math.floor(max);
    return Math.floor(Math.random() * (max - min + 1)) + min;
};

// Base URL for wrestler images
const IMAGE_BASE_URL = baseUrl + 'public/media/images/';

// Global variable to store all moves data once fetched
let allMovesData = [];

/**
 * Function to fetch moves data from the PHP backend.
 * @returns {Promise<Array<Object>>} A promise that resolves with an array of move objects.
 */
async function fetchMovesData() {
    try {
        const response = await fetch(baseUrl + 'api/get_moves'); // Path to your PHP script for moves
        if (!response.ok) {
            console.error(`[fetchMovesData] HTTP error! Status: ${response.status} - ${response.statusText}`);
            const errorText = await response.text();
            console.error('[fetchMovesData] Server response:', errorText);
            throw new Error(`Network response was not ok: ${response.statusText}`);
        }
        const data = await response.json();
        if (data.error) {
            console.error("[fetchMovesData] Error fetching moves from PHP:", data.error);
            return [];
        }
        return data;
    } catch (error) {
        console.error("[fetchMovesData] Could not fetch moves data:", error);
        return [];
    }
}

// Function to fetch wrestler data from the PHP backend
async function fetchWrestlerData() {
    try {
        const response = await fetch(baseUrl + 'api/get_all_wrestlers'); // Path to your PHP script for wrestlers
        if (!response.ok) {
            console.error(`[fetchWrestlerData] HTTP error! Status: ${response.status} - ${response.statusText}`);
            const errorText = await response.text();
            console.error('[fetchWrestlerData] Server response:', errorText);
            throw new Error(`Network response was not ok: ${response.statusText}`);
        }
        const data = await response.json();
        if (data.error) {
            console.error("[fetchWrestlerData] Error fetching wrestlers from PHP:", data.error);
            return [];
        }
        return data;
    } catch (error) {
        console.error("[fetchWrestlerData] Could not fetch wrestler data:", error);
        return [];
    }
}

/**
 * Parses raw wrestler data and enriches it with calculated stats and move details.
 * @param {Array<object>} rawWrestlers - Array of raw wrestler objects from the backend.
 * @param {Array<object>} allMoves - Array of all available move objects.
 * @returns {Array<object>} Array of enriched wrestler objects.
 */
async function processWrestlerData(rawWrestlers, allMoves) {
    if (!rawWrestlers || rawWrestlers.length === 0) {
        console.warn("[processWrestlerData] No raw wrestler data to process.");
        return [];
    }
    if (!allMoves || allMoves.length === 0) {
        console.warn("[processWrestlerData] No move data available to enrich wrestlers.");
        // We can still return wrestlers without moves if moves are not critical for initial display
    }

    // Helper to get move details by name
    const getMoveDetails = (moveName) => {
        let nameToFind;

        // Check if moveName is an object and has a 'move_name' property
        if (typeof moveName === 'object' && moveName !== null && moveName.move_name) {
            nameToFind = moveName.move_name.trim().toLowerCase();
        } 
        // Check if moveName is a string
        else if (typeof moveName === 'string') {
            nameToFind = moveName.trim().toLowerCase();
        } 
        // If it's neither, we can't process it
        else {
            console.warn('Invalid move name format:', moveName);
            return {
                name: 'Unknown Move',
                damage: 0,
                stamina_cost: 0,
                type: 'Basic'
            }; // Return a default/error object
        }

        const move = allMoves.find(m => m.move_name.toLowerCase() === nameToFind);
        if (!move) {
            // console.warn(`Move not found: "${nameToFind}"`);
            return {
                name: nameToFind,
                damage: 0,
                stamina_cost: 0,
                type: 'Basic'
            }; // Return a default object for unknown moves
        }

        return {
            name: move.move_name,
            damage: (parseInt(move.min_damage, 10) + parseInt(move.max_damage, 10)) / 2,
            stamina_cost: parseInt(move.stamina_cost, 10) || 0,
            type: move.type,
        };
    };

    return rawWrestlers.map(wrestler => {
        // Append .webp to the image filename
        const imageUrl = IMAGE_BASE_URL + (wrestler.image ? `${wrestler.image}.webp` : 'default.webp'); // Fallback image

        // Ensure stats are parsed as numbers and have fallbacks
        const stats = {
            strength: parseInt(wrestler.strength) || 0,
            technicalAbility: parseInt(wrestler.technicalAbility) || 0,
            brawlingAbility: parseInt(wrestler.brawlingAbility) || 0,
            stamina: parseInt(wrestler.stamina) || 0,
            aerialAbility: parseInt(wrestler.aerialAbility) || 0,
            toughness: parseInt(wrestler.toughness) || 0,
            reversalAbility: parseInt(wrestler.reversalAbility) || 0,
            submissionDefense: parseInt(wrestler.submissionDefense) || 0, // Ensure this is parsed as number
            staminaRecoveryRate: parseInt(wrestler.staminaRecoveryRate) || 0
        };

        // Define relevant stats for overall calculation (excluding reversalAbility and submissionDefense)
        const relevantStats = [
            stats.strength,
            stats.technicalAbility,
            stats.brawlingAbility,
            stats.stamina,
            stats.aerialAbility,
            stats.toughness
        ];

        // Apply the "minimum 50" rule for this calculation
        const adjustedStats = relevantStats.map(stat => Math.max(50, stat));

        // Calculate the sum of the adjusted stats
        const sumOfStats = adjustedStats.reduce((sum, stat) => sum + stat, 0);
        console.log(`Sum of adjusted stats for ${wrestler.name}:`, sumOfStats);

        // Calculate initial overall, ensuring division by non-zero number
        let overall = Math.round(sumOfStats / Math.max(1, adjustedStats.length));
        console.log(`Initial overall for ${wrestler.name}:`, overall);

        // Check for 5 or more stats of 80 or higher from the ORIGINAL relevantStats
        const highStatCount = relevantStats.filter(stat => stat >= 85).length;
        console.log(`High stat count for ${wrestler.name}:`, highStatCount);

        // Apply 5% boost if condition is met
        if (highStatCount >= 5) {
            overall = Math.round(overall * 1.05);
        }

        // Ensure baseHp is a number
        const baseHp = parseFloat(wrestler.baseHp) || 1000; // Default to 1000 if not a valid number

        let parsedMoves = {};
        // FIX: Directly use the wrestler.moves object as it's already parsed.
        // The check for string is a good safeguard.
        if (typeof wrestler.moves === 'string') {
            try {
                parsedMoves = JSON.parse(wrestler.moves);
            } catch (e) {
                console.error(`Error parsing moves JSON for ${wrestler.name}:`, e);
                parsedMoves = {}; // Default to empty object on error
            }
        } else {
            parsedMoves = wrestler.moves; // Use the object directly
        }

        const mappedMoves = {};
        for (const moveType in parsedMoves) {
            if (parsedMoves.hasOwnProperty(moveType) && Array.isArray(parsedMoves[moveType])) {
                mappedMoves[moveType] = parsedMoves[moveType].map(moveName => {
                    return getMoveDetails(moveName);
                });
            }
        }

        return {
            id: `wrestler-${wrestler.name.toLowerCase().replace(/\s/g, '-')}`,
            name: wrestler.name,
            image: imageUrl,
            height: wrestler.height || 'N/A', // Include height
            weight: wrestler.weight || 'N/A', // Include weight
            stats: stats,
            overall: overall,
            moves: mappedMoves,
            baseHp: baseHp,
            description: wrestler.description || "No description provided." // Ensure description is included
        };
    });
}


// Export a promise that resolves with the processed wrestler data
export const wrestlers = (async () => {
    const rawWrestlers = await fetchWrestlerData();
    allMovesData = await fetchMovesData(); // Ensure moves are fetched globally
    return processWrestlerData(rawWrestlers, allMovesData);
})();