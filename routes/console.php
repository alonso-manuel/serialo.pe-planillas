<?php

use App\Support\ModoSistema;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hosting compartido (cPanel): sin procesos permanentes. El cron del cliente corre
// `php artisan schedule:run` cada minuto y aquí se procesa la cola (cálculo masivo, correos).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping();

// Modo SaaS: avisos por correo de demos y planes por vencer o vencidos (no borra nada).
Schedule::command('estudios:avisar-vencimientos')
    ->dailyAt('08:00')
    ->timezone('America/Lima')
    ->when(fn () => ModoSistema::saas());

// Respaldo diario de la base (en SaaS: la central y la de cada estudio). Ver docs/respaldos.md.
Schedule::command('respaldos:crear')
    ->dailyAt((string) config('planillas.respaldos.hora', '03:00'))
    ->timezone('America/Lima')
    ->withoutOverlapping(120);
