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

export type EstadoCalculoEmpresa =
    | 'pendiente'
    | 'calculado'
    | 'con_problemas'
    | 'sin_periodo'
    | 'omitido';

/** Estado de un cálculo masivo (todas las empresas), consultado cada pocos segundos. */
export type CalculoMasivoEstado = {
    id: string;
    mes: string;
    total: number;
    pendientes: number;
    terminado: boolean;
    empresas: {
        empresa: string;
        periodo_id: number | null;
        estado: EstadoCalculoEmpresa;
        trabajadores?: number;
        neto?: string;
        problemas?: string[];
        detalle?: string;
    }[];
};

export type GrupoDetalle = {
    titulo: string;
    conceptos: { codigo: string; nombre: string; monto: string }[];
    total: string;
};

/** Props de la página de detalle de un trabajador en el periodo. */
export type DetalleLinea = {
    periodo: PeriodoDetalle & { regimen: string | null };
    trabajador: {
        id: number;
        nombre: string;
        documento: string;
        cargo: string | null;
        contrato: string;
        fecha_ingreso: string;
        fecha_cese: string | null;
        remuneracion_basica: string;
        asignacion_familiar: boolean;
        pension: string;
        comision: string | null;
        cuspp: string | null;
        seguro_salud: string;
    };
    linea: {
        id: number;
        dias_laborados: number;
        dias_no_laborados: number;
        dias_subsidiados: number;
        horas_extra_25: string;
        horas_extra_35: string;
        calculada: boolean;
        desactualizada: boolean;
        calculado_en: string | null;
        total_ingresos: string;
        total_descuentos: string;
        total_aportes_empleador: string;
        neto_pagar: string;
    };
    grupos: Record<
        'ingresos' | 'aportes_trabajador' | 'descuentos' | 'aportes_empleador',
        GrupoDetalle
    >;
    afectas: {
        essalud: string;
        pension: string | null;
        quinta: string;
    } | null;
    anterior: number | null;
    siguiente: number | null;
    posicion: number;
    total: number;
};

/** Cierre del periodo: permisos del usuario, quién cerró y avisos de orden entre meses. */
export type CierrePeriodo = {
    puede_cerrar: boolean;
    puede_reabrir: boolean;
    cerrado_por: string | null;
    cerrado_en: string | null;
    anterior_sin_cerrar: string | null;
    posteriores_cerrados: string[];
};
