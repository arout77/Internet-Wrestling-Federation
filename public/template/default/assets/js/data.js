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
        // Make comparison case-insensitive and trim spaces for robustness
        const normalizedMoveName = moveName.trim().toLowerCase();
        const rawDetails = allMoves.find(move => (move.move_name || '').trim().toLowerCase() === normalizedMoveName);
        
        if (!rawDetails) {
            console.warn(`Move details not found for: ${moveName}`);
            // Return a default structure to prevent errors
            return { name: moveName, damage: { min: 0, max: 0 }, staminaCost: 0, description: "Move details missing.", type: "unknown" };
        }

        let minDamage = 0;
        let maxDamage = 0;

        // --- IMPORTANT FIX: Handle inconsistent min_damage format ---
        try {
            // Attempt to parse min_damage as JSON first
            const parsedDamage = JSON.parse(rawDetails.min_damage);
            if (typeof parsedDamage === 'object' && parsedDamage !== null && 'min' in parsedDamage && 'max' in parsedDamage) {
                minDamage = parseFloat(parsedDamage.min) || 0;
                maxDamage = parseFloat(parsedDamage.max) || 0;
            } else {
                // If it's not a valid JSON object with min/max, try parsing as a direct number
                minDamage = parseFloat(rawDetails.min_damage) || 0;
                maxDamage = parseFloat(rawDetails.max_damage) || 0; // Use max_damage for max if min_damage was just a number
            }
        } catch (e) {
            // If JSON parsing fails, assume it's a direct number
            minDamage = parseFloat(rawDetails.min_damage) || 0;
            maxDamage = parseFloat(rawDetails.max_damage) || 0; // Use max_damage for max
        }
        // --- END FIX ---

        const mappedMove = {
            name: rawDetails.move_name, // Corrected: Use 'move_name' for the name property
            type: rawDetails.type || "unknown", // Ensure type is always present
            damage: {
                min: minDamage, 
                max: maxDamage  
            },
            staminaCost: parseFloat(rawDetails.stamina_cost) || 0,
            description: rawDetails.move_description || "No description provided." // Corrected: Use 'move_description'
        };
        return mappedMove;
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
        try {
            // Attempt to parse the moves JSON string
            parsedMoves = JSON.parse(wrestler.moves);
        } catch (e) {
            console.error(`Error parsing moves JSON for ${wrestler.name}:`, e);
            parsedMoves = {}; // Default to empty object if parsing fails
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