<!DOCTYPE html>
{{--
    Pagina de mentenanță (503). Se pre-randează la `artisan down` (deploy), deci e independentă de Vite, de sesiune și de fișierele
    care se schimbă în timpul urcării: stilurile sunt în pagină, iar imaginea are adresă fixă. Fără apeluri către baza de date.
--}}
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Actualizare în curs · {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">
    <meta name="theme-color" content="#ff4d6d">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { color-scheme: light; }
        body {
            margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 3rem 1.5rem;
            background: #fff5f3; color: #22222b; -webkit-font-smoothing: antialiased;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
        }
        main { width: 100%; max-width: 26rem; text-align: center; }
        .logo { display: block; width: 12rem; max-width: 70%; height: auto; margin: 0 auto 2.5rem; }
        .card { background: #fff; border: 1px solid #ffe0d8; border-radius: 1.5rem; padding: 2.5rem 2rem; box-shadow: 0 1px 2px rgba(34, 34, 43, .04); }
        .icon { width: 3.5rem; height: 3.5rem; margin: 0 auto 1.25rem; color: #ff4d6d; }
        .icon .spin { transform-origin: 50% 50%; animation: spin 2.4s linear infinite; }
        h1 { margin: 0; font-size: 1.5rem; line-height: 1.3; font-weight: 600; }
        p { margin: .75rem 0 0; color: rgba(34, 34, 43, .7); line-height: 1.55; }
        .bar { height: .25rem; margin: 1.75rem auto 0; width: 8rem; border-radius: 9999px; background: #ffe0d8; overflow: hidden; }
        .bar::after { content: ''; display: block; height: 100%; width: 40%; border-radius: inherit; background: linear-gradient(90deg, #ff4d6d, #ff9f1c); animation: slide 1.6s ease-in-out infinite; }
        .hint { margin-top: 1.25rem; font-size: .875rem; color: rgba(34, 34, 43, .5); }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes slide { 0% { transform: translateX(-100%); } 100% { transform: translateX(250%); } }
        @media (prefers-reduced-motion: reduce) { .icon .spin, .bar::after { animation: none; } }
    </style>
</head>
<body>
    <main>
        <img class="logo" src="/images/logo-default.png" alt="{{ config('app.name') }}" width="708" height="332">

        <div class="card" role="status">
            <svg class="icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <g class="spin"><path d="M40 24a16 16 0 1 1-4.7-11.3"/><path d="M40 6v8h-8"/></g>
            </svg>
            <h1>Actualizăm aplicația</h1>
            <p>Revenim în câteva minute. Te rugăm să nu faci operațiuni (vânzări, intrări) cât timp vezi această pagină.</p>
            <div class="bar" aria-hidden="true"></div>
            <p class="hint">Pagina se reîncarcă singură.</p>
        </div>
    </main>
</body>
</html>
