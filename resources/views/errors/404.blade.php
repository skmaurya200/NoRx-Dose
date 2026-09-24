@php
    $isManagerRequest = request()->is('manager/*') || request()->is('manager');
    $destination = $isManagerRequest ? route('manager.dashboard') : route('home');
    $destinationLabel = $isManagerRequest ? 'Back to dashboard' : 'Back to home';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Page not found &mdash; NoRx Dose</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/pages/error.css') }}">
</head>
<body>
    <main class="error-page">
        <section class="error-card" aria-labelledby="error-title">
            <a class="error-brand" href="{{ $destination }}" aria-label="NoRx Dose home">
                <span class="error-brand-mark">AW</span>
                <span>Aurum <em>Wellness</em></span>
            </a>

            <p class="error-code" aria-hidden="true">404</p>
            <p class="error-kicker">Page not found</p>
            <h1 id="error-title">This page has wandered off.</h1>
            <p class="error-copy">
                The address may be incorrect, or the page may have moved. Use the button below to return to a safe starting point.
            </p>

            <a class="error-action" href="{{ $destination }}">
                {{ $destinationLabel }} <span aria-hidden="true">&rarr;</span>
            </a>
        </section>
    </main>
</body>
</html>
