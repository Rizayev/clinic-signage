<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signage Player</title>
    @vite(['resources/css/app.css', 'resources/js/player.js'])
    <style>
        html, body { margin: 0; padding: 0; height: 100%; background: #000; overflow: hidden; }
        #player { height: 100vh; width: 100vw; }
    </style>
</head>
<body>
    <div id="player"></div>
</body>
</html>
