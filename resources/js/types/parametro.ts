export type TipoParametro = 'monto' | 'tasa' | 'dias';

export type VigenciaParametro = {
    id: number;
    valor: string;
    valor_mostrado: string;
    vigente_desde: string;
    base_legal: string | null;
    verificar: boolean;
};

export type VigenciaHistorial = VigenciaParametro & {
    anulable: boolean;
};

/** Resumen de una clave (RMV, UIT, tasas...) en la pantalla de parámetros. */
export type ResumenParametro = {
    clave: string;
    etiqueta: string;
    descripcion: string;
    tipo: TipoParametro;
    vigente: VigenciaParametro | null;
    proxima: VigenciaParametro | null;
    historial: VigenciaHistorial[];
};
