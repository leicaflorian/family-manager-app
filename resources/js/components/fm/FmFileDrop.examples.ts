/**
 * The cases of `FmFileDrop` for the component catalogue: the large zone, the
 * compact one below a form, a custom label and the zone while it sends.
 */
import { defineExamples } from '@/catalog/examples';
import FmFileDrop from './FmFileDrop.vue';

export default defineExamples(FmFileDrop, [
    { name: 'Default', props: {} },
    { name: 'Compact', props: { compact: true } },
    { name: 'Custom label', props: { label: 'Add the payslip' } },
    { name: 'Processing', props: { processing: true } },
]);
