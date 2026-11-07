export type TipoConcepto =
    | 'ingreso'
    | 'descuento'
    | 'aporte_trab'
    | 'aporte_empl';

export type ConceptoFila = {
    id: number;
    codigo_plame: string;
    nombre: string;
    tipo: TipoConcepto;
    afecto_essalud: boolean;
    afecto_onp: boolean;
    afecto_afp: boolean;
    afecto_quinta: boolean;
    sistema: boolean;
    activo: boolean;
    usado: boolean;
};

export type GrupoConceptos = {
    tipo: TipoConcepto;
    etiqueta: string;
    conceptos: ConceptoFila[];
};
