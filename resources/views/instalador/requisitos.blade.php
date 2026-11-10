@extends('instalador.layout')

@section('contenido')
    <h1>Requisitos del servidor</h1>
    <p class="ayuda">Revisamos que tu hosting tenga todo lo necesario para que el sistema funcione.</p>

    <ul class="requisitos">
        @foreach ($requisitos as $r)
            <li>
                <span class="{{ $r['ok'] ? 'si' : 'no' }}" aria-label="{{ $r['ok'] ? 'Cumple' : 'No cumple' }}">{{ $r['ok'] ? '✔' : '✖' }}</span>
                <span>
                    {{ $r['nombre'] }}
                    @unless ($r['ok'])
                        <small>{{ $r['ayuda'] }}</small>
                    @endunless
                </span>
            </li>
        @endforeach
    </ul>

    @unless ($cumple)
        <div class="aviso">Corrige los puntos marcados con ✖ y vuelve a cargar esta página.</div>
    @endunless

    <h2>Código de instalación</h2>
    <p class="ayuda">
        Para que solo el dueño del hosting pueda instalar, abre el archivo
        <code>{{ $rutaCodigo }}</code> desde el administrador de archivos de cPanel y copia el código.
    </p>

    <form method="POST" action="{{ route('instalar.codigo') }}">
        @csrf
        <label for="codigo">Código</label>
        <input id="codigo" name="codigo" type="text" autocomplete="off" placeholder="XXXX-XXXX-XXXX" value="{{ old('codigo') }}" required>
        @error('codigo')<div class="error">{{ $message }}</div>@enderror
        <button type="submit" @disabled(! $cumple)>Continuar</button>
    </form>
@endsection
