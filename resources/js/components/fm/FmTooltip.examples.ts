/**
 * The cases of `FmTooltip` for the component catalogue: under a button, and
 * beside it. Hover the button, or reach it with Tab, to see the text.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmButton from './FmButton.vue';
import FmTooltip from './FmTooltip.vue';

/** The button the text is about. */
const button = (): ReturnType<typeof h> => h(FmButton, null, () => 'Upload');

export default defineExamples(FmTooltip, [
    {
        name: 'Under',
        props: { text: 'Upload a document from the computer' },
        slots: { default: button },
    },
    {
        name: 'Beside',
        props: { text: 'Upload a document from the computer', side: 'right' },
        slots: { default: button },
    },
]);
