// combat.js

import { getRandomInt } from './data.js';
import { updateMatchLog, triggerDamageAnimation, renderWrestlerInModalDisplay } from './dom.js';
import { parseHeightToInches } from './utils.js';

// --- Crowd Reaction Messages ---
const crowdReactions = {
    bigDamage: [
        "The crowd ROARS!",
        "WHAT A SHOT! The arena is buzzing!",
        "Fans are on their feet after that one!",
        "A collective gasp from the audience!",
        "The crowd is going wild!",
        "UNBELIEVABLE! The fans can't believe it!"
    ],
    comeback: [
        "The crowd is rallying behind them!",
        "A surge of energy from the audience!",
        "The fans sense a comeback!",
        "The momentum is shifting, and the crowd loves it!",
        "The arena is electric as they fight back!"
    ],
    finisherAttempt: [
        "The crowd is on the edge of their seats!",
        "FINISHER ALERT! The tension is palpable!",
        "The arena holds its breath!",
        "The crowd knows what's coming next!",
        "Anticipation fills the air!"
    ],
    finisherHit: [
        "BOOM! The crowd EXPLODES!",
        "THE FINISHER CONNECTS! The roof just blew off!",
        "UNBELIEVABLE! The crowd is in a frenzy!",
        "A thunderous ovation for that finisher!",
        "The..."
    ]
};

/**
 * Triggers a crowd reaction based on the event.
 * @param {string} reactionType - The type of reaction ('bigDamage', 'comeback', etc.).
 * @param {boolean} isBulkSimulation - If true, no logs are generated.
 */
function triggerCrowdReaction(reactionType, isBulkSimulation = false) {
    if (isBulkSimulation) {
        return;
    }
    const reactions = crowdReactions[reactionType];
    if (reactions && reactions.length > 0) {
        const reaction = reactions[getRandomInt(0, reactions.length - 1)];
        updateMatchLog(reaction, 'crowd');
    }
}

/**
 * Updates the match momentum based on a wrestler's action.
 * @param {object} matchStateObj - The current state of the match.
 * @param {object} wrestlerId - The ID of the wrestler who performed the action.
 * @param {number} amount - The amount to change the momentum by.
 * @param {boolean} isBulkSimulation - If true, no logs are generated.
 */
function updateMomentum(matchStateObj, wrestlerId, amount, isBulkSimulation = false) {
    if (!matchStateObj.momentum) {
        matchStateObj.momentum = {}; // Ensure momentum object exists
    }
    if (matchStateObj.momentum[wrestlerId] === undefined) {
        matchStateObj.momentum[wrestlerId] = 0; // Initialize momentum if it doesn't exist
    }

    matchStateObj.momentum[wrestlerId] += amount;
    matchStateObj.momentum[wrestlerId] = Math.max(0, matchStateObj.momentum[wrestlerId]); // Momentum can't be negative

    if (!isBulkSimulation) {
        // You can add logging here if momentum changes are significant.
    }
}

/**
 * Selects a random move from a wrestler's moveset based on move types.
 * @param {object} wrestler - The wrestler object.
 * @returns {object} The selected move object.
 */
function selectRandomMove(wrestler) {
    const allMoves = [
        ...(wrestler.moves.basic || []),
        ...(wrestler.moves.signature || []),
        ...(wrestler.moves.finisher || [])
    ];
    if (allMoves.length === 0) {
        // Return a default move if the wrestler has no moves
        return { name: "Basic Punch", type: "basic", damage: 5, momentumGain: 5 };
    }
    const moveIndex = getRandomInt(0, allMoves.length - 1);
    return allMoves[moveIndex];
}

/**
 * Calculates the damage a move does, factoring in wrestler stats.
 * @param {object} attacker - The attacking wrestler.
 * @param {object} defender - The defending wrestler.
 * @param {object} move - The move being used.
 * @param {object} matchStateObj - The current state of the match.
 * @param {boolean} isBulkSimulation - If true, no logs are generated.
 * @returns {number} The final calculated damage.
 */
function calculateDamage(attacker, defender, move, matchStateObj, isBulkSimulation = false) {
    let damage = move.damage;

    // Apply strength and skill bonuses
    damage += (attacker.stats.strength || 0) * 0.5;
    damage += (attacker.stats.skill || 0) * 0.2;

    // Height and Weight Class Advantage
    const attackerHeight = parseHeightToInches(attacker.height);
    const defenderHeight = parseHeightToInches(defender.height);
    const attackerWeight = parseInt(attacker.weight, 10);
    const defenderWeight = parseInt(defender.weight, 10);

    if (attackerHeight > defenderHeight) {
        damage *= 1.05; // 5% damage bonus for being taller
    }

    if (attackerWeight > defenderWeight) {
        damage *= 1.1; // 10% damage bonus for being heavier
    }

    // Apply finisher and signature bonuses
    if (move.type === 'finisher') {
        damage *= 1.75; // 75% damage bonus
        if (!isBulkSimulation) {
            triggerCrowdReaction('finisherAttempt', isBulkSimulation);
        }
    } else if (move.type === 'signature') {
        damage *= 1.3; // 30% damage bonus
    }

    // Momentum bonus
    const momentum = matchStateObj.momentum[attacker.id] || 0;
    damage *= (1 + momentum / 100);

    const COMEBACK_HP_THRESHOLD_PERCENT = 0.25;
    const COMEBACK_DAMAGE_BONUS_PERCENT = 0.25;

    // Make sure we have valid initial HP for the check
    const attackerInitialHp = matchStateObj.initialHp[attacker.id];
    if (attackerInitialHp && attackerInitialHp > 0) {
        const actualAttackerCurrentHp = matchStateObj.currentHp[attacker.id];
        if ((actualAttackerCurrentHp / attackerInitialHp) <= COMEBACK_HP_THRESHOLD_PERCENT) {
            damage *= (1 + COMEBACK_DAMAGE_BONUS_PERCENT);
            if (!isBulkSimulation) {
                updateMatchLog(`${attacker.name} is fired up and delivers a powerful comeback blow!`, 'action');
                triggerCrowdReaction('comeback', isBulkSimulation);
            }
        }
    }

    const toughnessReduction = (defender.stats.toughness || 0) * 0.004;
    const resilienceReduction = (defender.stats.resilience || 0) * 0.002;

    damage *= (1 - (toughnessReduction + resilienceReduction));

    const finalDamage = Math.max(0, Math.round(damage));

    const BIG_DAMAGE_THRESHOLD_PERCENT = 0.15;
    const defenderInitialHp = matchStateObj.initialHp[defender.id];
    if (!isBulkSimulation && defenderInitialHp && defenderInitialHp > 0 && (finalDamage / defenderInitialHp) >= BIG_DAMAGE_THRESHOLD_PERCENT) {
        triggerCrowdReaction('bigDamage', isBulkSimulation);
    }

    return finalDamage;
}

/**
 * Advances the match state by one turn. This is the core match logic.
 * @param {object} matchStateObj - The current state of the match.
 * @param {object} selectedWrestlers - The wrestlers involved in the match.
 * @param {string} matchType - The type of match ('single' or 'tagTeam').
 * @param {function} wrestlerLookupFn - A function to look up wrestler objects by ID.
 * @param {boolean} isBulkSimulation - If true, no DOM updates or logs are generated.
 */
export function advanceTurn(matchStateObj, selectedWrestlers, matchType, wrestlerLookupFn, isBulkSimulation = false) {
    if (!matchStateObj || !selectedWrestlers) {
        console.error("Invalid match state or wrestlers provided.");
        return;
    }

    // Ensure match state properties exist before trying to access them
    if (!matchStateObj.initialHp) {
        matchStateObj.initialHp = {};
    }
    if (!matchStateObj.currentHp) {
        matchStateObj.currentHp = {};
    }
    if (!matchStateObj.momentum) {
        matchStateObj.momentum = {};
    }

    // Initialize the state for all wrestlers if it's missing.
    const allWrestlers = Object.values(selectedWrestlers).filter(w => w);
    allWrestlers.forEach(wrestler => {
        // Fallback to wrestler's base HP if initial HP is not set in the match state
        if (matchStateObj.initialHp[wrestler.id] === undefined) {
            matchStateObj.initialHp[wrestler.id] = wrestler.baseHp;
            console.warn(`[advanceTurn] Initial HP for ${wrestler.name} not found in matchStateObj. Using wrestler's base HP as a fallback.`);
        }
        if (matchStateObj.currentHp[wrestler.id] === undefined) {
            matchStateObj.currentHp[wrestler.id] = matchStateObj.initialHp[wrestler.id];
        }
        if (matchStateObj.momentum[wrestler.id] === undefined) {
            matchStateObj.momentum[wrestler.id] = 0;
        }
    });

    // Determine the active wrestlers for the current turn based on match type
    let activeWrestlers = [];
    if (matchType === 'single') {
        const player1 = selectedWrestlers.player1;
        const player2 = selectedWrestlers.player2;

        // Ensure wrestler objects are available before proceeding
        if (!player1 || !player2) {
            console.warn("Wrestlers not selected for the match.");
            return;
        }
        activeWrestlers.push(player1, player2);
    } else if (matchType === 'tagTeam') {
        const team1 = [selectedWrestlers.team1Player1, selectedWrestlers.team1Player2].filter(w => w);
        const team2 = [selectedWrestlers.team2Player1, selectedWrestlers.team2Player2].filter(w => w);
        activeWrestlers = [...team1, ...team2];
        
    } else {
        console.error("Unknown match type.");
        return;
    }

    // Filter out wrestlers with 0 or less HP
    const availableWrestlers = activeWrestlers.filter(wrestler => wrestler && matchStateObj.currentHp[wrestler.id] > 0);

    // If there are less than two wrestlers available, the match is over.
    if (availableWrestlers.length < 2) {
        if (!isBulkSimulation) {
            console.log("Match has ended.");
        }
        return;
    }
    
    // Randomly select attacker and defender from available wrestlers
    const attacker = availableWrestlers[getRandomInt(0, availableWrestlers.length - 1)];
    
    let defender;
    do {
        defender = availableWrestlers[getRandomInt(0, availableWrestlers.length - 1)];
    } while (defender.id === attacker.id);

    // Select a random move for the attacker
    const move = selectRandomMove(attacker);

    // Update stamina and momentum before the move
    // stamina logic here... (not implemented in this version)
    
    // Calculate damage and update health
    const damage = calculateDamage(attacker, defender, move, matchStateObj, isBulkSimulation);
    matchStateObj.currentHp[defender.id] -= damage;

    // Update momentum based on the move's success
    updateMomentum(matchStateObj, attacker.id, move.momentumGain, isBulkSimulation);

    if (!isBulkSimulation) {
        updateMatchLog(`${attacker.name} uses ${move.name} on ${defender.name}! It deals ${damage} damage.`, 'action');
        updateWrestlerHealthDisplay(defender, matchStateObj.currentHp[defender.id]);
        triggerDamageAnimation(defender.id);
        
        // Check for winner after the move
        if (matchStateObj.currentHp[defender.id] <= 0) {
            updateMatchLog(`${defender.name} has been knocked out!`, 'knockout');
        }
    }
}
