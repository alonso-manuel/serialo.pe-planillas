import type { EstadoPeriodo } from './periodo';

/** Archivo de una exportación con su huella SHA-256. */
export type ArchivoExportado = {
    nombre: string;
    sha256: string;
    bytes: number;
};

export type ExportacionRealizada = {
    id: number;
    fecha: string;
    usuario: string | null;
    archivos: ArchivoExportado[];
};

/** Props de las pantallas de archivos oficiales (PLAME, AFPnet). */
export type DatosExportacion = {
    anio: number;
    mes: number;
    etiqueta: string;
    empresas: {
        id: number;
        razon_social: string;
        ruc: string;
        periodo: {
            id: number;
            estado: EstadoPeriodo;
            estado_etiqueta: string;
            trabajadores: number;
        } | null;
        listo: boolean;
        ultima: ExportacionRealizada | null;
    }[];
    historial: (ExportacionRealizada & {
        empresa: string | null;
        mes: string;
    })[];
};
