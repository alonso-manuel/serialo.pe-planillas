import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

/** Módulo del menú lateral. Sin `href` = aún no disponible (se muestra deshabilitado con "Pronto"). */
export type ModuloNav = {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
};

export type GrupoNav = {
    title: string;
    items: ModuloNav[];
    soloAdmin?: boolean;
};
