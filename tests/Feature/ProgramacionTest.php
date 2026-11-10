<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Tareas programadas: en hosting compartido el cron atiende la cola; en el VPS lo hace el servicio permanente. */
class ProgramacionTest extends TestCase
{
    private function tarea(string $comando): Event
    {
        foreach (app(Schedule::class)->events() as $evento) {
            if (str_contains((string) $evento->command, $comando)) {
                return $evento;
            }
        }

        $this->fail("No está programado {$comando}");
    }

    #[Test]
    public function el_cron_atiende_la_cola_salvo_que_haya_un_servicio_permanente(): void
    {
        $this->assertTrue($this->tarea('queue:work')->filtersPass($this->app));

        config(['planillas.cola_permanente' => true]);
        $this->assertFalse($this->tarea('queue:work')->filtersPass($this->app));
    }

    #[Test]
    public function los_respaldos_se_programan_cada_dia_a_la_hora_de_lima(): void
    {
        $respaldos = $this->tarea('respaldos:crear');

        $this->assertSame('0 3 * * *', $respaldos->expression);
        $this->assertSame('America/Lima', $respaldos->timezone);
    }
}
