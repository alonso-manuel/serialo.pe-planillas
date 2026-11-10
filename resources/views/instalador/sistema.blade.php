@extends('instalador.layout')

@section('contenido')
    <h1>Sistema y administrador</h1>
    <p class="ayuda">Al terminar se crean las tablas, se cargan los catálogos legales (RMV, UIT, AFP, tabla 22) y el primer administrador.</p>

    @error('instalacion')<div class="alerta">{{ $message }}</div>@enderror

    <form method="POST" action="{{ route('instalar.sistema.store') }}">
        @csrf
        <label for="sistema">Nombre del sistema</label>
        <input id="sistema" name="sistema" type="text" value="{{ old('sistema', 'Serialo Planillas') }}" required>
        @error('sistema')<div class="error">{{ $message }}</div>@enderror

        <label for="url">Dirección web</label>
        <input id="url" name="url" type="url" value="{{ old('url', $urlSugerida) }}" required>
        @error('url')<div class="error">{{ $message }}</div>@enderror

        <h2>Primer administrador</h2>
        <label for="admin_nombre">Nombre</label>
        <input id="admin_nombre" name="admin_nombre" type="text" value="{{ old('admin_nombre') }}" autocomplete="name" required>
        @error('admin_nombre')<div class="error">{{ $message }}</div>@enderror

        <label for="admin_correo">Correo</label>
        <input id="admin_correo" name="admin_correo" type="email" value="{{ old('admin_correo') }}" autocomplete="email" required>
        @error('admin_correo')<div class="error">{{ $message }}</div>@enderror

        <label for="admin_clave">Contraseña</label>
        <input id="admin_clave" name="admin_clave" type="password" autocomplete="new-password" required>
        <small class="ayuda">Mínimo 12 caracteres, con mayúsculas, minúsculas, números y símbolos.</small>
        @error('admin_clave')<div class="error">{{ $message }}</div>@enderror

        <label for="admin_clave_confirmation">Repite la contraseña</label>
        <input id="admin_clave_confirmation" name="admin_clave_confirmation" type="password" autocomplete="new-password" required>

        <details @if ($errors->hasAny(['smtp_host', 'smtp_puerto', 'smtp_usuario', 'correo_remitente']) || old('smtp_host')) open @endif>
            <summary>Correo para enviar boletas (opcional, se puede configurar después)</summary>
            <div class="fila">
                <div>
                    <label for="smtp_host">Servidor SMTP</label>
                    <input id="smtp_host" name="smtp_host" type="text" value="{{ old('smtp_host') }}" placeholder="mail.tudominio.pe">
                    @error('smtp_host')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="smtp_puerto">Puerto</label>
                    <input id="smtp_puerto" name="smtp_puerto" type="number" value="{{ old('smtp_puerto', 465) }}">
                    @error('smtp_puerto')<div class="error">{{ $message }}</div>@enderror
                </div>
            </div>
            <label for="smtp_cifrado">Cifrado</label>
            <select id="smtp_cifrado" name="smtp_cifrado">
                <option value="ssl" @selected(old('smtp_cifrado', 'ssl') === 'ssl')>SSL (puerto 465)</option>
                <option value="tls" @selected(old('smtp_cifrado') === 'tls')>TLS (puerto 587)</option>
            </select>
            <label for="smtp_usuario">Usuario SMTP</label>
            <input id="smtp_usuario" name="smtp_usuario" type="text" value="{{ old('smtp_usuario') }}" autocomplete="off" placeholder="planillas@tudominio.pe">
            @error('smtp_usuario')<div class="error">{{ $message }}</div>@enderror
            <label for="smtp_clave">Contraseña SMTP</label>
            <input id="smtp_clave" name="smtp_clave" type="password" autocomplete="new-password">
            <label for="correo_remitente">Correo remitente</label>
            <input id="correo_remitente" name="correo_remitente" type="email" value="{{ old('correo_remitente') }}" placeholder="Si lo dejas vacío se usa el usuario SMTP">
            @error('correo_remitente')<div class="error">{{ $message }}</div>@enderror
        </details>

        <label class="casilla">
            <input type="checkbox" name="demo" value="1" @checked(old('demo'))>
            <span>Cargar datos de demostración (empresas y trabajadores ficticios). Solo para probar; no lo marques en una instalación real.</span>
        </label>

        <button type="submit">Instalar</button>
    </form>
@endsection
