const miles = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 0 });

/**
 * "1500.5" → "1,500.50". Solo da formato: el monto ya viene redondeado del servidor
 * y nunca pasa por float (la parte entera se formatea como BigInt).
 */
export function formatoMonto(valor: string | null | undefined): string {
    if (
        valor === null ||
        valor === undefined ||
        !/^-?\d+(\.\d+)?$/.test(valor)
    ) {
        return '—';
    }

    const [entero, decimal = ''] = valor.split('.');

    return `${miles.format(BigInt(entero))}.${decimal.padEnd(2, '0').slice(0, 2)}`;
}
