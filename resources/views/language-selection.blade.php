<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Choose Language</title>
    <style>
        body {
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #111;
        }
        .flags {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 20px;
            max-width: 600px;
            width: 100%;
        }
        .flag-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            transition: transform 0.2s;
        }
        .flag-btn:hover {
            transform: scale(1.1);
        }
        img {
            width: 100%;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body>
    <div class="flags">
        <form action="{{ route('language.set', 'en') }}" method="POST">
            @csrf
            <button type="submit" class="flag-btn">
                <img src="{{ asset('flags/en.png') }}" alt="English">
            </button>
        </form>

        <form action="{{ route('language.set', 'fr') }}" method="POST">
            @csrf
            <button type="submit" class="flag-btn">
                <img src="{{ asset('flags/fr.png') }}" alt="Français">
            </button>
        </form>

        <form action="{{ route('language.set', 'es') }}" method="POST">
            @csrf
            <button type="submit" class="flag-btn">
                <img src="{{ asset('flags/es.png') }}" alt="Español">
            </button>
        </form>
    </div>
</body>
</html>
