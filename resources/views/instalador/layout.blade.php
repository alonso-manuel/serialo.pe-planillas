<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Instalación · Serialo Planillas</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{-- Estilos propios: el instalador no depende de los assets compilados. --}}
    <style>
        :root { --azul: #2558a8; --celeste: #7fcbe8; --texto: #171717; --suave: #6b7280; --borde: #e5e7eb; --fondo: #f7f7f5; --error: #b91c1c; --ok: #15803d; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--texto); background: var(--fondo); line-height: 1.5; }
        main { max-width: 640px; margin: 0 auto; padding: 40px 16px 64px; }
        header { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        header svg { width: 40px; height: 40px; flex-shrink: 0; }
        header strong { font-size: 18px; display: block; }
        header span { color: var(--suave); font-size: 14px; }
        ol.pasos { list-style: none; display: flex; gap: 8px; padding: 0; margin: 0 0 20px; flex-wrap: wrap; }
        ol.pasos li { font-size: 13px; color: var(--suave); display: flex; align-items: center; gap: 6px; }
        ol.pasos li b { width: 22px; height: 22px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: #e5e7eb; font-size: 12px; }
        ol.pasos li.activo { color: var(--texto); font-weight: 600; }
        ol.pasos li.activo b, ol.pasos li.hecho b { background: var(--azul); color: #fff; }
        ol.pasos li + li::before { content: "›"; color: #c4c4c4; margin-right: 2px; }
        .tarjeta { background: #fff; border: 1px solid var(--borde); border-radius: 12px; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 24px 0 8px; }
        p.ayuda { color: var(--suave); font-size: 14px; margin: 0 0 16px; }
        label { display: block; font-size: 14px; font-weight: 500; margin: 14px 0 6px; }
        input[type=text], input[type=email], input[type=password], input[type=number], input[type=url], select, textarea { width: 100%; padding: 9px 12px; border: 1px solid #d4d4d4; border-radius: 8px; font: inherit; background: #fff; }
        input:focus, select:focus, textarea:focus { outline: 2px solid var(--celeste); border-color: var(--azul); }
        textarea { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; word-break: break-all; resize: vertical; }
        small.ayuda { display: block; margin-top: 4px; font-size: 13px; color: var(--suave); }
        .fila { display: grid; grid-template-columns: 1fr 120px; gap: 12px; }
        .fila > div label { margin-top: 14px; }
        .casilla { display: flex; gap: 8px; align-items: flex-start; font-weight: 400; }
        .casilla input { margin-top: 4px; }
        button { margin-top: 20px; width: 100%; padding: 11px 16px; border: 0; border-radius: 8px; background: var(--azul); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .error { color: var(--error); font-size: 13px; margin-top: 4px; }
        .alerta { border: 1px solid #fca5a5; background: #fef2f2; color: #7f1d1d; border-radius: 8px; padding: 10px 12px; font-size: 14px; margin-bottom: 12px; }
        .aviso { border: 1px solid #fcd34d; background: #fffbeb; color: #78350f; border-radius: 8px; padding: 10px 12px; font-size: 14px; margin: 12px 0; }
        ul.requisitos { list-style: none; padding: 0; margin: 0; border: 1px solid var(--borde); border-radius: 8px; }
        ul.requisitos li { padding: 8px 12px; font-size: 14px; display: flex; gap: 8px; }
        ul.requisitos li + li { border-top: 1px solid var(--borde); }
        ul.requisitos .si { color: var(--ok); font-weight: 700; }
        ul.requisitos .no { color: var(--error); font-weight: 700; }
        ul.requisitos small { display: block; color: var(--suave); }
        code, pre { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 13px; }
        pre { background: #f3f4f6; border-radius: 8px; padding: 12px; white-space: pre-wrap; word-break: break-all; }
        details { margin-top: 16px; border-top: 1px solid var(--borde); padding-top: 8px; }
        summary { cursor: pointer; font-weight: 500; font-size: 14px; }
        a.boton { display: block; text-align: center; margin-top: 20px; padding: 11px 16px; border-radius: 8px; background: var(--azul); color: #fff; text-decoration: none; font-weight: 600; }
        @media (max-width: 480px) { .fila { grid-template-columns: 1fr; } .tarjeta { padding: 18px; } }
    </style>
</head>
<body>
<main>
    <header>
        <svg viewBox="0 0 32 32" aria-hidden="true">
            <rect width="32" height="32" rx="7" fill="#2558a8"/>
            <g fill="#7fcbe8">
                @foreach (range(0, 7) as $k)
                    <rect x="9.4" y="7.6" width="3.2" height="4.4" rx="0.7" transform="rotate({{ $k * 45 }} 11 16)"/>
                @endforeach
                <circle cx="11" cy="16" r="5.4"/>
            </g>
            <circle cx="11" cy="16" r="2.3" fill="#2558a8"/>
            <g fill="#fff"><rect x="20.4" y="9.5" width="1.6" height="13" rx="0.4"/><rect x="23" y="9.5" width="0.9" height="13" rx="0.3"/><rect x="24.8" y="9.5" width="1.9" height="13" rx="0.4"/><rect x="27.5" y="9.5" width="0.9" height="13" rx="0.3"/></g>
        </svg>
        <div>
            <strong>Serialo Planillas</strong>
            <span>Instalación del sistema</span>
        </div>
    </header>

    <ol class="pasos">
        @foreach (['Requisitos', 'Base de datos', 'Sistema y administrador', 'Listo'] as $i => $nombre)
            <li class="{{ $paso === $i + 1 ? 'activo' : ($paso > $i + 1 ? 'hecho' : '') }}"><b>{{ $i + 1 }}</b>{{ $nombre }}</li>
        @endforeach
    </ol>

    <div class="tarjeta">
        @yield('contenido')
    </div>
</main>
</body>
</html>
