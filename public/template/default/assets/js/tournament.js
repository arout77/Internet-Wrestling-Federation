document.addEventListener('DOMContentLoaded', () => {
    const required_ids = [
        'start-tournament-btn', 'submit-picks-btn', 'bracket-container', 'result-modal', 
        'modal-title', 'modal-message', 'modal-actions', 'modal-round-summary', 
        'winners-header', 'round-winners-gallery', 'incorrect-picks-container', 'incorrect-picks-list',
        'buy-odds-container', 'buy-odds-btn', 'odds-section', 'odds-table-body', 'oddsNotice', 'close-notice-btn'
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
    const oddsNoticeModal = document.getElementById('oddsNotice');
    const closeNoticeBtn = document.getElementById('close-notice-btn');

    
    let userPicks = {};
    let currentRound = 1;
    let tournamentId = null;
    let lastResponseData = null;

    startBtn.addEventListener('click', async () => {
        try {
            const wrestlerIds = bracketContainer.dataset.wrestlerIds ? bracketContainer.dataset.wrestlerIds.split(',') : [];

            const response = await fetch(baseUrl + 'tournament/start', { 
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest' 
                },
                body: JSON.stringify({ wrestler_ids: wrestlerIds })
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
        buyOddsBtn.textContent = 'Calculating...this may take a moment!';

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
            oddsNoticeModal.style.display = 'flex';
            oddsSection.style.display = 'block';

        } catch (error) {
            alert(`Error: ${error.message}`);
            buyOddsBtn.disabled = false;
            buyOddsBtn.textContent = 'Pay 1 Gold to See First Round Odds';
        }
    });

    closeNoticeBtn.addEventListener('click', () => {
        oddsNoticeModal.style.display = 'none';
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

        const allCurrentMatchups = document.querySelectorAll('.round.current .matchup');
        const activeMatchups = Array.from(allCurrentMatchups).filter(m => m.querySelector('[data-wrestler-id]'));
        
        if (Object.keys(userPicks).length === activeMatchups.length) {
            submitPicksBtn.disabled = false;
        }
    });

    submitPicksBtn.addEventListener('click', async () => {
        try {
            const payload = { tournament_id: tournamentId, picks: userPicks };
            
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
        console.log("Server response received:", data);
        const incorrectWinnerIds = (data.incorrect_picks_data || []).map(pick => pick.actual_winner.wrestler_id);

        winnersHeader.textContent = `Round ${currentRound} Winners`;
        roundWinnersGallery.innerHTML = '';

        if (data.winners_data && Array.isArray(data.winners_data)) {
            data.winners_data.forEach(winner => {
                if (winner) {
                    const card = document.createElement('div');
                    card.className = 'winner-card-container';
                    const wasIncorrectPick = incorrectWinnerIds.includes(winner.wrestler_id);
                    const incorrectOverlay = wasIncorrectPick ? '<div class="incorrect-overlay">✖</div>' : '';
                    card.innerHTML = `
                        <div class="winner-card">
                            <img src="${baseUrl}public/media/images/${winner.image}.webp" alt="${winner.name}">
                            <p>${winner.name}</p>
                        </div>
                        ${incorrectOverlay}
                    `;
                    roundWinnersGallery.appendChild(card);
                }
            });
        }

        if (data.incorrect_picks_data && data.incorrect_picks_data.length > 0) {
            incorrectPicksList.innerHTML = '';
            data.incorrect_picks_data.forEach(pick => {
                const userPickName = pick.user_pick ? pick.user_pick.name : 'your pick';
                const actualWinnerName = pick.actual_winner ? pick.actual_winner.name : 'the winner';
                const li = document.createElement('li');
                li.innerHTML = `You picked <strong class="text-red-500">${userPickName}</strong>, but <strong class="text-green-400">${actualWinnerName}</strong> won.`;
                incorrectPicksList.appendChild(li);
            });
            incorrectPicksContainer.style.display = 'block';
        } else {
            incorrectPicksContainer.style.display = 'none';
        }

        modalTitle.textContent = data.tournament_winner ? 'Tournament Over!' : `Round ${currentRound} Complete!`;
        modalMessage.textContent = data.message;
        
        let actionsHtml = '';
        if (data.tournament_winner) {
            actionsHtml = `<button id="play-again-btn" class="btn btn-primary">Play Again</button>`;
        } else if (data.all_correct) {
            actionsHtml = `<button id="next-round-btn" class="btn btn-success">Continue to Next Round</button>`;
        } else {
            if (data.can_continue) {
                actionsHtml += `<button id="pay-continue-btn" class="btn-success mt-4">Pay 3 Gold to Continue</button>`;
            }
            actionsHtml += `<button id="quit-btn" class="btn-error mt-4 ml-4">Quit Tournament</button>`;
        }
        modalActions.innerHTML = actionsHtml;

        if (data.tournament_winner) {
            document.getElementById('play-again-btn').addEventListener('click', () => window.location.reload());
        } else if (data.all_correct) {
            document.getElementById('next-round-btn').addEventListener('click', handleNextRound);
        } else {
            if (data.can_continue) {
                const payBtn = document.getElementById('pay-continue-btn');
                if(payBtn) payBtn.addEventListener('click', handlePayToContinue);
            }
            const quitBtn = document.getElementById('quit-btn');
            if(quitBtn) quitBtn.addEventListener('click', () => window.location.reload());
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
        let winnerIndex = 0;
        matchups.forEach((match) => {
            if (!match.querySelector('[data-wrestler-id]')) return; // Skip empty matchups
            const winnerId = actualWinners[winnerIndex++];
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
        if (nextRoundDivs.length === 0 || nextRoundWrestlers.length < 2) {
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
                const wrestler2 = wrestlerData[(index * 2) + 1];
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

        const is16Man = bracketContainer.classList.contains('bracket-16');

        if (is16Man) {
            populateSide('one', nextRoundWrestlers); 
        } else {
            const midPoint = nextRoundWrestlers.length / 2;
            populateSide('one', nextRoundWrestlers.slice(0, midPoint));
            populateSide('two', nextRoundWrestlers.slice(midPoint));
        }

        bracketContainer.classList.remove('locked');
    }
});
