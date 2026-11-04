<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Страница сама обновится, когда деплой закончится --}}
    <meta http-equiv="refresh" content="15">
    <title>Идёт обновление · APEX TIERS</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: #0d0c11;
            color: #a8a3b5;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .card {
            width: 100%;
            max-width: 420px;
            padding: 32px 28px;
            text-align: center;
            background: #16141d;
            border: 1px solid #2a2733;
            border-radius: 8px;
        }
        .blocks { display: flex; justify-content: center; gap: 8px; margin-bottom: 20px; }
        .blocks span {
            width: 14px;
            height: 14px;
            background: #a78bfa;
            animation: hop 1s ease-in-out infinite;
        }
        .blocks span:nth-child(2) { animation-delay: .15s; }
        .blocks span:nth-child(3) { animation-delay: .3s; }
        h1 { margin: 0 0 10px; color: #fff; font-size: 22px; }
        p { margin: 0; font-size: 15px; line-height: 1.5; }
        @keyframes hop {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }
        @media (prefers-reduced-motion: reduce) { .blocks span { animation: none; } }
    </style>
</head>
<body>
    <main class="card">
        <div class="blocks" aria-hidden="true"><span></span><span></span><span></span></div>
        <h1>Идёт обновление</h1>
        <p>Подождите около 2 минут. Страница откроется сама, как только всё будет готово.</p>
    </main>
</body>
</html>
