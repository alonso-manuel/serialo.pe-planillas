import type { Opcion } from './empresa';

export type TrabajadorFila = {
    id: number;
    documento: string;
    apellido_paterno: string;
    nombre_completo: string;
    cargo: string | null;
    establecimiento: string | null;
    fecha_ingreso: string;
    remuneracion_basica: string;
    pension: string;
    cesado: boolean;
    fecha_cese: string | null;
};

/** Ficha editable (fechas en AAAA-MM-DD; montos como texto). */
export type TrabajadorEditable = {
    id: number;
    tipo_documento: string;
    numero_documento: string;
    apellido_paterno: string;
    apellido_materno: string | null;
    nombres: string;
    fecha_nacimiento: string | null;
    sexo: 'M' | 'F' | null;
    email: string | null;
    telefono: string | null;
    establecimiento_id: number | null;
    cargo: string | null;
    fecha_ingreso: string;
    tipo_contrato: string;
    remuneracion_basica: string;
    asignacion_familiar: boolean;
    sistema_pension: 'afp' | 'onp' | 'ninguno';
    afp_id: number | null;
    cuspp: string | null;
    tipo_comision_afp: 'flujo' | 'mixta' | null;
    seguro_salud: 'essalud' | 'sis';
    cuenta_sueldo: string | null;
    cuenta_cts: string | null;
    nombre_completo: string;
    cesado: boolean;
    cese: {
        fecha: string;
        fecha_iso: string;
        motivo: string | null;
        motivo_etiqueta: string | null;
    } | null;
    en_planilla: boolean;
};

export type CatalogosTrabajador = {
    empresa: string;
    establecimientos: Opcion[];
    afps: Opcion[];
    tipos_documento: Opcion[];
    tipos_contrato: Opcion[];
    tipos_comision: Opcion[];
    motivos_cese: Opcion[];
    seguro_por_defecto: 'essalud' | 'sis';
    permite_sis: boolean;
    rmv: string | null;
    consulta_dni: boolean;
};

/** Un cambio del historial (grupo + mes), con sus campos antes → después. */
export type CambioHistorial = {
    id: number;
    grupo: string;
    grupo_etiqueta: string;
    desde: string;
    orden: string;
    usuario: string;
    registrado: string | null;
    cambios: { campo: string; antes: string; despues: string }[];
    anulable: boolean;
};

export type FilaImportadaTrabajador = {
    linea: number;
    documento: string;
    nombre: string;
    cargo: string | null;
    remuneracion_basica: string;
    pension: string;
    errores: string[];
    avisos: string[];
};

export type ImportacionTrabajadores = {
    archivo: string;
    validas: number;
    con_errores: number;
    filas: FilaImportadaTrabajador[];
};
