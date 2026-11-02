/**
 * The cases of `FmTitle` for the component catalogue: the title alone, with a
 * sentence, with the link back, and with buttons on the right.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmButton from './FmButton.vue';
import FmTitle from './FmTitle.vue';

export default defineExamples(FmTitle, [
    { name: 'Title only', props: { title: 'Documents' } },
    {
        name: 'With a sentence',
        props: { title: 'Documents', subtitle: 'Everything the family keeps.' },
    },
    {
        name: 'With the link back',
        props: {
            title: 'Bolletta luce ottobre',
            subtitle: 'Edison',
            back: '/areas/home',
            backLabel: 'Home',
        },
    },
    {
        name: 'With something beside',
        props: { title: 'Agenda' },
        slots: {
            beside: () =>
                h(
                    'span',
                    { class: 'rounded-xl bg-[#E6EAF0] px-4 py-2' },
                    'Deadlines · Reminders',
                ),
        },
    },
    {
        name: 'With buttons',
        props: { title: 'Documents' },
        slots: {
            actions: () =>
                h(
                    FmButton,
                    { variant: 'soft', href: '/upload' },
                    () => 'Upload',
                ),
        },
    },
]);
