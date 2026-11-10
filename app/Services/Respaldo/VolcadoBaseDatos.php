<?php

namespace App\Services\Respaldo;

use Illuminate\Database\Connection;
use PDO;
use RuntimeException;

/**
 * Vuelca una base de datos completa a un archivo .sql.gz con PHP puro (PDO).
 *
 * No usa `mysqldump`: en el hosting compartido del cliente `exec()` suele estar deshabilitado.
 * El archivo se importa tal cual en phpMyAdmin (acepta .gz) o con `gunzip < x.sql.gz | mysql base`.
 * Soporta MySQL/MariaDB (producción) y SQLite (tests).
 */
class VolcadoBaseDatos
{
    /** Tablas volátiles: se respalda su estructura, no sus filas. */
    private const SIN_DATOS = ['sessions', 'sesiones_panel', 'cache', 'cache_locks'];

    private const FILAS_POR_LOTE = 200;

    /** @return array{tablas: int, filas: int} */
    public function volcar(Connection $conexion, string $destino): array
    {
        $temporal = $destino.'.parcial';
        $gz = gzopen($temporal, 'wb6');
        if ($gz === false) {
            throw new RuntimeException("No se pudo escribir {$temporal}.");
        }

        $sqlite = $conexion->getDriverName() === 'sqlite';
        $pdo = $conexion->getPdo();
        $filas = 0;
        $tablas = $this->tablas($conexion, $sqlite);

        try {
            $this->escribir($gz, "-- Respaldo de Serialo Planillas\n-- Base: {$conexion->getDatabaseName()}\n-- Fecha: ".now()->toDateTimeString()."\n\n");
            $this->escribir($gz, $sqlite ? "PRAGMA foreign_keys=OFF;\n\n" : "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach ($tablas as $tabla) {
                $q = $this->identificador($tabla, $sqlite);
                $this->escribir($gz, "DROP TABLE IF EXISTS {$q};\n".$this->estructura($conexion, $tabla, $sqlite).";\n");

                if (! in_array($tabla, self::SIN_DATOS, true)) {
                    $filas += $this->datos($conexion, $pdo, $gz, $tabla, $sqlite);
                }

                $this->escribir($gz, "\n");
            }

            if ($sqlite) {
                foreach ($conexion->select("select sql from sqlite_master where type = 'index' and sql is not null") as $indice) {
                    $this->escribir($gz, ((array) $indice)['sql'].";\n");
                }
            }

            $this->escribir($gz, $sqlite ? "\nPRAGMA foreign_keys=ON;\n" : "\nSET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            gzclose($gz);
        }

        if (! rename($temporal, $destino)) {
            @unlink($temporal);
            throw new RuntimeException("No se pudo guardar {$destino}.");
        }

        return ['tablas' => count($tablas), 'filas' => $filas];
    }

    /** @return list<string> */
    private function tablas(Connection $conexion, bool $sqlite): array
    {
        $filas = $sqlite
            ? $conexion->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name")
            : $conexion->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

        $tablas = [];
        foreach ($filas as $fila) {
            $tablas[] = (string) array_values((array) $fila)[0];
        }
        sort($tablas);

        return $tablas;
    }

    private function estructura(Connection $conexion, string $tabla, bool $sqlite): string
    {
        if ($sqlite) {
            $fila = $conexion->selectOne("select sql from sqlite_master where type = 'table' and name = ?", [$tabla]);

            return (string) ((array) $fila)['sql'];
        }

        $fila = (array) $conexion->selectOne('SHOW CREATE TABLE '.$this->identificador($tabla, false));

        return (string) ($fila['Create Table'] ?? array_values($fila)[1]);
    }

    /** @param  resource  $gz */
    private function datos(Connection $conexion, PDO $pdo, $gz, string $tabla, bool $sqlite): int
    {
        $q = $this->identificador($tabla, $sqlite);
        $columnas = null;
        $orden = $this->columnaOrden($conexion, $tabla, $sqlite);
        $total = 0;

        for ($desde = 0; ; $desde += self::FILAS_POR_LOTE) {
            $lote = $conexion->select("select * from {$q}".($orden !== null ? " order by {$orden}" : '').' limit '.self::FILAS_POR_LOTE.' offset '.$desde);
            if ($lote === []) {
                break;
            }

            $valores = [];
            foreach ($lote as $fila) {
                $fila = (array) $fila;
                $columnas ??= implode(', ', array_map(fn (string $c) => $this->identificador($c, $sqlite), array_keys($fila)));
                $valores[] = '('.implode(', ', array_map(fn (mixed $v) => $this->valor($pdo, $v, $sqlite), array_values($fila))).')';
            }

            $this->escribir($gz, "INSERT INTO {$q} ({$columnas}) VALUES\n".implode(",\n", $valores).";\n");
            $total += count($lote);

            if (count($lote) < self::FILAS_POR_LOTE) {
                break;
            }
        }

        return $total;
    }

    /** Orden estable para paginar: la llave primaria si existe. */
    private function columnaOrden(Connection $conexion, string $tabla, bool $sqlite): ?string
    {
        $llave = [];
        if ($sqlite) {
            foreach ($conexion->select('pragma table_info('.$this->identificador($tabla, true).')') as $c) {
                $c = (array) $c;
                if ((int) $c['pk'] > 0) {
                    $llave[(int) $c['pk']] = (string) $c['name'];
                }
            }
            ksort($llave);
        } else {
            foreach ($conexion->select('SHOW KEYS FROM '.$this->identificador($tabla, false)." WHERE Key_name = 'PRIMARY'") as $c) {
                $c = (array) $c;
                $llave[(int) $c['Seq_in_index']] = (string) $c['Column_name'];
            }
            ksort($llave);
        }

        return $llave === [] ? null : implode(', ', array_map(fn (string $c) => $this->identificador($c, $sqlite), $llave));
    }

    private function valor(PDO $pdo, mixed $valor, bool $sqlite): string
    {
        return match (true) {
            $valor === null => 'NULL',
            is_bool($valor) => $valor ? '1' : '0',
            is_int($valor), is_float($valor) => (string) $valor,
            ! mb_check_encoding((string) $valor, 'UTF-8') => ($sqlite ? "X'" : '0x').bin2hex((string) $valor).($sqlite ? "'" : ''),
            default => (string) $pdo->quote((string) $valor),
        };
    }

    private function identificador(string $nombre, bool $sqlite): string
    {
        return $sqlite ? '"'.str_replace('"', '""', $nombre).'"' : '`'.str_replace('`', '``', $nombre).'`';
    }

    /** @param  resource  $gz */
    private function escribir($gz, string $texto): void
    {
        if (gzwrite($gz, $texto) === false) {
            throw new RuntimeException('No se pudo escribir el respaldo (¿disco lleno?).');
        }
    }
}
