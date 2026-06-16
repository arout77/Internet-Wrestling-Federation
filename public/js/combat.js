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
    ],
    pinAttempt: [
        "ONE... TWO... SO CLOSE!",
        "That was nearly three!",
        "The referee's hand was coming down!",
        "A last-second kickout!",
        "This crowd can't believe they kicked out!"
    ],
    submissionAttempt: [
        "They're locked in tight!",
        "Will they tap?!",
        "Grit and determination on display!",
        "Fighting to break the hold!",
        "The crowd is urging them on!"
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
 * @param {object} attacker - The attacking wrestler.
 * @param {object} defender - The defending wrestler.
 * @param {boolean} finishersOnly - If true, only finisher moves will be considered.
 * @returns {object} The selected move object.
 */
function selectRandomMove(attacker, defender, finishersOnly = false) {
    let availableMoves = [];
    const defenderWeight = parseInt(defender.weight, 10);

    if (finishersOnly) {
        availableMoves = (attacker.moves.finisher || []).filter(move => {
            return !move.weight_limit || defenderWeight <= parseInt(move.weight_limit, 10);
        });
    } else {
        const basicMoves = (attacker.moves.basic || []).filter(move => {
            return !move.weight_limit || defenderWeight <= parseInt(move.weight_limit, 10);
        });
        const signatureMoves = (attacker.moves.signature || []).filter(move => {
            return !move.weight_limit || defenderWeight <= parseInt(move.weight_limit, 10);
        });
        availableMoves = [...basicMoves, ...signatureMoves];
    }

    if (availableMoves.length === 0) {
        // Fallback to a basic punch if no other moves are available
        return { name: "Basic Punch", type: "basic", damage: 5, momentumGain: 5 };
    }
    
    const moveIndex = getRandomInt(0, availableMoves.length - 1);
    return availableMoves[moveIndex];
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

    // REVISED: Apply strength and skill bonuses (Strength nerfed, Skill buffed)
    damage += (attacker.stats.strength || 0) * 0.3; // Was 0.5
    damage += (attacker.stats.skill || 0) * 0.25;   // Was 0.2

    // REVISED: Height and Weight Class Advantage (Bonuses reduced)
    const attackerHeight = parseHeightToInches(attacker.height);
    const defenderHeight = parseHeightToInches(defender.height);
    const attackerWeight = parseInt(attacker.weight, 10);
    const defenderWeight = parseInt(defender.weight, 10);

    if (attackerHeight > defenderHeight) {
        damage *= 1.02; // Was 1.05
    }

    if (attackerWeight > defenderWeight) {
        damage *= 1.05; // Was 1.1
    }

    // Apply finisher and signature bonuses
    if (move.type === 'finisher') {
        damage *= 1.75;
        if (!isBulkSimulation) {
            triggerCrowdReaction('finisherAttempt', isBulkSimulation);
        }
    } else if (move.type === 'signature') {
        damage *= 1.3;
    }

    // Momentum bonus
    const momentum = matchStateObj.momentum[attacker.id] || 0;
    damage *= (1 + momentum / 100);

    const COMEBACK_HP_THRESHOLD_PERCENT = 0.25;
    const COMEBACK_DAMAGE_BONUS_PERCENT = 0.25;

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

    // REVISED: Toughness and Resilience reduction (Toughness buffed)
    const toughnessReduction = (defender.stats.toughness || 0) * 0.005; // Was 0.004
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
export async function advanceTurn(matchStateObj, selectedWrestlers, matchType, wrestlerLookupFn, isBulkSimulation = false) {
    if (!matchStateObj || !selectedWrestlers) {
        console.error("Invalid match state or wrestlers provided.");
        return;
    }

    // Initialize match state properties
    if (!matchStateObj.initialHp) matchStateObj.initialHp = {};
    if (!matchStateObj.currentHp) matchStateObj.currentHp = {};
    if (!matchStateObj.momentum) matchStateObj.momentum = {};
    if (!matchStateObj.stamina) matchStateObj.stamina = {};
    if (!matchStateObj.stunnedStatus) matchStateObj.stunnedStatus = {};
    if (!matchStateObj.winnerId) matchStateObj.winnerId = null;

    const allWrestlers = Object.values(selectedWrestlers).filter(w => w);
    allWrestlers.forEach(wrestler => {
        if (matchStateObj.initialHp[wrestler.id] === undefined) matchStateObj.initialHp[wrestler.id] = wrestler.baseHp;
        if (matchStateObj.currentHp[wrestler.id] === undefined) matchStateObj.currentHp[wrestler.id] = matchStateObj.initialHp[wrestler.id];
        if (matchStateObj.momentum[wrestler.id] === undefined) matchStateObj.momentum[wrestler.id] = 0;
        if (matchStateObj.stamina[wrestler.id] === undefined) matchStateObj.stamina[wrestler.id] = wrestler.stats.stamina || 100;
        if (matchStateObj.stunnedStatus[wrestler.id] === undefined) matchStateObj.stunnedStatus[wrestler.id] = 0;
    });

    if (matchStateObj.winnerId) return;

    // Stun Check
    for (const wrestler of allWrestlers) {
        if (matchStateObj.stunnedStatus[wrestler.id] > 0) {
            if (!isBulkSimulation) updateMatchLog(`${wrestler.name} is stunned and cannot make a move!`, 'info');
            matchStateObj.stunnedStatus[wrestler.id]--;
            return;
        }
    }

    const availableWrestlers = allWrestlers.filter(w => w && matchStateObj.currentHp[w.id] > 0);
    if (availableWrestlers.length < 2) return;

    // Initiative Roll
    const initiativeRolls = availableWrestlers.map(w => {
        const roll = getRandomInt(1, 100);
        const bonus = (w.stats.reversalAbility || 0) + (matchStateObj.momentum[w.id] || 0) * 0.5;
        return { wrestler: w, score: roll + bonus };
    });
    initiativeRolls.sort((a, b) => b.score - a.score);
    let attacker = initiativeRolls[0].wrestler;
    let defender = initiativeRolls[1].wrestler;

    let turnActionTaken = false;

    // Reversal Checks (Universal and Trait-Based)
    const reversalChance = (defender.stats.reversalAbility || 0) * 0.005;
    if (Math.random() < reversalChance) {
        updateMomentum(matchStateObj, defender.id, 10, isBulkSimulation);
        if (!isBulkSimulation) updateMatchLog(`${defender.name} reverses ${attacker.name}'s move!`, 'reversal');
        turnActionTaken = true;
    } else if (defender.traits.includes('Technician') && Math.random() < 0.15) {
        const staminaDrain = 5;
        matchStateObj.stamina[attacker.id] = Math.max(0, matchStateObj.stamina[attacker.id] - staminaDrain);
        if (!isBulkSimulation) {
            updateMatchLog(`${defender.name} masterfully reverses the attempt, causing ${attacker.name} to lose ${staminaDrain} stamina!`, 'reversal');
        }
        turnActionTaken = true;
    } else if (defender.traits.includes('Submission Specialist') && Math.random() < 0.1) {
        if (!isBulkSimulation) {
            updateMatchLog(`${defender.name} sees an opening and reverses the move!`, 'reversal');
        }
        [attacker, defender] = [defender, attacker];
        
        try {
            const response = await fetch('/api/submission-moves');
            const submissionMoves = await response.json();
            const move = submissionMoves[getRandomInt(0, submissionMoves.length - 1)];
            
            let momentumGain = move.momentumGain;
            if (attacker.traits.includes('Technician')) {
                momentumGain *= 1.20;
            }

            const damage = calculateDamage(attacker, defender, move, matchStateObj, isBulkSimulation);
            matchStateObj.currentHp[defender.id] -= damage;
            updateMomentum(matchStateObj, attacker.id, momentumGain, isBulkSimulation);

            if (!isBulkSimulation) {
                updateMatchLog(`${attacker.name} counters with a ${move.name}! It deals ${damage} damage.`, 'action');
            }
        } catch (error) {
            console.error("Failed to fetch submission moves:", error);
        }
        turnActionTaken = true;
    }

    // Main Action: Regular Move or Finisher
    if (!turnActionTaken) {
        const defenderHpPercent = matchStateObj.currentHp[defender.id] / matchStateObj.initialHp[defender.id];
        const canAttemptFinisher = matchStateObj.momentum[attacker.id] >= 90 && defenderHpPercent <= 0.40;

        let move;
        if (canAttemptFinisher && Math.random() < 0.5) {
            move = selectRandomMove(attacker, defender, true); // True for finishers only
            if (!isBulkSimulation) updateMatchLog(`${attacker.name} is going for the finisher!`, 'finisher');
        } else {
            move = selectRandomMove(attacker, defender);
        }

        let momentumGain = move.momentumGain;
        if (attacker.traits.includes('Technician')) {
            momentumGain *= 1.20;
            if (!isBulkSimulation) updateMatchLog(`${attacker.name}'s technical prowess increases momentum!`, 'info');
        }

        const damage = calculateDamage(attacker, defender, move, matchStateObj, isBulkSimulation);
        matchStateObj.currentHp[defender.id] -= damage;
        updateMomentum(matchStateObj, attacker.id, momentumGain, isBulkSimulation);

        if (!isBulkSimulation) updateMatchLog(`${attacker.name} uses ${move.name} on ${defender.name}! It deals ${damage} damage.`, 'action');

        // Pin/Submission and Stun Logic
        if (matchStateObj.currentHp[defender.id] > 0) {
            // Pin Attempt
            if (move.pinAttemptChance && Math.random() < move.pinAttemptChance) {
                if (!isBulkSimulation) {
                    updateMatchLog(`${attacker.name} goes for the pin!`, 'pin');
                    triggerCrowdReaction('pinAttempt', isBulkSimulation);
                }
                const hpPercentage = matchStateObj.currentHp[defender.id] / matchStateObj.initialHp[defender.id];
                const kickoutChance = hpPercentage + (defender.stats.toughness || 0) * 0.005;
                if (Math.random() > kickoutChance) {
                    matchStateObj.winnerId = attacker.id;
                    if (!isBulkSimulation) updateMatchLog(`${defender.name} is pinned! ${attacker.name} wins!`, 'win');
                    return;
                } else {
                    if (!isBulkSimulation) updateMatchLog(`${defender.name} kicks out!`, 'info');
                }
            }
            // Submission Attempt
            else if (move.submissionAttemptChance && Math.random() < move.submissionAttemptChance) {
                if (!isBulkSimulation) {
                    updateMatchLog(`${attacker.name} locks in a submission!`, 'submission');
                    triggerCrowdReaction('submissionAttempt', isBulkSimulation);
                }
                const escapeChance = (defender.stats.submissionDefense || 0) * 0.01;
                if (Math.random() > escapeChance) {
                    matchStateObj.winnerId = attacker.id;
                    if (!isBulkSimulation) updateMatchLog(`${defender.name} taps out! ${attacker.name} wins by submission!`, 'win');
                    return;
                } else {
                    if (!isBulkSimulation) updateMatchLog(`${defender.name} escapes the hold!`, 'info');
                }
            }
        }

        if (move.type === 'signature' && Math.random() < 0.25) {
            matchStateObj.stunnedStatus[defender.id] = 1;
        } else if (move.type === 'finisher' && Math.random() < 0.50) {
            matchStateObj.stunnedStatus[defender.id] = 1;
        }
    }

    // Post-Turn Updates
    if (!isBulkSimulation) {
        const damagedWrestler = allWrestlers.find(w => w.id === defender.id);
        if (damagedWrestler) {
            updateWrestlerHealthDisplay(damagedWrestler, matchStateObj.currentHp[damagedWrestler.id]);
            triggerDamageAnimation(damagedWrestler.id);
        }
        
        if (matchStateObj.currentHp[defender.id] <= 0 && !matchStateObj.winnerId) {
            matchStateObj.winnerId = attacker.id;
            updateMatchLog(`${defender.name} is unable to kick out! ${attacker.name} wins by pinfall!`, 'win');
        }
    }
}