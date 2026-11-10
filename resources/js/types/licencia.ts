export type TipoEstadoLicencia =
    | 'valida'
    | 'prueba'
    | 'sin_licencia'
    | 'invalida'
    | 'otro_dominio'
    | 'omitida';

/** Prop compartida `avisoLicencia`: solo llega cuando hay algo que avisar (solo lectura o prueba). */
export type AvisoLicencia = {
    tipo: TipoEstadoLicencia;
    solo_lectura: boolean;
    mensaje: string;
    dias_prueba: number | null;
};

export type EstadoLicencia = {
    tipo: TipoEstadoLicencia;
    etiqueta: string;
    mensaje: string;
    solo_lectura: boolean;
    dias_prueba: number | null;
};

export type DatosLicencia = {
    cliente: string;
    plan: string;
    dominio: string;
    emitida: string;
    actualizaciones_hasta: string;
    con_actualizaciones: boolean;
};

export type UsoLicencia = {
    recurso: 'empresas' | 'usuarios' | 'trabajadores';
    etiqueta: string;
    usados: number;
    limite: number | null;
};
