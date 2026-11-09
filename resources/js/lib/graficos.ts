/**
 * Utilidades de los gráficos del panel. Los montos llegan como texto ("1234.56");
 * aquí solo se convierten a número para ubicar puntos en pantalla, nunca para calcular dinero.
 */

const compacto = new Intl.NumberFormat('es-PE', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

/** "1234.56" → 1234.56 (solo para dibujar); null o texto inválido → null. */
export function aNumero(valor: string | null | undefined): number | null {
    if (
        valor === null ||
        valor === undefined ||
        !/^-?\d+(\.\d+)?$/.test(valor)
    ) {
        return null;
    }

    return Number(valor);
}

/** 12500 → "12.5 mil" (ejes y etiquetas cortas). */
export function formatoCompacto(valor: number): string {
    return compacto.format(valor);
}

/** Marcas del eje: de 0 a un máximo "redondo" en 4 o 5 pasos. */
export function marcasEje(maximo: number, pasos = 4): number[] {
    if (maximo <= 0) {
        return [0, 1];
    }

    const crudo = maximo / pasos;
    const potencia = 10 ** Math.floor(Math.log10(crudo));
    const factor = [1, 2, 2.5, 5, 10].find((f) => f * potencia >= crudo) ?? 10;
    const paso = factor * potencia;
    const tope = Math.ceil(maximo / paso) * paso;
    const marcas: number[] = [];

    for (let v = 0; v <= tope + paso / 2; v += paso) {
        marcas.push(v);
    }

    return marcas;
}

function centimos(valor: string): bigint {
    const negativo = valor.startsWith('-');
    const [entero, decimal = ''] = valor.replace('-', '').split('.');
    const c =
        BigInt(entero) * 100n + BigInt(decimal.padEnd(2, '0').slice(0, 2));

    return negativo ? -c : c;
}

/**
 * Parte de un total en porcentaje con un decimal ("12.3"), calculado en céntimos enteros.
 * Total cero → "0.0".
 */
export function porcentaje(parte: string, total: string): string {
    const t = centimos(total);

    if (t === 0n) {
        return '0.0';
    }

    const milesimas = (centimos(parte) * 1000n * 2n + t) / (t * 2n); // redondeo a 1 decimal

    return `${milesimas / 10n}.${(milesimas < 0n ? -milesimas : milesimas) % 10n}`;
}

/** Variación porcentual con signo ("+4.2", "-1.0") o null si no hay base. */
export function variacion(
    actual: string | null,
    anterior: string | null,
): string | null {
    if (actual === null || anterior === null) {
        return null;
    }

    const a = centimos(actual);
    const b = centimos(anterior);

    if (b === 0n) {
        return null;
    }

    const diff = (a - b) * 1000n;
    const milesimas = (diff * 2n + (diff >= 0n ? b : -b)) / (b * 2n);
    const abs = milesimas < 0n ? -milesimas : milesimas;

    return `${milesimas < 0n ? '-' : '+'}${abs / 10n}.${abs % 10n}`;
}
