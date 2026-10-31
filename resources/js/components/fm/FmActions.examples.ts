/**
 * The cases of `FmActions` for the component catalogue: one action, four (the
 * last goes in the menu), and with a menu-only action that deletes.
 */
import { Download, Pencil, Plus, Trash2, Upload } from '@lucide/vue';
import { defineExamples } from '@/catalog/examples';
import FmActions from './FmActions.vue';

export default defineExamples(FmActions, [
    {
        name: 'One action',
        props: {
            actions: [{ label: 'Upload', icon: Upload, href: '/upload' }],
        },
    },
    {
        name: 'Four actions',
        props: {
            actions: [
                { label: 'Edit', icon: Pencil, href: '/documents/5/edit' },
                { label: 'Add a person', icon: Plus, href: '/people/create' },
                { label: 'Upload', icon: Upload, href: '/upload' },
                {
                    label: 'Download',
                    icon: Download,
                    href: '/documents/5/file',
                    download: true,
                },
            ],
        },
    },
    {
        name: 'With a delete in the menu',
        props: {
            actions: [
                { label: 'Edit', icon: Pencil, href: '/documents/5/edit' },
                {
                    label: 'Delete',
                    icon: Trash2,
                    variant: 'danger',
                    menuOnly: true,
                    confirm: {
                        url: '/documents/5',
                        question: 'Delete this document?',
                        text: 'The file goes too.',
                        confirmLabel: 'Delete the document',
                    },
                },
            ],
        },
    },
]);
