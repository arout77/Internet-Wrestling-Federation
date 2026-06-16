// utils.js

/**
 * Parses height string (e.g., "6'2\"") into total inches.
 * @param {string} heightStr - The height string to parse.
 * @returns {number} The height in inches, or 0 if parsing fails.
 */
export function parseHeightToInches(heightStr) {
    if (!heightStr || typeof heightStr !== 'string') return 0;
    const parts = heightStr.match(/(\d+)'(\d+)"?/);
    if (parts && parts.length === 3) {
        const feet = parseInt(parts[1]);
        const inches = parseInt(parts[2]);
        return (feet * 12) + inches;
    }
    return 0; // Default to 0 if parsing fails
}

/**
 * Calculates the greatest common divisor (GCD) of two numbers.
 * @param {number} a
 * @param {number} b
 * @returns {number} GCD
 */
export function gcd(a, b) {
    return b === 0 ? a : gcd(b, a % b);
}

/**
 * Converts a probability to Decimal Odds.
 * @param {number} probability - The probability (0 to 1).
 * @returns {string} Decimal odds string.
 */
export function probabilityToDecimalOdds(probability) {
    if (probability === 0) return '∞'; // Infinite odds for impossible events
    return (1 / probability).toFixed(2);
}

/**
 * Converts a probability to Fractional Odds.
 * @param {number} probability - The probability (0 to 1).
 * @returns {string} Fractional odds string (e.g., "5/1", "1/2").
 */
export function probabilityToFractionalOdds(probability) {
    if (probability === 0) return '∞/1';
    if (probability === 1) return '0/1'; // For a certainty

    const decimalOdds = 1 / probability;
    if (decimalOdds <= 1) { // Favored (e.g., 1.50 -> 1/2)
        const numerator = Math.round((decimalOdds - 1) * 100);
        const denominator = 100;
        const gcdValue = gcd(numerator, denominator);
        return `${numerator / gcdValue}/${denominator / gcdValue}`;
    } else { // Underdog (e.g., 5.00 -> 4/1)
        const numerator = Math.round((decimalOdds - 1) * 100);
        const denominator = 100;
        const gcdValue = gcd(numerator, denominator);
        return `${numerator / gcdValue}/${denominator / gcdValue}`;
    }
}

/**
 * Converts a probability to Moneyline Odds, including a bookmaker's margin.
 * @param {number} probability - The true probability (0 to 1).
 * @returns {string} Moneyline odds string (e.g., "+400", "-200").
 */
export function probabilityToMoneylineOdds(probability) {
    // Handle edge cases for 0% and 100% probability first, as per user's specific requests.
    if (probability === 0) {
        return '+9900'; // As requested for 0.00% probability
    }
    // For 100% probability, it's an extreme favorite, so a very large negative moneyline.
    if (probability === 1) {
        return '-99999'; // A very large negative number for guaranteed win
    }

    const BOOKMAKER_MARGIN = 0.10; // 10% bookmaker's margin

    // Apply the bookmaker's margin to the probability.
    // This effectively reduces the payout for the bettor, building in the margin.
    // Example: If true probability is 0.5 (50%), with 10% margin,
    // the effective probability for payout is 0.5 * (1 - 0.10) = 0.45.
    // This makes the decimal odds higher (less favorable for the bettor).
    const effectiveProbability = probability * (1 - BOOKMAKER_MARGIN);

    // Ensure effectiveProbability is not zero or negative after applying margin
    if (effectiveProbability <= 0) {
        return '+9900'; // Consistent with the user's previous request for 0% probability
    }

    const decimalOdds = 1 / effectiveProbability;
    let moneylineValue;

    if (decimalOdds >= 2.00) { // Underdog (positive moneyline)
        // Formula: (Decimal Odds - 1) * 100
        moneylineValue = Math.round((decimalOdds - 1) * 100);
        return `+${Math.min(9900, moneylineValue)}`;
    } else { // Favored (negative moneyline)
        // Formula: -100 / (Decimal Odds - 1) (absolute value)
        moneylineValue = Math.round(100 / (decimalOdds - 1));
        return `-${moneylineValue}`;
    }

    // Handle the case where moneylineValue might become 0 due to rounding for very high probabilities
    if (moneylineValue === 0 && decimalOdds < 2.00) {
        return '-99999'; // Very large negative for extreme favorite
    }

    return `${decimalOdds >= 2.00 ? '+' : '-'}${moneylineValue}`;
}

/**
 * Determines the winner of the match based on current HP.
 * @param {Object} matchStateObj - The current match state object (can be global matchState or tempMatchState).
 * @param {Object} selectedWrestlersMap - Map of currently selected wrestlers for the match type (e.g., app.js's selectedWrestlers).
 * @param {string} matchType - The type of match ('single' or 'tagTeam').
 * @returns {object|string} The winning wrestler object, a team string ('Team 1', 'Team 2'), or 'draw' if no winner yet.
 */
export function determineWinner(matchStateObj, selectedWrestlersMap, matchType) {
    if (matchType === 'single') {
        const player1Hp = matchStateObj.currentHp[selectedWrestlersMap.player1.id];
        const player2Hp = matchStateObj.currentHp[selectedWrestlersMap.player2.id];

        if (player1Hp <= 0 && player2Hp <= 0) {
            return 'draw'; // Double KO
        } else if (player1Hp <= 0) {
            return selectedWrestlersMap.player2;
        } else if (player2Hp <= 0) {
            return selectedWrestlersMap.player1;
        }
    } else if (matchType === 'tagTeam') {
        // Check if both members of a team are knocked out
        const team1KnockedOut = (matchStateObj.currentHp[selectedWrestlersMap.team1Player1.id] <= 0 && matchStateObj.currentHp[selectedWrestlersMap.team1Player2.id] <= 0);
        const team2KnockedOut = (matchStateObj.currentHp[selectedWrestlersMap.team2Player1.id] <= 0 && matchStateObj.currentHp[selectedWrestlersMap.team2Player2.id] <= 0);

        if (team1KnockedOut && team2KnockedOut) {
            return 'draw'; // Both teams are knocked out
        } else if (team1KnockedOut) {
            return 'Team 2'; // Team 1 is knocked out, Team 2 wins
        } else if (team2KnockedOut) {
            return 'Team 1'; // Team 2 is knocked out, Team 1 wins
        }
    }
    return null; // No winner yet
}
