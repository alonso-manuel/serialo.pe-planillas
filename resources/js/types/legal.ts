/** Datos de la instalación para los términos y la política de privacidad. */
export type DatosLegales = {
    version: string;
    /** Empresa o estudio responsable de los datos (null = texto genérico). */
    responsable: string | null;
    /** Correo para derechos ARCO (null = solicitud por escrito al responsable). */
    correo: string | null;
    saas: boolean;
};

export type EstadoFirma =
    | 'vigente'
    | 'por_vencer'
    | 'vencida'
    | 'sin_certificado';

/** Administración → Seguridad y cumplimiento. */
export type ResumenSeguridad = {
    cierres: { cerrados: number; ultimo: string | null; reaperturas: number };
    auditoria: { total: number; ultimos_30: number };
    firmas: {
        empresa: string;
        estado: EstadoFirma;
        titular: string | null;
        vence: string | null;
    }[];
    respaldos: {
        cantidad: number;
        ultimo: string | null;
        dias: number;
        hora: string;
        externo: boolean;
        saas: boolean;
    };
    accesos: { activos: number; sin_doble_factor: string[] };
};
