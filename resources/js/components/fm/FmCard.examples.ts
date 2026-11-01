/**
 * The cases of `FmCard` for the component catalogue: with a text, with a
 * title and buttons, and holding a list of rows.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmButton from './FmButton.vue';
import FmCard from './FmCard.vue';
import FmRow from './FmRow.vue';

export default defineExamples(FmCard, [
    {
        name: 'Plain',
        props: {},
        slots: { default: 'Some text inside the card.' },
    },
    {
        name: 'With a title and a button',
        props: { title: 'Payments' },
        slots: {
            default: 'Nothing to pay this month.',
            actions: () => h(FmButton, { variant: 'text' }, () => 'See all'),
        },
    },
    {
        name: 'Overdue title',
        props: { title: 'To sort out', tone: 'overdue' },
        slots: { default: 'Deadlines already passed.' },
    },
    {
        name: 'List of rows',
        props: { title: 'Next deadlines', list: true },
        slots: {
            default: () => [
                h(FmRow, {
                    title: 'Bolletta luce ottobre',
                    subtitle: 'Scade il 15/11/2026',
                    href: '/cards/1',
                }),
                h(FmRow, {
                    title: 'Assicurazione auto',
                    subtitle: 'Scade il 03/02/2027',
                    href: '/cards/2',
                }),
            ],
        },
    },
]);
