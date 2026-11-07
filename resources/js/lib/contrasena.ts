/*
 * Generador de contraseñas sugeridas para usuarios nuevos.
 * Sin caracteres que se confunden al dictarlos o copiarlos a mano (0/O, 1/l/I).
 * Cumple la política de producción: mayúscula, minúscula, número y símbolo.
 */

const MAYUSCULAS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
const MINUSCULAS = 'abcdefghijkmnopqrstuvwxyz';
const NUMEROS = '23456789';
const SIMBOLOS = '!@#$%*-_+?';

function aleatorio(maximo: number): number {
    const valores = new Uint32Array(1);
    crypto.getRandomValues(valores);

    return valores[0] % maximo;
}

function caracterDe(conjunto: string): string {
    return conjunto[aleatorio(conjunto.length)];
}

export function generarContrasena(longitud = 14): string {
    const todos = MAYUSCULAS + MINUSCULAS + NUMEROS + SIMBOLOS;
    const caracteres = [
        caracterDe(MAYUSCULAS),
        caracterDe(MINUSCULAS),
        caracterDe(NUMEROS),
        caracterDe(SIMBOLOS),
    ];

    while (caracteres.length < longitud) {
        caracteres.push(caracterDe(todos));
    }

    // Mezcla Fisher–Yates para que los obligatorios no queden siempre al inicio.
    for (let i = caracteres.length - 1; i > 0; i--) {
        const j = aleatorio(i + 1);
        [caracteres[i], caracteres[j]] = [caracteres[j], caracteres[i]];
    }

    return caracteres.join('');
}
