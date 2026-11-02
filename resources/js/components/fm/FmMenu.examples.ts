/**
 * The cases of `FmMenu` for the component catalogue: the ⋯ button with a few
 * entries, the plain one with an entry that deletes, and the outlined one
 * with its label in sight, as *Postpone* of a late reminder.
 */
import {
    CalendarDays,
    Clock,
    Download,
    ExternalLink,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

export default defineExamples(FmMenu, [
    {
        name: 'Few entries',
        props: {},
        slots: {
            default: () => [
                h(
                    FmMenuItem,
                    { icon: Pencil, href: '/documents/5/edit' },
                    () => 'Edit',
                ),
                h(
                    FmMenuItem,
                    {
                        icon: Download,
                        href: '/documents/5/file',
                        download: true,
                    },
                    () => 'Download',
                ),
            ],
        },
    },
    {
        name: 'Plain, with a delete',
        props: { plain: true, label: 'Actions of the document' },
        slots: {
            default: () => [
                h(
                    FmMenuItem,
                    { icon: Pencil, href: '/documents/5/edit' },
                    () => 'Edit',
                ),
                h(
                    FmMenuItem,
                    {
                        icon: ExternalLink,
                        href: '/documents/5/file',
                        external: true,
                    },
                    () => 'Open in a new tab',
                ),
                h(FmMenuItem, { icon: Trash2, danger: true }, () => 'Delete'),
            ],
        },
    },
    {
        name: 'Outlined, with its label',
        props: { outline: true, label: 'Rimanda', icon: Clock },
        slots: {
            default: () => [
                h(FmMenuItem, null, () => 'Domani'),
                h(FmMenuItem, null, () => 'Tra una settimana'),
                h(FmMenuItem, { icon: CalendarDays }, () => 'Scegli una data…'),
            ],
        },
    },
]);
