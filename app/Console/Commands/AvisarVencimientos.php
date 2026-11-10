<?php

namespace App\Console\Commands;

use App\Enums\EstadoEstudio;
use App\Enums\TipoEstudio;
use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Estudio;
use App\Notifications\AvisoVencimientoCuenta;
use App\Support\ModoSistema;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Modo SaaS (diario, desde el cron): avisa por correo al administrador de cada cuenta cuyo vencimiento
 * está a 3 días o menos, y otra vez cuando vence. Un aviso por estado; no borra nada.
 */
class AvisarVencimientos extends Command
{
    protected $signature = 'estudios:avisar-vencimientos';

    protected $description = 'Avisa por correo las demos y planes por vencer o vencidos (SaaS)';

    private const DIAS_ANTES = 3;

    public function handle(): int
    {
        if (! ModoSistema::saas()) {
            $this->error('Solo disponible con PLANILLAS_MODO=saas.');

            return self::FAILURE;
        }

        $hoy = CarbonImmutable::today();
        $enviados = 0;

        $cuentas = Estudio::query()->whereNotNull('vence_en')->whereNotNull('correo_admin')
            ->where('estado', EstadoEstudio::Activo->value)
            ->whereDate('vence_en', '<=', $hoy->addDays(self::DIAS_ANTES))
            ->get();

        foreach ($cuentas as $cuenta) {
            if (! $cuenta instanceof Estudio || $cuenta->vence_en === null || $cuenta->correo_admin === null) {
                continue;
            }

            $vencida = $cuenta->vencida($hoy);
            $aviso = $vencida ? 'vencida' : 'por_vencer';
            if ($cuenta->aviso_vencimiento === $aviso || ($aviso === 'por_vencer' && $cuenta->aviso_vencimiento === 'vencida')) {
                continue; // ya se avisó
            }

            try {
                Notification::route('mail', $cuenta->correo_admin)->notify(new AvisoVencimientoCuenta(
                    cuenta: $cuenta->nombre,
                    direccion: $cuenta->url(),
                    fecha: $cuenta->vence_en->format('d/m/Y'),
                    vencida: $vencida,
                    esDemo: $cuenta->tipo === TipoEstudio::Demo,
                ));
            } catch (Throwable $e) {
                report($e);
                $this->warn("No se pudo avisar a {$cuenta->correo_admin}: {$e->getMessage()}");

                continue;
            }

            $cuenta->update(['aviso_vencimiento' => $aviso]);
            AuditoriaCentral::registrar('aviso', ($vencida ? 'Aviso de vencimiento' : 'Aviso de próximo vencimiento')." a {$cuenta->correo_admin}", $cuenta->id);
            $enviados++;
            $this->line("{$cuenta->id}: aviso «{$aviso}» enviado a {$cuenta->correo_admin}");
        }

        $this->info("Avisos enviados: {$enviados}.");

        return self::SUCCESS;
    }
}
