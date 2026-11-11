<?php

namespace App\Services\Sunat;

use App\Domain\Sunat\TRegistro\DerechohabienteTRegistro;
use App\Domain\Sunat\TRegistro\GeneradorDerechohabientes;
use App\Domain\Sunat\TRegistro\GeneradorTRegistro;
use App\Domain\Sunat\TRegistro\PrestadorTRegistro;
use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Models\Derechohabiente;
use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Models\Trabajador;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Altas y bajas de una empresa en un rango de fechas, para la carga masiva del T-Registro:
 * revisa que cada trabajador tenga los datos que exige SUNAT y arma el zip con RP_<RUC>.ide/.tra/.per/.est
 * para validarlo en el PVS T-Registro. Altas = ingreso dentro del rango; bajas = cese dentro del rango.
 */
class ExportacionTRegistro
{
    public function __construct(
        private readonly GeneradorTRegistro $generador,
        private readonly GeneradorDerechohabientes $derechohabientes,
    ) {}

    /**
     * Filtra una fecha dentro del rango comparando por día (no por texto): un valor guardado con hora
     * («2026-11-30 00:00:00», como en SQLite) cuenta en el último día del rango.
     *
     * @template TModelo of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModelo>  $consulta
     * @return Builder<TModelo>
     */
    private function enRango(Builder $consulta, string $campo, CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        return $consulta->whereDate($campo, '>=', $desde->toDateString())->whereDate($campo, '<=', $hasta->toDateString());
    }

    /**
     * Derechohabientes con alta o baja dentro del rango (trabajadores de la empresa).
     *
     * @return array{altas: list<Derechohabiente>, bajas: list<Derechohabiente>}
     */
    public function derechohabientes(Empresa $empresa, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $base = fn () => Derechohabiente::query()->with('trabajador')
            ->whereHas('trabajador', fn ($q) => $q->where('empresa_id', $empresa->id))
            ->orderBy('apellido_paterno');

        $altas = [];
        foreach ($this->enRango($base(), 'fecha_alta', $desde, $hasta)->get() as $d) {
            $altas[] = $d;
        }
        $bajas = [];
        foreach ($this->enRango($base()->whereNotNull('fecha_baja'), 'fecha_baja', $desde, $hasta)->get() as $d) {
            $bajas[] = $d;
        }

        return ['altas' => $altas, 'bajas' => $bajas];
    }

    /**
     * Zip con RD_<RUC>_<ddmmaaaa>_ALTA.TXT y _BAJA.TXT (fecha de envío: hoy) para la carga masiva del
     * Registro de Derechohabientes en SOL. Devuelve la ruta temporal.
     */
    public function zipDerechohabientes(Empresa $empresa, CarbonImmutable $desde, CarbonImmutable $hasta): string
    {
        $dh = $this->derechohabientes($empresa, $desde, $hasta);
        if ($dh['altas'] === [] && $dh['bajas'] === []) {
            throw new \DomainException('No hay altas ni bajas de derechohabientes en esas fechas.');
        }

        $prefijo = "RD_{$empresa->ruc}_".CarbonImmutable::today()->format('dmY');
        $archivos = [
            "{$prefijo}_ALTA.TXT" => $this->derechohabientes->alta(array_map(fn (Derechohabiente $d) => $this->dhTRegistro($d), $dh['altas'])),
            "{$prefijo}_BAJA.TXT" => $this->derechohabientes->baja(array_map(fn (Derechohabiente $d) => $this->dhTRegistro($d), $dh['bajas'])),
        ];

        return $this->comprimir($archivos);
    }

    private function dhTRegistro(Derechohabiente $d): DerechohabienteTRegistro
    {
        $t = $d->trabajador;

        return new DerechohabienteTRegistro(
            tipoDocumentoTitular: $t->tipo_documento->value,
            numeroDocumentoTitular: $t->numero_documento,
            tipoDocumento: $d->tipo_documento->value,
            numeroDocumento: $d->numero_documento,
            paisEmisor: $d->tipo_documento === TipoDocumento::Pasaporte ? (string) $d->pais_emisor : '604',
            fechaNacimiento: $d->fecha_nacimiento->format('d/m/Y'),
            apellidoPaterno: $this->texto($d->apellido_paterno, 40),
            apellidoMaterno: $this->texto((string) $d->apellido_materno, 40),
            nombres: $this->texto($d->nombres, 40),
            sexo: $d->sexo === 'F' ? '2' : '1',
            vinculo: $d->vinculo->value,
            documentoVinculo: (string) $d->documento_vinculo?->value,
            numeroDocumentoVinculo: (string) $d->numero_documento_vinculo,
            mesConcepcion: $d->mes_concepcion === null ? '' : substr($d->mes_concepcion, 5, 2).substr($d->mes_concepcion, 0, 4),
            fechaBaja: $d->fecha_baja?->format('d/m/Y') ?? '',
            motivoBaja: (string) $d->motivo_baja?->value,
        );
    }

    /**
     * @param  array<string, string>  $archivos  nombre => contenido (los vacíos se omiten)
     */
    private function comprimir(array $archivos): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'treg');
        $zip = new ZipArchive;
        if ($ruta === false || $zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el zip de T-Registro.');
        }
        foreach ($archivos as $nombre => $contenido) {
            if ($contenido !== '') {
                // Los aplicativos de SUNAT leen texto ANSI (Windows-1252): tildes y eñes de los nombres ⚠.
                $zip->addFromString($nombre, (string) mb_convert_encoding($contenido, 'Windows-1252', 'UTF-8'));
            }
        }
        $zip->close();

        return $ruta;
    }

    /**
     * @return array{
     *     altas: list<array{trabajador: Trabajador, problemas: list<string>}>,
     *     bajas: list<array{trabajador: Trabajador, problemas: list<string>}>
     * }
     */
    public function revisar(Empresa $empresa, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $base = fn () => Trabajador::query()->deEmpresa($empresa->id)->with(['afp', 'establecimiento'])->orderBy('apellido_paterno')->orderBy('nombres');
        $altas = [];
        foreach ($this->enRango($base(), 'fecha_ingreso', $desde, $hasta)->get() as $t) {
            $altas[] = ['trabajador' => $t, 'problemas' => $this->problemasAlta($t, $empresa)];
        }

        $bajas = [];
        foreach ($this->enRango($base()->whereNotNull('fecha_cese'), 'fecha_cese', $desde, $hasta)->get() as $t) {
            $bajas[] = ['trabajador' => $t, 'problemas' => $this->problemasBaja($t)];
        }

        return ['altas' => $altas, 'bajas' => $bajas];
    }

    /**
     * Zip con los archivos (solo los que tienen líneas). Devuelve la ruta temporal.
     *
     * @throws \DomainException si hay datos faltantes o nada que exportar
     */
    public function zip(Empresa $empresa, CarbonImmutable $desde, CarbonImmutable $hasta): string
    {
        $revision = $this->revisar($empresa, $desde, $hasta);
        if ($revision['altas'] === [] && $revision['bajas'] === []) {
            throw new \DomainException('No hay altas ni bajas en esas fechas.');
        }
        foreach ([...$revision['altas'], ...$revision['bajas']] as $r) {
            if ($r['problemas'] !== []) {
                throw new \DomainException('Completa los datos de los trabajadores marcados antes de generar los archivos.');
            }
        }

        $altas = array_map(fn (array $r) => $this->prestador($r['trabajador'], $empresa), $revision['altas']);
        $bajas = array_map(fn (array $r) => $this->prestador($r['trabajador'], $empresa), $revision['bajas']);

        $p = "RP_{$empresa->ruc}";

        return $this->comprimir([
            "{$p}.ide" => $this->generador->ide($altas),
            "{$p}.tra" => $this->generador->tra($altas),
            "{$p}.per" => $this->generador->per($altas, $bajas),
            "{$p}.est" => $this->generador->est($altas),
        ]);
    }

    public function nombreZip(Empresa $empresa, CarbonImmutable $desde, CarbonImmutable $hasta): string
    {
        return "T-Registro {$empresa->ruc} {$desde->format('Y-m-d')} a {$hasta->format('Y-m-d')}.zip";
    }

    /** @return list<string> */
    private function problemasBaja(Trabajador $t): array
    {
        $p = [];
        if ($t->motivo_cese === null) {
            $p[] = 'Falta el motivo del cese.';
        }

        return $p;
    }

    /** @return list<string> */
    private function problemasAlta(Trabajador $t, Empresa $empresa): array
    {
        $p = [];
        $falta = function (bool $condicion, string $mensaje) use (&$p): void {
            if ($condicion) {
                $p[] = $mensaje;
            }
        };

        $falta($t->fecha_nacimiento === null, 'Falta la fecha de nacimiento.');
        $falta(! in_array($t->sexo, ['M', 'F'], true), 'Falta el sexo.');
        $falta(preg_match('/^9\d{8}$/', (string) preg_replace('/\D/', '', (string) $t->telefono)) !== 1, 'Falta un celular de 9 dígitos (empieza con 9).');
        $falta(blank($t->email), 'Falta el correo electrónico.');
        $falta($t->categoria_ocupacional === null, 'Falta la categoría ocupacional (ejecutivo, empleado u obrero).');
        $falta(blank($t->ocupacion), 'Falta la ocupación (tabla 30 de SUNAT).');
        $falta($t->situacion_educativa === null, 'Falta la situación educativa.');
        $falta($t->tipo_contrato === TipoContrato::PlazoFijo && $t->modalidad_contrato === null, 'Falta la modalidad del contrato a plazo fijo.');
        $falta($t->tipo_documento !== TipoDocumento::Dni && blank($t->nacionalidad), 'Falta la nacionalidad (tabla 4 de SUNAT).');
        $falta($t->tipo_documento === TipoDocumento::Pasaporte && blank($t->pais_emisor), 'Falta el país emisor del pasaporte (tabla 26).');
        $falta($t->sistema_pension === SistemaPension::Afp && blank($t->afp?->codigo_sunat), 'La AFP del trabajador no tiene código SUNAT.');
        $falta($this->establecimiento($t, $empresa) === null, 'La empresa no tiene establecimiento registrado.');
        $falta(strlen((string) $empresa->ruc) !== 11, 'La empresa no tiene RUC válido.');

        return $p;
    }

    private function prestador(Trabajador $t, Empresa $empresa): PrestadorTRegistro
    {
        $dni = $t->tipo_documento === TipoDocumento::Dni;
        $fecha = fn (?\DateTimeInterface $d) => $d === null ? '' : $d->format('d/m/Y');

        $agrario = $empresa->regimen_laboral === RegimenLaboral::Agrario;

        return new PrestadorTRegistro(
            tipoDocumento: $t->tipo_documento->value,
            numeroDocumento: $t->numero_documento,
            paisEmisor: $t->tipo_documento === TipoDocumento::Pasaporte ? (string) $t->pais_emisor : '604',
            fechaNacimiento: $fecha($t->fecha_nacimiento),
            apellidoPaterno: $this->texto($t->apellido_paterno, 40),
            apellidoMaterno: $this->texto((string) $t->apellido_materno, 40),
            nombres: $this->texto($t->nombres, 40),
            sexo: $t->sexo === 'F' ? '2' : '1',
            nacionalidad: $dni ? '' : (string) $t->nacionalidad,
            telefono: (string) preg_replace('/\D/', '', (string) $t->telefono),
            correo: (string) $t->email,
            regimenLaboral: match ($empresa->regimen_laboral) { // tabla 33
                RegimenLaboral::General => '01',
                RegimenLaboral::PequenaEmpresa => '17',
                RegimenLaboral::Microempresa => '16',
                RegimenLaboral::Agrario => '26',
            },
            situacionEducativa: (string) $t->situacion_educativa?->value,
            ocupacion: (string) $t->ocupacion,
            discapacidad: $t->discapacidad,
            cuspp: $t->sistema_pension === SistemaPension::Afp ? (string) $t->cuspp : '',
            tipoContrato: match ($t->tipo_contrato) { // tabla 12
                TipoContrato::Indeterminado => '1',
                TipoContrato::TiempoParcial => '2',
                TipoContrato::PlazoFijo => (string) $t->modalidad_contrato?->value,
            },
            sindicalizado: $t->sindicalizado,
            remuneracionBasica: (string) $t->remuneracion_basica,
            situacionEspecial: $t->situacion_especial->value,
            tipoPago: blank($t->cuenta_sueldo) ? '1' : '2', // tabla 16: efectivo o depósito en cuenta
            categoriaOcupacional: (string) $t->categoria_ocupacional?->value,
            // Tabla 8: agrario Ley 31110 = 76. Tabla 32: EsSalud agrario/acuícola = 04.
            tipoTrabajador: $agrario ? '76' : (string) $t->categoria_ocupacional?->tipoTrabajador(),
            regimenSalud: match (true) {
                $t->seguro_salud === SeguroSalud::Sis => '21',
                $agrario => '04',
                default => '00',
            }, // tabla 32
            regimenPensionario: match ($t->sistema_pension) { // tabla 11
                SistemaPension::Onp => '02',
                SistemaPension::Afp => (string) $t->afp?->codigo_sunat,
                SistemaPension::Ninguno => '99',
            },
            fechaIngreso: $fecha($t->fecha_ingreso),
            fechaCese: $fecha($t->fecha_cese),
            motivoCese: (string) $t->motivo_cese?->value,
            ruc: (string) $empresa->ruc,
            establecimiento: (string) $this->establecimiento($t, $empresa)?->codigo,
        );
    }

    /** El del trabajador o, si no tiene, el principal de la empresa. */
    private function establecimiento(Trabajador $t, Empresa $empresa): ?Establecimiento
    {
        return $t->establecimiento ?? $empresa->establecimientos()->orderBy('codigo')->first();
    }

    private function texto(string $valor, int $largo): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $valor)), 0, $largo);
    }

    /** @return Collection<int, object{codigo: string, nombre: string}> */
    public static function buscarOcupaciones(string $texto, ?string $columnaCategoria): Collection
    {
        $q = DB::table('ocupaciones_sunat')->select(['codigo', 'nombre'])->orderBy('nombre')->limit(20);
        if ($columnaCategoria !== null) {
            $q->where($columnaCategoria, true);
        }
        $texto = trim($texto);
        if (preg_match('/^\d+$/', $texto) === 1) {
            $q->where('codigo', 'like', $texto.'%');
        } elseif ($texto !== '') {
            foreach (preg_split('/\s+/', mb_strtoupper($texto)) ?: [] as $palabra) {
                $q->where('nombre', 'like', '%'.$palabra.'%');
            }
        }

        /** @var Collection<int, object{codigo: string, nombre: string}> */
        return $q->get();
    }
}
