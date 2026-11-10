@extends('instalador.layout')

@section('contenido')
    <h1>Base de datos</h1>
    <p class="ayuda">
        Crea en cPanel («Bases de datos MySQL») una base de datos vacía y un usuario con todos los
        privilegios sobre ella. Luego escribe aquí sus datos.
    </p>

    @error('conexion')<div class="alerta">{{ $message }}</div>@enderror

    <form method="POST" action="{{ route('instalar.base-datos.store') }}">
        @csrf
        <div class="fila">
            <div>
                <label for="host">Servidor</label>
                <input id="host" name="host" type="text" value="{{ old('host', 'localhost') }}" required>
                @error('host')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="puerto">Puerto</label>
                <input id="puerto" name="puerto" type="number" value="{{ old('puerto', 3306) }}" required>
                @error('puerto')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <label for="nombre">Nombre de la base de datos</label>
        <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" placeholder="usuariocpanel_planillas" required>
        @error('nombre')<div class="error">{{ $message }}</div>@enderror

        <label for="usuario">Usuario</label>
        <input id="usuario" name="usuario" type="text" value="{{ old('usuario') }}" autocomplete="off" required>
        @error('usuario')<div class="error">{{ $message }}</div>@enderror

        <label for="clave">Contraseña</label>
        <input id="clave" name="clave" type="password" autocomplete="new-password">
        @error('clave')<div class="error">{{ $message }}</div>@enderror

        <button type="submit">Probar conexión y continuar</button>
    </form>
@endsection
