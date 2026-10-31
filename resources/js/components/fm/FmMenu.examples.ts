/**
 * The cases of `FmMenu` for the component catalogue: the ⋯ button with a few
 * entries, and the plain one with an entry that deletes.
 */
import { Download, Pencil, Trash2 } from '@lucide/vue';
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
                h(FmMenuItem, { icon: Trash2, danger: true }, () => 'Delete'),
            ],
        },
    },
]);
