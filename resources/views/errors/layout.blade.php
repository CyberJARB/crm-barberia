{{-- OWASP A05 / CWE-209 — Páginas de error genéricas: sin stack traces ni firma del framework --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') — @yield('title')</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #111827; color: #f9fafb;
               display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { text-align: center; padding: 2rem; }
        h1 { font-size: 4rem; margin: 0; color: #f59e0b; }
        p { color: #9ca3af; margin: 1rem 0 2rem; }
        a { color: #f59e0b; text-decoration: none; border: 1px solid #f59e0b; padding: .6rem 1.4rem;
            border-radius: .5rem; display: inline-block; }
    </style>
</head>
<body>
    <div class="box">
        <h1>@yield('code')</h1>
        <p>@yield('message')</p>
        <a href="{{ url('/admin') }}">Zurück zur Anwendung</a>
    </div>
</body>
</html>
