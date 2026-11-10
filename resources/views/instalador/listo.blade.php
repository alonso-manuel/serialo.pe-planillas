@extends('instalador.layout')

@section('contenido')
    <h1>¡Instalación terminada!</h1>
    <p class="ayuda">El sistema está listo. Entra con el correo <strong>{{ $correo }}</strong> y la contraseña que elegiste.</p>

    <h2>Último paso: la tarea programada (cron)</h2>
    <p class="ayuda">
        En cPanel, abre «Trabajos de cron», elige <em>Una vez por minuto</em> y pega este comando.
        Con él se procesan los cálculos masivos y el envío de boletas por correo.
    </p>
    <pre>{{ $cron }}</pre>
    <p class="ayuda">Si tu hosting lo pide, cambia <code>php</code> por la ruta completa (por ejemplo <code>/usr/local/bin/php</code>).</p>

    @if ($demo)
        <div class="aviso">
            Se cargaron datos de demostración con los usuarios <code>demo@serialo.pe</code> y <code>asistente@serialo.pe</code>,
            que tienen contraseñas conocidas. Desactívalos desde Usuarios antes de usar el sistema con datos reales.
        </div>
    @endif

    <a class="boton" href="{{ $login }}">Ir al inicio de sesión</a>
@endsection
