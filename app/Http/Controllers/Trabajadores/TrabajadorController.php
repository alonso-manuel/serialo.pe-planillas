<?php

namespace App\Http\Controllers\Trabajadores;

use App\Enums\ClaveParametro;
use App\Enums\MotivoCese;
use App\Enums\RegimenLaboral;
use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trabajadores\GuardarTrabajadorRequest;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Models\Parametro;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Dni\ConsultaDni;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Trabajadores\HistorialTrabajador;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Trabajadores de la empresa activa (rutas con middleware `empresa.activa`). */
class TrabajadorController extends Controller
{
    public function __construct(
        private readonly EmpresaActiva $empresaActiva,
        private readonly HistorialTrabajador $historial,
    ) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresa($request);
        $hoy = CarbonImmutable::today()->toDateString();

        $buscar = trim($request->string('buscar')->toString());
        $estado = in_array($request->query('estado'), ['cesados', 'todos'], true) ? (string) $request->query('estado') : 'activos';
        $pension = SistemaPension::tryFrom($request->string('pension')->toString());
        $establecimiento = $request->integer('establecimiento') ?: null;

        $trabajadores = Trabajador::query()
            ->deEmpresa($empresa->id)
            ->with(['establecimiento:id,codigo,nombre', 'historial'])
            ->when($buscar !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('numero_documento', 'like', "{$buscar}%")
                ->orWhere('apellido_paterno', 'like', "%{$buscar}%")
                ->orWhere('apellido_materno', 'like', "%{$buscar}%")
                ->orWhere('nombres', 'like', "%{$buscar}%")))
            ->when($estado === 'activos', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereNull('fecha_cese')->orWhereDate('fecha_cese', '>', $hoy)))
            ->when($estado === 'cesados', fn (Builder $q) => $q->whereDate('fecha_cese', '<=', $hoy))
            ->when($pension !== null, fn (Builder $q) => $q->where('sistema_pension', $pension))
            ->when($establecimiento !== null, fn (Builder $q) => $q->where('establecimiento_id', $establecimiento))
            ->orderBy('apellido_paterno')->orderBy('apellido_materno')->orderBy('nombres')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Trabajador $t) => [
                'id' => $t->id,
                'documento' => "{$t->tipo_documento->abreviatura()} {$t->numero_documento}",
                'apellido_paterno' => $t->apellido_paterno,
                'nombre_completo' => $t->nombre_completo,
                'cargo' => $t->cargo,
                'establecimiento' => $t->establecimiento?->nombre,
                'fecha_ingreso' => $t->fecha_ingreso->format('d/m/Y'),
                'remuneracion_basica' => $this->historial->valoresAl($t, CarbonImmutable::today())['remuneracion_basica'],
                'pension' => $t->sistema_pension === SistemaPension::Afp ? 'AFP' : ($t->sistema_pension === SistemaPension::Onp ? 'ONP' : '—'),
                'cesado' => $t->estaCesado(),
                'fecha_cese' => $t->fecha_cese?->format('d/m/Y'),
            ]);

        return Inertia::render('trabajadores/Index', [
            'trabajadores' => $trabajadores,
            'filtros' => [
                'buscar' => $buscar,
                'estado' => $estado,
                'pension' => $pension?->value,
                'establecimiento' => $establecimiento,
            ],
            'establecimientos' => $this->establecimientos($empresa),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Trabajador::class);

        return Inertia::render('trabajadores/Form', [
            'trabajador' => null,
            'catalogos' => $this->catalogos($this->empresa($request)),
        ]);
    }

    public function store(GuardarTrabajadorRequest $request): RedirectResponse
    {
        $empresa = $request->empresa();
        $trabajador = $empresa->trabajadores()->create($request->datos());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Trabajador {$trabajador->nombre_completo} registrado."]);

        return to_route('trabajadores.index');
    }

    public function edit(Trabajador $trabajador): Response
    {
        Gate::authorize('update', $trabajador);

        /** @var Empresa $empresa */
        $empresa = $trabajador->empresa;

        return Inertia::render('trabajadores/Form', [
            'trabajador' => [
                'id' => $trabajador->id,
                'tipo_documento' => $trabajador->tipo_documento->value,
                'numero_documento' => $trabajador->numero_documento,
                'apellido_paterno' => $trabajador->apellido_paterno,
                'apellido_materno' => $trabajador->apellido_materno,
                'nombres' => $trabajador->nombres,
                'fecha_nacimiento' => $trabajador->fecha_nacimiento?->toDateString(),
                'sexo' => $trabajador->sexo,
                'email' => $trabajador->email,
                'telefono' => $trabajador->telefono,
                'establecimiento_id' => $trabajador->establecimiento_id,
                'cargo' => $trabajador->cargo,
                'fecha_ingreso' => $trabajador->fecha_ingreso->toDateString(),
                'tipo_contrato' => $trabajador->tipo_contrato->value,
                'remuneracion_basica' => $trabajador->remuneracion_basica,
                'asignacion_familiar' => $trabajador->asignacion_familiar,
                'sistema_pension' => $trabajador->sistema_pension->value,
                'afp_id' => $trabajador->afp_id,
                'cuspp' => $trabajador->cuspp,
                'tipo_comision_afp' => $trabajador->tipo_comision_afp?->value,
                'seguro_salud' => $trabajador->seguro_salud->value,
                'cuenta_sueldo' => $trabajador->cuenta_sueldo,
                'cuenta_cts' => $trabajador->cuenta_cts,
                'nombre_completo' => $trabajador->nombre_completo,
                'cesado' => $trabajador->estaCesado(),
                'cese' => $trabajador->fecha_cese === null ? null : [
                    'fecha' => $trabajador->fecha_cese->format('d/m/Y'),
                    'fecha_iso' => $trabajador->fecha_cese->toDateString(),
                    'motivo' => $trabajador->motivo_cese?->value,
                    'motivo_etiqueta' => $trabajador->motivo_cese?->etiqueta(),
                ],
                'en_planilla' => $trabajador->lineas()->exists(),
            ],
            'catalogos' => $this->catalogos($empresa),
            'historial' => $this->historial->lineaDeTiempo($trabajador),
            'grupos_historial' => collect(HistorialTrabajador::GRUPOS)
                ->map(fn (array $g, string $clave) => ['value' => $clave, 'label' => $g[0]])
                ->values()->all(),
        ]);
    }

    public function update(GuardarTrabajadorRequest $request, Trabajador $trabajador): RedirectResponse
    {
        $trabajador->update($request->datos());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Ficha de {$trabajador->nombre_completo} actualizada."]);

        return to_route('trabajadores.index');
    }

    public function destroy(Trabajador $trabajador): RedirectResponse
    {
        Gate::authorize('delete', $trabajador);

        if ($trabajador->lineas()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Este trabajador ya figura en planillas: registra su cese en lugar de eliminarlo.',
            ]);

            return back();
        }

        // Nunca estuvo en planilla: se elimina del todo para poder registrarlo de nuevo.
        $trabajador->forceDelete();
        Inertia::flash('toast', ['type' => 'success', 'message' => "Trabajador {$trabajador->nombre_completo} eliminado."]);

        return to_route('trabajadores.index');
    }

    private function empresa(Request $request): Empresa
    {
        /** @var User $usuario */
        $usuario = $request->user();

        // El middleware `empresa.activa` garantiza que existe.
        return $this->empresaActiva->obtener($usuario) ?? abort(409);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function establecimientos(Empresa $empresa): array
    {
        return $empresa->establecimientos()->orderBy('codigo')->get()
            ->map(fn (Establecimiento $e) => ['value' => (string) $e->id, 'label' => "{$e->codigo} · {$e->nombre}"])
            ->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(Empresa $empresa): array
    {
        $opciones = fn (array $casos) => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->etiqueta()], $casos);

        try {
            $rmv = Parametro::vigente(ClaveParametro::Rmv, CarbonImmutable::today()->toDateString());
        } catch (\RuntimeException) {
            $rmv = null;
        }

        return [
            'empresa' => $empresa->razon_social,
            'establecimientos' => $this->establecimientos($empresa),
            'afps' => Afp::query()->where('activo', true)->orderBy('nombre')->get()
                ->map(fn (Afp $a) => ['value' => (string) $a->id, 'label' => $a->nombre])->values()->all(),
            'tipos_documento' => $opciones(TipoDocumento::cases()),
            'tipos_contrato' => $opciones(TipoContrato::cases()),
            'tipos_comision' => $opciones(TipoComisionAfp::cases()),
            'motivos_cese' => array_map(fn (MotivoCese $m) => ['value' => $m->value, 'label' => "{$m->value} · {$m->etiqueta()}"], MotivoCese::cases()),
            'seguro_por_defecto' => $empresa->seguro_salud->value,
            'permite_sis' => $empresa->regimen_laboral === RegimenLaboral::Microempresa,
            'rmv' => $rmv,
            'consulta_dni' => app(ConsultaDni::class)->disponible(),
        ];
    }
}
