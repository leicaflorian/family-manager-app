/**
 * The cases of `FmHeader` for the component catalogue: the title alone and
 * with the sentence below.
 */
import { defineExamples } from '@/catalog/examples';
import FmHeader from './FmHeader.vue';

export default defineExamples(FmHeader, [
    { name: 'Title only', props: { title: 'Hi, Rosa' } },
    {
        name: 'With a sentence',
        props: {
            title: 'Hi, Rosa',
            subtitle: 'Marco invited you to the Bianchi family.',
        },
    },
]);
