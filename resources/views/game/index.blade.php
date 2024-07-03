<!DOCTYPE html>
<html>
<head>
    <title>Rock-Paper-Scissors Game</title>
</head>
<body>
    <div id="app">
        <h1>Rock-Paper-Scissors Game</h1>
        <div>
            <select id="move">
                <option value="rock">Rock</option>
                <option value="paper">Paper</option>
                <option value="scissors">Scissors</option>
            </select>
            <button onclick="playGame()">Play</button>
        </div>
        <div v-if="result">
            <p>Your Move: <span id="userMove"></span></p>
            <p>Opponent's Move: <span id="opponentMove"></span></p>
            <p>Result: <span id="resultText"></span></p>
            <button onclick="resetGame()">Play Again</button>
        </div>
    </div>

    <script>
        let userMove = '';
        let opponentMove = '';
        let result = '';

        function playGame() {
            const move = document.getElementById('move').value;
            userMove = move;

            const moves = ['rock', 'paper', 'scissors'];
            opponentMove = moves[Math.floor(Math.random() * 3)];

            if (userMove === opponentMove) {
                result = 'It\'s a tie!';
            } else if (
                (userMove === 'rock' && opponentMove === 'scissors') ||
                (userMove === 'paper' && opponentMove === 'rock') ||
                (userMove === 'scissors' && opponentMove === 'paper')
            ) {
                result = 'You win!';
            } else {
                result = 'You lose!';
            }

            document.getElementById('userMove').innerText = userMove;
            document.getElementById('opponentMove').innerText = opponentMove;
            document.getElementById('resultText').innerText = result;
        }

        function resetGame() {
            userMove = '';
            opponentMove = '';
            result = '';

            document.getElementById('userMove').innerText = '';
            document.getElementById('opponentMove').innerText = '';
            document.getElementById('resultText').innerText = '';
        }
    </script>
</body>
</html>
