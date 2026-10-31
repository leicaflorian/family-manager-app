/**
 * The cases of `FmButton` for the component catalogue: the five variants, with
 * an icon, as a link, disabled, and only the icon.
 */
import { Plus, Trash2, Upload } from '@lucide/vue';
import { defineExamples } from '@/catalog/examples';
import FmButton from './FmButton.vue';

export default defineExamples(FmButton, [
    { name: 'Solid', props: { variant: 'solid' }, slots: { default: 'Save' } },
    {
        name: 'Outline',
        props: { variant: 'outline' },
        slots: { default: 'Cancel' },
    },
    { name: 'Soft', props: { variant: 'soft' }, slots: { default: 'Add' } },
    { name: 'Text', props: { variant: 'text' }, slots: { default: 'Skip' } },
    {
        name: 'Danger',
        props: { variant: 'danger', icon: Trash2 },
        slots: { default: 'Delete' },
    },
    {
        name: 'With an icon, as a link',
        props: { icon: Upload, href: '/upload' },
        slots: { default: 'Upload a document' },
    },
    {
        name: 'Disabled',
        props: { disabled: true },
        slots: { default: 'Save' },
    },
    {
        name: 'Only the icon',
        props: { variant: 'soft', icon: Plus, label: 'Add a person' },
    },
]);
