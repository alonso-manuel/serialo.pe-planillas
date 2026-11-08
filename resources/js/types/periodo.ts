export type EstadoPeriodo = 'abierto' | 'calculado' | 'cerrado';

export type PeriodoFila = {
    id: number;
    anio: number;
    mes: number;
    etiqueta: string;
    estado: EstadoPeriodo;
    estado_etiqueta: string;
    trabajadores: number;
    neto: string | null;
    calculado_en: string | null;
};

export type PeriodoDetalle = {
    id: number;
    etiqueta: string;
    estado: EstadoPeriodo;
    estado_etiqueta: string;
    puede_editar: boolean;
    empresa: string | null;
};

export type LineaPeriodo = {
    id: number;
    trabajador_id: number;
    nombre: string;
    documento: string;
    cargo: string | null;
    dias_laborados: number;
    ingresa_en_mes: string | null;
    cesa_en_mes: string | null;
    neto_pagar: string | null;
    dias_mes: number;
    dias_no_laborados: number;
    dias_subsidiados: number;
    horas_extra_25: string;
    horas_extra_35: string;
    /** concepto_id => monto */
    novedades: Record<string, string>;
};

/** Concepto que se puede ingresar como novedad en la grilla. */
export type ConceptoNovedad = {
    id: number;
    codigo_plame: string;
    nombre: string;
    tipo: 'ingreso' | 'descuento';
};
