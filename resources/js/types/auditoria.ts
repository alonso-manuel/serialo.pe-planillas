export type CambioAuditoria = {
    campo: string;
    antes: string;
    despues: string;
};

export type RegistroAuditoria = {
    id: number;
    fecha: string;
    usuario: string;
    empresa: string | null;
    tipo: string;
    tipo_etiqueta: string;
    descripcion: string | null;
    accion: string;
    accion_etiqueta: string;
    ip: string | null;
    cambios: CambioAuditoria[];
};

export type FiltrosAuditoria = {
    empresa: number | null;
    usuario: number | null;
    tipo: string | null;
    accion: string | null;
    desde: string | null;
    hasta: string | null;
};
