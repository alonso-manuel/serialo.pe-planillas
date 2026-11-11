/** Datos mínimos de una empresa para el selector y la cabecera. */
export type EmpresaResumen = {
    id: number;
    ruc: string;
    razon_social: string;
};

export type RegimenLaboral = 'general' | 'pequena' | 'micro';
export type SeguroSalud = 'essalud' | 'sis';

export type Opcion<T extends string = string> = {
    value: T;
    label: string;
};

/** Fila del listado de empresas. */
export type EmpresaFila = EmpresaResumen & {
    nombre_comercial: string | null;
    regimen_laboral: RegimenLaboral;
    regimen_etiqueta: string;
    seguro_salud: SeguroSalud;
    activo: boolean;
    trabajadores_count: number;
    establecimientos_count: number;
};

/** Empresa que se edita en el formulario. */
export type EmpresaEditable = EmpresaResumen & {
    nombre_comercial: string | null;
    direccion: string | null;
    ubigeo: string | null;
    regimen_laboral: RegimenLaboral;
    seguro_salud: SeguroSalud;
    representante_legal: string | null;
    activo: boolean;
    /** Versión del logo (null si no tiene). */
    logo: string | null;
};

export type EstablecimientoFila = {
    id: number;
    codigo: string;
    nombre: string;
    direccion: string | null;
    es_principal: boolean;
    trabajadores_count: number;
};

/** Respuesta de GET /empresas/consulta-ruc/{ruc}. */
export type DatosRuc = {
    ruc: string;
    razon_social: string;
    direccion: string | null;
    ubigeo: string | null;
    estado: string | null;
    condicion: string | null;
    alerta: string | null;
};

/** Certificado digital registrado para firmar los documentos de la empresa (nunca trae el archivo ni la clave). */
export type FirmaDigitalEmpresa = {
    titular: string;
    documento: string | null;
    emisor: string;
    valido_desde: string;
    valido_hasta: string;
    vigente: boolean;
    dias_para_vencer: number;
    huella: string;
};
