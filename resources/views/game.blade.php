<!DOCTYPE html>
<html>
<head>
    <title>Rock-Paper-Scissors Game</title>
</head>
<body>
    <h1>Rock-Paper-Scissors</h1>
    <form method="POST" action="/game/play">
        @csrf
        <button type="submit" name="move" value="rock">Rock</button>
        <button type="submit" name="move" value="paper">Paper</button>
        <button type="submit" name="move" value="scissors">Scissors</button>
    </form>
</body>
</html>
