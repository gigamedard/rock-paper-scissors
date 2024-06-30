<!DOCTYPE html>
<html>
<head>
    <title>Game Result</title>
</head>
<body>
    <h1>Game Result</h1>
    <p>You played: {{ $playerMove }}</p>
    <p>Computer played: {{ $computerMove }}</p>
    <p>Result: {{ $result }}</p>
    <a href="/game">Play Again</a>
</body>
</html>
