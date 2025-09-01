document.addEventListener('DOMContentLoaded', () => {
    // --- Element Validation ---
    const required_ids = [
        'start-tournament-btn', 'submit-picks-btn', 'bracket-container',
        'result-modal', 'modal-title', 'modal-message',
        'modal-actions', 'pay-continue-btn', 'quit-tournament-btn'
    ];
    
    for (const id of required_ids) {
        if (!document.getElementById(id)) {
            alert(`Critical Error: HTML element with id '${id}' was not found. The tournament script cannot run.`);
            return; // Stop execution
        }
    }
    // --- End Validation ---

    const startBtn = document.getElementById('start-tournament-btn');
    const submitPicksBtn = document.getElementById('submit-picks-btn');
    const bracketContainer = document.getElementById('bracket-container');
    
    const resultModal = document.getElementById('result-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalMessage = document.getElementById('modal-message');
    const modalActions = document.getElementById('modal-actions');
    const payContinueBtn = document.getElementById('pay-continue-btn');
    const quitTournamentBtn = document.getElementById('quit-tournament-btn');
    
    let userPicks = {};
    let currentRound = 1;
    let tournamentId = null;
    let lastActualWinners = [];

    startBtn.addEventListener('click', async () => {
        try {
            const response = await fetch(baseUrl + 'tournament/start', { method: 'POST' });
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || `HTTP error! Status: ${response.status}`);
            }
            
            tournamentId = data.tournament_id;
            alert(data.message);
            bracketContainer.classList.remove('locked');
            startBtn.style.display = 'none';
            submitPicksBtn.style.display = 'inline-block';
            submitPicksBtn.disabled = true;

        } catch (error) {
            console.error('Error starting tournament:', error);
            alert(`Error: ${error.message}`);
        }
    });

    bracketContainer.addEventListener('click', (event) => {
        const team = event.target.closest('.team');
        if (!team || bracketContainer.classList.contains('locked') || !team.dataset.wrestlerId) {
            return;
        }

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
            if (selectedWrestler) {
                finalPicks[index] = selectedWrestler.dataset.wrestlerId;
            }
        });
        
        try {
            const payload = {
                tournament_id: tournamentId,
                picks: finalPicks
            };

            const response = await fetch(baseUrl + 'tournament/simulate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Server error: ${response.status} - ${errorText}`);
            }

            const data = await response.json();

            if (data.success) {
                lastActualWinners = data.actual_winners;
                updateBracketWithResults(data.actual_winners);

                if (data.tournament_winner) {
                    showFinalResult(data.message);
                } else if (data.all_correct) {
                    setTimeout(() => advanceToNextRound(data.next_round_matchups), 2000);
                } else {
                    modalTitle.textContent = 'Incorrect Pick!';
                    modalMessage.textContent = data.message;
                    payContinueBtn.style.display = data.can_continue ? 'inline-block' : 'none';
                    resultModal.style.display = 'flex';
                }
            } else {
                alert(data.message || 'An error occurred.');
            }
        } catch (error) {
            console.error('An error occurred during submission:', error);
            alert('Failed to submit picks. Check the developer console (F12) for more details.');
        }
    });

    payContinueBtn.addEventListener('click', async () => {
        try {
            const response = await fetch(baseUrl + 'tournament/payToContinue', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tournament_id: tournamentId, actual_winners: lastActualWinners })
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
    });
    
    quitTournamentBtn.addEventListener('click', () => window.location.reload());

    function updateBracketWithResults(actualWinners) {
        bracketContainer.classList.add('locked');
        const matchups = document.querySelectorAll('.round.current .matchup, .champion.current .matchup');
        
        matchups.forEach((match, index) => {
            const winnerId = actualWinners[index];
            const teams = match.querySelectorAll('.team');
            teams.forEach(team => {
                if (team.dataset.wrestlerId == winnerId) {
                    team.classList.add('winner');
                } else if (team.dataset.wrestlerId) {
                    team.classList.add('eliminated');
                }
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

            const winner1 = nextRoundWrestlers[0];
            const winner2 = nextRoundWrestlers[1];
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
            const sideSelector = `.split-${side} .round.round-${currentRound}`;
            const matchups = document.querySelectorAll(`${sideSelector} .matchup`);
            
            matchups.forEach((match, index) => {
                const wrestler1 = wrestlerData[index * 2];
                const wrestler2 = wrestlerData[(index * 2) + 1];
                if (wrestler1 && wrestler2) {
                    const matchId = `${currentRound}-${side}-${index}`;
                    match.dataset.matchId = matchId;
                    
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

    function showFinalResult(message) {
        modalTitle.textContent = 'Tournament Over!';
        modalMessage.textContent = message;
        modalActions.innerHTML = '<button id="close-modal-btn" class="btn btn-primary">Play Again</button>';
        resultModal.style.display = 'flex';
        document.getElementById('close-modal-btn').addEventListener('click', () => window.location.reload());
    }
});