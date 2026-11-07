export type RolUsuario = 'admin' | 'asistente';

export type OpcionRol = {
    value: RolUsuario;
    label: string;
};

/** Fila del listado de usuarios. */
export type UsuarioFila = {
    id: number;
    name: string;
    email: string;
    rol: RolUsuario;
    rol_etiqueta: string;
    activo: boolean;
    empresas_count: number;
    es_actual: boolean;
};

/** Usuario que se edita en el formulario. */
export type UsuarioEditable = {
    id: number;
    name: string;
    email: string;
    rol: RolUsuario;
    activo: boolean;
    empresas: number[];
    es_actual: boolean;
};

/** Paginador de Laravel (`->paginate()`), tal como llega por Inertia. */
export type Paginado<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
