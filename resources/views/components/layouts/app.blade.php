<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'ParkEasy' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav>
        <ul>
            <!-- <li><a href="/">Home</a></li> -->
        </ul>
    </nav>
        {{ $slot }}
</body>
</html>