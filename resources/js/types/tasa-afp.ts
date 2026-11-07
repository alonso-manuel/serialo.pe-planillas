/** Estado de un mes en el resumen anual de tasas AFP. */
export type MesTasasAfp = {
    mes: number;
    cargadas: number;
    estado: 'completo' | 'incompleto' | 'vacio';
    bloqueado: boolean;
};

/** Fila de la grilla: tasas en porcentaje ("1.37") y RMA en soles; null si no hay dato. */
export type FilaTasaAfp = {
    afp_id: number;
    codigo: string;
    nombre: string;
    aporte_obligatorio: string | null;
    prima_seguro: string | null;
    comision_flujo: string | null;
    comision_mixta: string | null;
    remuneracion_maxima_asegurable: string | null;
};

export type FilaImportadaTasaAfp = {
    linea: number;
    anio: number | null;
    mes: number | null;
    afp: string;
    aporte_obligatorio: string;
    prima_seguro: string;
    comision_flujo: string;
    comision_mixta: string;
    remuneracion_maxima_asegurable: string;
    errores: string[];
    avisos: string[];
};

export type ImportacionTasasAfp = {
    archivo: string;
    validas: number;
    con_errores: number;
    filas: FilaImportadaTasaAfp[];
};
