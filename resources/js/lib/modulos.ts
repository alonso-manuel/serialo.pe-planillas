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
import { index as auditoria } from '@/routes/auditoria';
import { index as conceptos } from '@/routes/conceptos';
import { index as empresas } from '@/routes/empresas';
import { index as parametros } from '@/routes/parametros';
import { index as tasasAfp } from '@/routes/tasas-afp';
import { index as trabajadores } from '@/routes/trabajadores';
import { index as usuarios } from '@/routes/usuarios';
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
        items: [{ title: 'Trabajadores', href: trabajadores(), icon: Users }],
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
            { title: 'Empresas', href: empresas(), icon: Building },
            { title: 'Usuarios', href: usuarios(), icon: UserCog },
            {
                title: 'Parámetros',
                href: parametros(),
                icon: SlidersHorizontal,
            },
            { title: 'Tasas AFP', href: tasasAfp(), icon: Percent },
            { title: 'Conceptos', href: conceptos(), icon: ListChecks },
            { title: 'Auditoría', href: auditoria(), icon: ScrollText },
        ],
    },
];
