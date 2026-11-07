import {
    Building,
    CalendarDays,
    ChartColumn,
    FileText,
    FileUp,
    Gift,
    Landmark,
    LayoutGrid,
    ListChecks,
    Percent,
    PiggyBank,
    ScrollText,
    SlidersHorizontal,
    TreePalm,
    UserCog,
    Users,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import type { GrupoNav } from '@/types';

/**
 * Menú lateral del sistema. Un módulo sin `href` todavía no está construido:
 * se muestra deshabilitado con la etiqueta "Pronto". Al terminar un módulo,
 * agrega aquí su ruta de Wayfinder (p. ej. `href: index()` de `@/routes/trabajadores`).
 */
export const gruposNav: GrupoNav[] = [
    {
        title: 'Inicio',
        items: [{ title: 'Panel', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Personal',
        items: [{ title: 'Trabajadores', icon: Users }],
    },
    {
        title: 'Planilla',
        items: [
            { title: 'Periodos', icon: CalendarDays },
            { title: 'Boletas', icon: FileText },
        ],
    },
    {
        title: 'Beneficios',
        items: [
            { title: 'Gratificaciones', icon: Gift },
            { title: 'CTS', icon: PiggyBank },
            { title: 'Vacaciones', icon: TreePalm },
        ],
    },
    {
        title: 'Archivos oficiales',
        items: [
            { title: 'PLAME', icon: FileUp },
            { title: 'AFPnet', icon: Landmark },
        ],
    },
    {
        title: 'Reportes',
        items: [{ title: 'Reportes', icon: ChartColumn }],
    },
    {
        title: 'Administración',
        soloAdmin: true,
        items: [
            { title: 'Empresas', icon: Building },
            { title: 'Usuarios', icon: UserCog },
            { title: 'Parámetros', icon: SlidersHorizontal },
            { title: 'Tasas AFP', icon: Percent },
            { title: 'Conceptos', icon: ListChecks },
            { title: 'Auditoría', icon: ScrollText },
        ],
    },
];
