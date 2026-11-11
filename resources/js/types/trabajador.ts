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
    centro_costo_id: number | null;
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
    entidad_cts: string | null;
    entidad_sueldo: string | null;
    categoria_ocupacional: string | null;
    ocupacion: string | null;
    ocupacion_nombre: string | null;
    situacion_educativa: string | null;
    modalidad_contrato: string | null;
    situacion_especial: string;
    discapacidad: boolean;
    sindicalizado: boolean;
    nacionalidad: string | null;
    pais_emisor: string | null;
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
    centros_costo: Opcion[];
    afps: Opcion[];
    tipos_documento: Opcion[];
    tipos_contrato: Opcion[];
    categorias_ocupacionales: Opcion[];
    situaciones_educativas: Opcion[];
    modalidades_contrato: Opcion[];
    situaciones_especiales: Opcion[];
    vinculos: (Opcion & { documentos: Opcion[]; exige_numero: boolean })[];
    motivos_baja_derechohabiente: Opcion[];
    tipos_comision: Opcion[];
    motivos_cese: Opcion[];
    entidades_cts: Opcion[];
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

/** Certificado de rentas y retenciones de otro empleador del año (quinta categoría). */
export type RentaOtroEmpleador = {
    id: number;
    anio: number;
    ruc_empleador: string | null;
    empleador: string | null;
    renta: string;
    retencion: string;
};

/** Derechohabiente del trabajador en la ficha. */
export type DerechohabienteFila = {
    id: number;
    nombre: string;
    documento: string;
    vinculo: string;
    fecha_nacimiento: string;
    fecha_alta: string;
    fecha_alta_iso: string;
    fecha_baja: string | null;
    motivo_baja: string | null;
};

/** Préstamo del trabajador con su cronograma y saldo. */
export type PrestamoEstado = {
    id: number;
    fecha: string;
    monto: string;
    glosa: string | null;
    saldo: string;
    eliminable: boolean;
    cuotas: {
        numero: number;
        mes: string;
        monto: string;
        descontada: boolean;
    }[];
};
