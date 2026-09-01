<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#E63946">

    <title>@yield('title', config('app.name', 'Double Jeu'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .guest-wrap {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .guest-card {
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.45s ease both;
        }
        .guest-card .card { margin-top: 0; }
        .guest-divider {
            display: flex; align-items: center; gap: 12px; margin: 20px 0;
        }
        .guest-divider::before, .guest-divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
        .guest-divider span { color: var(--text-3); font-size: 12px; font-weight: 500; }
        .err-bag {
            background: rgba(230,57,70,0.1);
            border: 1px solid rgba(230,57,70,0.3);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 16px;
        }
        .err-bag p { margin: 0; font-size: 13px; color: var(--primary-2); }
        .err-bag p + p { margin-top: 4px; }
    </style>
</head>
<body>
    <div class="guest-wrap">
        <div class="guest-card">
            {{ $slot }}
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            @foreach ($errors->all() as $error)
                toast(@json($error), 'error');
            @endforeach
        });
    </script>

    @stack('scripts')
</body>
</html>
