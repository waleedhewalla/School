<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locale::direction() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Madrasa'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f7f7f5; --fg: #1d2326; --muted: #5b6468; --accent: #0f6e56; --card: #fff; --line: #e3e5e3; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #14181a; --fg: #eef1ef; --muted: #a2abaf; --accent: #4fc3a1; --card: #1c2225; --line: #2b3236; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif; background: var(--bg); color: var(--fg); line-height: 1.6; }
        main { max-width: 760px; margin: 0 auto; padding: 48px 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 24px; }
        .muted { color: var(--muted); }
        a { color: var(--accent); }
        /* Logical properties keep spacing correct in both RTL and LTR. */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-block-end: 24px; }
    </style>
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
