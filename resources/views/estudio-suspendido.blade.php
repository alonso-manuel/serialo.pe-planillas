<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Cuenta suspendida · Serialo Planillas</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { --azul: #2558a8; --texto: #171717; --suave: #6b7280; --fondo: #f7f7f5; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--texto); background: var(--fondo); }
        main { max-width: 440px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; text-align: center; }
        h1 { font-size: 20px; margin: 12px 0 8px; }
        p { color: var(--suave); font-size: 15px; line-height: 1.5; margin: 0 0 8px; }
        svg { width: 44px; height: 44px; }
    </style>
</head>
<body>
<main>
    <svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="7" fill="#2558a8"/><circle cx="11" cy="16" r="5.4" fill="#7fcbe8"/><circle cx="11" cy="16" r="2.3" fill="#2558a8"/><g fill="#fff"><rect x="20.4" y="9.5" width="1.6" height="13" rx="0.4"/><rect x="23" y="9.5" width="0.9" height="13" rx="0.3"/><rect x="24.8" y="9.5" width="1.9" height="13" rx="0.4"/><rect x="27.5" y="9.5" width="0.9" height="13" rx="0.3"/></g></svg>
    <h1>Cuenta suspendida</h1>
    <p>La cuenta de <strong>{{ $estudio->nombre }}</strong> en Serialo Planillas está suspendida temporalmente.</p>
    <p>Sus datos se conservan. Para reactivarla, comunícate con Serialo.pe.</p>
</main>
</body>
</html>
