document.addEventListener('DOMContentLoaded', () => {
    const required_ids = [
        'start-tournament-btn', 'submit-picks-btn', 'bracket-container', 'result-modal', 
        'modal-title', 'modal-message', 'modal-actions', 'modal-round-summary', 
        'winners-header', 'round-winners-gallery', 'incorrect-picks-container', 'incorrect-picks-list',
        'buy-odds-container', 'buy-odds-btn', 'odds-section', 'odds-table-body'
    ];
    
    for (const id of required_ids) {
        if (!document.getElementById(id)) {
            console.error(`Critical Error: HTML element with id '${id}' was not found.`);
            return;
        }
    }

    const startBtn = document.getElementById('start-tournament-btn');
    const submitPicksBtn = document.getElementById('submit-picks-btn');
    const bracketContainer = document.getElementById('bracket-container');
    const resultModal = document.getElementById('result-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalMessage = document.getElementById('modal-message');
    const modalActions = document.getElementById('modal-actions');
    const winnersHeader = document.getElementById('winners-header');
    const roundWinnersGallery = document.getElementById('round-winners-gallery');
    const incorrectPicksContainer = document.getElementById('incorrect-picks-container');
    const incorrectPicksList = document.getElementById('incorrect-picks-list');
    const buyOddsContainer = document.getElementById('buy-odds-container');
    const buyOddsBtn = document.getElementById('buy-odds-btn');
    const oddsSection = document.getElementById('odds-section');
    const oddsTableBody = document.getElementById('odds-table-body');
    const oddsNotice = document.getElementById('odds-section');
    
    let userPicks = {};
    let currentRound = 1;
    let tournamentId = null;
    let lastResponseData = null;

    startBtn.addEventListener('click', async () => {
        try {
            const response = await fetch(baseUrl + 'tournament/start', { 
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (response.status === 401 || response.status === 403) {
                alert('You must be logged in to start a tournament. Redirecting to login...');
                window.location.href = baseUrl + 'user/login';
                return;
            }
            if (!response.ok) throw new Error(data.message || `HTTP error! Status: ${response.status}`);
            
            tournamentId = data.tournament_id;
            alert(data.message);
            bracketContainer.classList.remove('locked');
            startBtn.style.display = 'none';
            submitPicksBtn.style.display = 'inline-block';
            submitPicksBtn.disabled = true;
            buyOddsContainer.style.display = 'block';
        } catch (error) {
            console.error('Error starting tournament:', error);
            if (error instanceof SyntaxError) {
                 alert('An unexpected response was received from the server. You may need to log in.');
                 window.location.href = baseUrl + 'user/login';
            } else {
                alert(`Error: ${error.message}`);
            }
        }
    });
    
    buyOddsBtn.addEventListener('click', async () => {
        if (!tournamentId) {
            alert('Please start the tournament first.');
            return;
        }
        buyOddsBtn.disabled = true;
        buyOddsBtn.textContent = 'Calculating...';

        try {
            const response = await fetch(baseUrl + 'tournament/get_odds', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ tournament_id: tournamentId })
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.message || 'Failed to fetch odds.');

            oddsTableBody.innerHTML = '';
            data.odds_data.forEach((matchup, index) => {
                const row = `
                    <tr>
                        <td>${index + 1}</td>
                        <td class="wrestler-name">${matchup.wrestler1.name}</td>
                        <td>${matchup.odds.wrestler1}%</td>
                        <td class="wrestler-name">${matchup.wrestler2.name}</td>
                        <td>${matchup.odds.wrestler2}%</td>
                    </tr>
                `;
                oddsTableBody.innerHTML += row;
            });

            buyOddsContainer.style.display = 'none';
            oddsNotice.style.display = 'block';
            oddsSection.style.display = 'block';

        } catch (error) {
            alert(`Error: ${error.message}`);
            buyOddsBtn.disabled = false;
            buyOddsBtn.textContent = 'Pay 1 Gold to See First Round Odds';
        }
    });

    bracketContainer.addEventListener('click', (event) => {
        const team = event.target.closest('.team');
        if (!team || bracketContainer.classList.contains('locked') || !team.dataset.wrestlerId) return;

        const match = team.closest('.matchup');
        const matchId = match.dataset.matchId;
        const teamsInMatch = match.querySelectorAll('.team');
        
        teamsInMatch.forEach(t => t.classList.remove('selected'));
        team.classList.add('selected');
        userPicks[matchId] = team.dataset.wrestlerId;

        const currentMatchups = document.querySelectorAll('.round.current .matchup, .champion.current .matchup');
        if (Object.keys(userPicks).length === currentMatchups.length) {
            submitPicksBtn.disabled = false;
        }
    });

    submitPicksBtn.addEventListener('click', async () => {
        const finalPicks = {};
        const currentMatchups = document.querySelectorAll('.round.current .matchup, .champion.current .matchup');
        currentMatchups.forEach((match, index) => {
            const selectedWrestler = match.querySelector('.team.selected');
            if (selectedWrestler) finalPicks[index] = selectedWrestler.dataset.wrestlerId;
        });
        
        try {
            const payload = { tournament_id: tournamentId, picks: finalPicks };
            const response = await fetch(baseUrl + 'tournament/simulate', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Server error: ${response.status} - ${errorText}`);
            }

            lastResponseData = await response.json();
            if (lastResponseData.success) {
                updateBracketWithResults(lastResponseData.actual_winners);
                showRoundSummary(lastResponseData);
            } else {
                alert(lastResponseData.message || 'An error occurred.');
            }
        } catch (error) {
            console.error('An error occurred during submission:', error);
            alert('Failed to submit picks. Check the developer console (F12) for more details.');
        }
    });

    function showRoundSummary(data) {
        // --- THIS IS THE FIX ---
        // Log the server response to help debug and add defensive checks.
        console.log("Server response received:", data);

        winnersHeader.textContent = `Round ${currentRound} Winners`;
        roundWinnersGallery.innerHTML = '';
        if (data.winners_data && Array.isArray(data.winners_data)) {
            data.winners_data.forEach(winner => {
                if (winner) { // Check if winner object is not null
                    const card = document.createElement('div');
                    card.className = 'winner-card';
                    card.innerHTML = `
                        <img src="${baseUrl}assets/img/roster/${winner.image}.webp" alt="${winner.name}">
                        <p>${winner.name}</p>
                    `;
                    roundWinnersGallery.appendChild(card);
                }
            });
        }

        if (data.incorrect_picks_data && Array.isArray(data.incorrect_picks_data) && data.incorrect_picks_data.length > 0) {
            incorrectPicksList.innerHTML = '';
            data.incorrect_picks_data.forEach(pick => {
                // Check that user_pick exists before trying to access its name
                const userPickName = pick.user_pick ? pick.user_pick.name : 'your pick';
                const actualWinnerName = pick.actual_winner ? pick.actual_winner.name : 'the winner';
                const li = document.createElement('li');
                li.innerHTML = `You picked <strong>${userPickName}</strong>, but <strong>${actualWinnerName}</strong> won.`;
                incorrectPicksList.appendChild(li);
            });
            incorrectPicksContainer.style.display = 'block';
        } else {
            incorrectPicksContainer.style.display = 'none';
        }
        // --- END FIX ---

        modalTitle.textContent = data.tournament_winner ? 'Tournament Over!' : `Round ${currentRound} Complete!`;
        modalMessage.textContent = data.message;
        modalActions.innerHTML = '';

        if (data.tournament_winner) {
            modalActions.innerHTML = `<button id="play-again-btn" class="btn btn-primary">Play Again</button>`;
            document.getElementById('play-again-btn').addEventListener('click', () => window.location.reload());
        } else if (data.all_correct) {
            modalActions.innerHTML = `<button id="next-round-btn" class="btn btn-success">Continue to Next Round</button>`;
            document.getElementById('next-round-btn').addEventListener('click', handleNextRound);
        } else {
            if (data.can_continue) {
                modalActions.innerHTML += `<button id="pay-continue-btn" class="btn btn-warning">Pay 3 Gold to Continue</button>`;
                document.getElementById('pay-continue-btn').addEventListener('click', handlePayToContinue);
            }
            modalActions.innerHTML += `<button id="quit-btn" class="btn btn-danger">Quit Tournament</button>`;
            document.getElementById('quit-btn').addEventListener('click', () => window.location.reload());
        }

        resultModal.style.display = 'flex';
    }

    function handleNextRound() {
        resultModal.style.display = 'none';
        advanceToNextRound(lastResponseData.next_round_matchups);
    }
    
    async function handlePayToContinue() {
         try {
            const response = await fetch(baseUrl + 'tournament/payToContinue', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ tournament_id: tournamentId })
            });
            const data = await response.json();
            
            if(data.success) {
                resultModal.style.display = 'none';
                alert(data.message);
                advanceToNextRound(data.next_round_matchups);
            } else {
                alert(data.message || 'Payment failed.');
            }
        } catch (error) {
            console.error('Error paying to continue:', error);
            alert('An error occurred during payment. Please try again.');
        }
    }

    function updateBracketWithResults(actualWinners) {
        bracketContainer.classList.add('locked');
        const matchups = document.querySelectorAll('.round.current .matchup, .champion.current .matchup');
        matchups.forEach((match, index) => {
            const winnerId = actualWinners[index];
            const teams = match.querySelectorAll('.team');
            teams.forEach(team => {
                if (team.dataset.wrestlerId == winnerId) team.classList.add('winner');
                else if (team.dataset.wrestlerId) team.classList.add('eliminated');
            });
        });
    }

    function advanceToNextRound(nextRoundWrestlers) {
        userPicks = {};
        document.querySelectorAll('.current').forEach(el => el.classList.remove('current'));
        currentRound++;
        
        const nextRoundDivs = document.querySelectorAll(`.round.round-${currentRound}`);
        if (nextRoundDivs.length === 0) {
            const finalMatchup = document.querySelector('.champion .matchup');
            if (!finalMatchup || nextRoundWrestlers.length < 2) return;
            const [winner1, winner2] = [nextRoundWrestlers[0], nextRoundWrestlers[1]];
            finalMatchup.dataset.matchId = "final-0";
            const teamTop = finalMatchup.querySelector('.team-top');
            teamTop.textContent = winner1.name;
            teamTop.dataset.wrestlerId = winner1.wrestler_id;
            teamTop.classList.remove('winner', 'eliminated', 'selected');
            const teamBottom = finalMatchup.querySelector('.team-bottom');
            teamBottom.textContent = winner2.name;
            teamBottom.dataset.wrestlerId = winner2.wrestler_id;
            teamBottom.classList.remove('winner', 'eliminated', 'selected');
            document.querySelector('.champion').classList.add('current');
            submitPicksBtn.textContent = `Submit Final Pick`;
        } else {
             nextRoundDivs.forEach(el => el.classList.add('current'));
             submitPicksBtn.textContent = `Submit Round ${currentRound} Picks`;
        }
        
        submitPicksBtn.disabled = true;
        const populateSide = (side, wrestlerData) => {
            const matchups = document.querySelectorAll(`.split-${side} .round.round-${currentRound} .matchup`);
            matchups.forEach((match, index) => {
                const wrestler1 = wrestlerData[index * 2];
                const wrestler2 = wrestlerData[index * 2] + 1;
                if (wrestler1 && wrestler2) {
                    match.dataset.matchId = `${currentRound}-${side}-${index}`;
                    const teamTop = match.querySelector('.team-top');
                    teamTop.textContent = wrestler1.name;
                    teamTop.dataset.wrestlerId = wrestler1.wrestler_id;
                    teamTop.classList.remove('winner', 'eliminated', 'selected');
                    const teamBottom = match.querySelector('.team-bottom');
                    teamBottom.textContent = wrestler2.name;
                    teamBottom.dataset.wrestlerId = wrestler2.wrestler_id;
                    teamBottom.classList.remove('winner', 'eliminated', 'selected');
                }
            });
        };
        const midPoint = nextRoundWrestlers.length / 2;
        populateSide('one', nextRoundWrestlers.slice(0, midPoint));
        populateSide('two', nextRoundWrestlers.slice(midPoint));
        bracketContainer.classList.remove('locked');
    }
});

