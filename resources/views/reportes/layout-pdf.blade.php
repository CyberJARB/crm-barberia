<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>@yield('titulo')</title>
    <style>
        /* DomPDF — solo CSS básico, sin Tailwind */
        @page {
            margin: 110px 40px 70px 40px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        /* ── Header fijo ─────────────────────────────────────────── */
        header {
            position: fixed;
            top: -80px;
            left: 0;
            right: 0;
            height: 70px;
            background-color: #4f46e5;
            color: #ffffff;
            padding: 12px 20px;
        }

        header .logo {
            display: inline-block;
            width: 34px;
            height: 34px;
            line-height: 34px;
            text-align: center;
            font-size: 18px;
            background-color: #ffffff;
            color: #4f46e5;
            border-radius: 17px;
            vertical-align: middle;
        }

        header .empresa {
            display: inline-block;
            vertical-align: middle;
            margin-left: 10px;
        }

        header .empresa .nombre {
            font-size: 14px;
            font-weight: bold;
        }

        header .empresa .subtitulo {
            font-size: 9px;
            color: #c7d2fe;
        }

        header .meta {
            position: absolute;
            top: 12px;
            right: 20px;
            text-align: right;
        }

        header .meta .titulo-reporte {
            font-size: 13px;
            font-weight: bold;
        }

        header .meta .periodo {
            font-size: 9px;
            color: #c7d2fe;
            margin-top: 3px;
        }

        /* ── Footer fijo ─────────────────────────────────────────── */
        footer {
            position: fixed;
            bottom: -50px;
            left: 0;
            right: 0;
            height: 40px;
            border-top: 1px solid #d1d5db;
            padding-top: 6px;
            font-size: 8px;
            color: #6b7280;
        }

        footer .izquierda { float: left; }
        footer .centro { text-align: center; }
        footer .derecha { float: right; }

        footer .pagina:after {
            content: "Seite " counter(page) " von " counter(pages);
        }

        /* ── Tabla ───────────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        thead th {
            background-color: #4f46e5;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            padding: 7px 8px;
        }

        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }

        /* Filas alternadas en gris claro */
        tbody tr:nth-child(even) td {
            background-color: #f3f4f6;
        }

        tfoot td {
            padding: 8px;
            font-weight: bold;
            font-size: 10px;
            background-color: #e0e7ff;
            border-top: 2px solid #4f46e5;
        }

        .derecha-num { text-align: right; }
        .centrado { text-align: center; }

        .sin-datos {
            margin-top: 30px;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
        }

        h2.seccion {
            font-size: 12px;
            color: #4f46e5;
            margin: 4px 0 6px 0;
        }
    </style>
</head>
<body>
    <header>
        <span class="logo">&#9986;</span>
        <span class="empresa">
            <span class="nombre">{{ $tenant->nombre }}</span><br>
            <span class="subtitulo">CRM Barbería — Berichtswesen</span>
        </span>
        <span class="meta">
            <span class="titulo-reporte">@yield('titulo')</span><br>
            <span class="periodo">Zeitraum: {{ $periodo }}</span>
        </span>
    </header>

    <footer>
        <span class="izquierda">Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr</span>
        <span class="derecha pagina"></span>
        <div class="centro">Vertraulich — nur für internen Gebrauch</div>
    </footer>

    <main>
        @yield('contenido')
    </main>
</body>
</html>
