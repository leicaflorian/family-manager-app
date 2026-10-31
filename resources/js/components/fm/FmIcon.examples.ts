/**
 * The cases of `FmIcon` for the component catalogue: the default size, a big
 * thin one and one with a label for screen readers.
 */
import { FileText, Home } from '@lucide/vue';
import { defineExamples } from '@/catalog/examples';
import FmIcon from './FmIcon.vue';

export default defineExamples(FmIcon, [
    { name: 'Default', props: { icon: FileText } },
    { name: 'Big and thin', props: { icon: Home, size: 48, stroke: 1 } },
    { name: 'With a label', props: { icon: FileText, label: 'Document' } },
]);
