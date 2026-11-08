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
    novedades: number;
    neto_pagar: string | null;
};
