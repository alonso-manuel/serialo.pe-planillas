<?php

namespace App\Support;

/**
 * Catálogo de videos de la Guía (menú Ayuda → Guía), agrupados por tema en el orden en que se aprende el sistema.
 * Cada video es `<clave>.mp4` en el directorio de la guía (config `planillas.guia.directorio`); los prepara
 * `tools/videos/preparar-guia.mjs` desde los originales. Guiones: docs/videos/guiones.md (el número es el del guion).
 */
final class GuiaVideos
{
    /** @var array<string, list<array{clave: string, numero: int, titulo: string, descripcion: string}>> */
    public const SECCIONES = [
        'Primeros pasos' => [
            ['clave' => 'introduccion', 'numero' => 0, 'titulo' => 'Conoce Serialo Planillas', 'descripcion' => 'Qué hace el sistema y cómo está organizado.'],
            ['clave' => 'login', 'numero' => 1, 'titulo' => 'Acceso al sistema', 'descripcion' => 'Ingreso con contraseña o passkey, empresa activa y doble factor.'],
            ['clave' => 'panel-administrativo', 'numero' => 10, 'titulo' => 'Panel del estudio', 'descripcion' => 'El resumen de todas tus empresas y lo que está pendiente.'],
        ],
        'Configuración' => [
            ['clave' => 'empresas', 'numero' => 2, 'titulo' => 'Empresas', 'descripcion' => 'Registro de empresas con su RUC, régimen laboral y seguro de salud.'],
            ['clave' => 'usuarios', 'numero' => 3, 'titulo' => 'Usuarios', 'descripcion' => 'Administradores y asistentes, y qué empresas ve cada uno.'],
            ['clave' => 'parametros', 'numero' => 4, 'titulo' => 'Parámetros legales', 'descripcion' => 'RMV, UIT y tasas con su fecha de vigencia.'],
            ['clave' => 'tasas-afp', 'numero' => 5, 'titulo' => 'Tasas AFP', 'descripcion' => 'Comisiones y prima de seguro de cada AFP por mes.'],
            ['clave' => 'conceptos', 'numero' => 6, 'titulo' => 'Conceptos', 'descripcion' => 'Ingresos, descuentos y aportes con su código de la tabla 22.'],
            ['clave' => 'centros-costo', 'numero' => 21, 'titulo' => 'Centros de costo', 'descripcion' => 'Distribuye la planilla por áreas o proyectos.'],
            ['clave' => 'firma-digital', 'numero' => 28, 'titulo' => 'Firma digital', 'descripcion' => 'Certificado del representante legal para firmar las boletas.'],
        ],
        'Trabajadores' => [
            ['clave' => 'trabajadores', 'numero' => 7, 'titulo' => 'Trabajadores', 'descripcion' => 'Alta, datos laborales, pensión y carga masiva desde Excel.'],
            ['clave' => 'ficha-sunat', 'numero' => 18, 'titulo' => 'Ficha y datos SUNAT', 'descripcion' => 'Datos que exigen la PLAME y el T-Registro.'],
            ['clave' => 'derechohabientes', 'numero' => 19, 'titulo' => 'Derechohabientes', 'descripcion' => 'Familiares con derecho a EsSalud.'],
            ['clave' => 'prestamos', 'numero' => 20, 'titulo' => 'Préstamos', 'descripcion' => 'Préstamos en cuotas que se descuentan solos en la planilla.'],
        ],
        'Planilla mensual' => [
            ['clave' => 'periodos', 'numero' => 8, 'titulo' => 'Periodos y planilla', 'descripcion' => 'Novedades del mes, cálculo, revisión y cierre.'],
            ['clave' => 'asistencia', 'numero' => 22, 'titulo' => 'Importar asistencia', 'descripcion' => 'Carga las faltas y horas extra desde un archivo.'],
            ['clave' => 'boletas', 'numero' => 11, 'titulo' => 'Boletas de pago', 'descripcion' => 'Boletas en PDF, en un zip o enviadas por correo a cada trabajador.'],
            ['clave' => 'quinta', 'numero' => 23, 'titulo' => 'Renta de quinta categoría', 'descripcion' => 'Retención mensual y certificado anual.'],
            ['clave' => 'honorarios', 'numero' => 24, 'titulo' => 'Honorarios (cuarta categoría)', 'descripcion' => 'Recibos por honorarios del mes y su retención.'],
            ['clave' => 'pagos-reportes', 'numero' => 27, 'titulo' => 'Pagos', 'descripcion' => 'Archivo de transferencia para el banco y reportes del periodo.'],
        ],
        'Beneficios sociales' => [
            ['clave' => 'gratificaciones', 'numero' => 12, 'titulo' => 'Gratificaciones', 'descripcion' => 'Julio y diciembre, con su bonificación extraordinaria.'],
            ['clave' => 'cts', 'numero' => 13, 'titulo' => 'CTS', 'descripcion' => 'Depósitos de mayo y noviembre.'],
            ['clave' => 'vacaciones', 'numero' => 14, 'titulo' => 'Vacaciones', 'descripcion' => 'Récord vacacional, goce y pago.'],
            ['clave' => 'liquidacion', 'numero' => 25, 'titulo' => 'Liquidación al cese', 'descripcion' => 'Beneficios pendientes cuando un trabajador se retira.'],
        ],
        'Archivos oficiales' => [
            ['clave' => 'plame', 'numero' => 15, 'titulo' => 'PLAME', 'descripcion' => 'Archivos para importar en la PLAME de SUNAT.'],
            ['clave' => 'afpnet', 'numero' => 16, 'titulo' => 'AFPnet', 'descripcion' => 'Archivo de aportes para AFPnet.'],
            ['clave' => 't-registro', 'numero' => 26, 'titulo' => 'T-Registro', 'descripcion' => 'Altas, bajas y cambios de los trabajadores.'],
        ],
        'Reportes y control' => [
            ['clave' => 'reportes', 'numero' => 17, 'titulo' => 'Reportes', 'descripcion' => 'Planilla, resumen de pagos, asiento contable y costo laboral, en Excel y PDF.'],
            ['clave' => 'auditoria', 'numero' => 9, 'titulo' => 'Auditoría', 'descripcion' => 'Quién cambió qué y cuándo.'],
            ['clave' => 'respaldo', 'numero' => 29, 'titulo' => 'Respaldos y licencia', 'descripcion' => 'Copias de seguridad y estado de la licencia.'],
        ],
        'Regímenes especiales' => [
            ['clave' => 'agrario', 'numero' => 30, 'titulo' => 'Régimen agrario', 'descripcion' => 'Planilla del régimen agrario (Ley 31110).'],
        ],
    ];

    /** @return array{clave: string, numero: int, titulo: string, descripcion: string}|null */
    public static function buscar(string $clave): ?array
    {
        foreach (self::SECCIONES as $videos) {
            foreach ($videos as $video) {
                if ($video['clave'] === $clave) {
                    return $video;
                }
            }
        }

        return null;
    }

    public static function archivo(string $clave): string
    {
        return $clave.'.mp4';
    }

    public static function ruta(string $clave): string
    {
        return rtrim((string) config('planillas.guia.directorio'), '/\\').DIRECTORY_SEPARATOR.self::archivo($clave);
    }
}
