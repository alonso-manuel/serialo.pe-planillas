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

/** Suma montos "1234.56" en céntimos enteros (BigInt) y devuelve "1234.56". */
export function sumarMontos(valores: (string | null | undefined)[]): string {
    const total = valores.reduce((suma, valor) => {
        if (!valor || !/^-?\d+(\.\d+)?$/.test(valor)) {
            return suma;
        }

        const negativo = valor.startsWith('-');
        const [entero, decimal = ''] = valor.replace('-', '').split('.');
        const centimos =
            BigInt(entero) * 100n + BigInt(decimal.padEnd(2, '0').slice(0, 2));

        return suma + (negativo ? -centimos : centimos);
    }, 0n);

    const negativo = total < 0n;
    const absoluto = negativo ? -total : total;

    return `${negativo ? '-' : ''}${absoluto / 100n}.${String(absoluto % 100n).padStart(2, '0')}`;
}
