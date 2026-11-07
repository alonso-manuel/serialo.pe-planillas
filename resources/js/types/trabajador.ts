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
    en_planilla: boolean;
};

export type CatalogosTrabajador = {
    empresa: string;
    establecimientos: Opcion[];
    afps: Opcion[];
    tipos_documento: Opcion[];
    tipos_contrato: Opcion[];
    tipos_comision: Opcion[];
    seguro_por_defecto: 'essalud' | 'sis';
    permite_sis: boolean;
    rmv: string | null;
};
