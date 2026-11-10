/**
 * Rutas del panel central (modo SaaS). Son relativas a propósito: el panel siempre se usa en su propio
 * dominio (admin.serialo.pe en producción, admin.localhost en local) y Wayfinder fijaría el dominio
 * con el que se compilaron los assets.
 */
export const rutasPanel = {
    ingresar: '/ingresar',
    verificacion: '/verificacion',
    salir: '/salir',
    dobleFactor: '/seguridad/doble-factor',
    cuentas: '/cuentas',
    nuevaCuenta: '/cuentas/nueva',
    estado: (id: string) => `/cuentas/${encodeURIComponent(id)}/estado`,
    acceso: (id: string) => `/cuentas/${encodeURIComponent(id)}/acceso`,
    cuenta: (id: string) => `/cuentas/${encodeURIComponent(id)}`,
    actividad: '/actividad',
} as const;
