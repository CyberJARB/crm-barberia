{{--
    Identidad visual del panel (renderHook panels::head.end).
    Sin tema Filament compilado: solo CSS sobre las clases fi-* estables.
    Tonos índigo/violeta a juego con la marca; sutil para no cansar.
--}}
<style>
    /* ===== Barra lateral con tinte de marca ===== */
    .fi-sidebar {
        background: linear-gradient(180deg, #eef2ff 0%, #fafafa 38%, #faf5ff 100%);
    }
    .dark .fi-sidebar {
        background: linear-gradient(180deg, rgba(49, 46, 129, 0.35) 0%, rgba(24, 24, 27, 1) 45%);
    }

    /* Marca con degradado */
    .fi-sidebar .fi-logo {
        background: linear-gradient(90deg, #4f46e5, #7c3aed);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    /* ===== Línea de acento superior ===== */
    .fi-topbar > nav, .fi-topbar {
        border-top: 3px solid transparent;
        border-image: linear-gradient(90deg, #4f46e5, #7c3aed, #f59e0b) 1;
    }

    /* ===== Item de navegación activo más vivo ===== */
    .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background: linear-gradient(90deg, rgba(79, 70, 229, 0.14), rgba(124, 58, 237, 0.10));
        border-radius: 10px;
    }

    /* ===== Tarjetas de widgets: sombra suave y hover ===== */
    .fi-wi-stats-overview-stat, .fi-section {
        transition: box-shadow 0.18s ease;
    }
    .fi-wi-stats-overview-stat:hover {
        box-shadow: 0 10px 28px -16px rgba(79, 70, 229, 0.45);
    }
</style>
