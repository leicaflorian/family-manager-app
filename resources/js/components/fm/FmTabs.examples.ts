/**
 * The cases of `FmTabs` for the component catalogue: the three pages of the
 * budget, and the two of the Agenda with the number of reminders.
 */
import { defineExamples } from '@/catalog/examples';
import FmTabs from './FmTabs.vue';

export default defineExamples(FmTabs, [
    {
        name: 'Budget',
        props: {
            label: 'Budget',
            tabs: [
                {
                    label: 'Movements',
                    href: '/budget/movements',
                    current: true,
                },
                {
                    label: 'Planned and spent',
                    href: '/budget/comparison',
                    current: false,
                },
                {
                    label: 'Planned expenses',
                    href: '/budget/planned',
                    current: false,
                },
            ],
        },
    },
    {
        name: 'With a number',
        props: {
            label: 'Agenda',
            tabs: [
                { label: 'Deadlines', href: '/agenda', current: false },
                {
                    label: 'Reminders',
                    href: '/reminders',
                    count: 10,
                    current: true,
                },
            ],
        },
    },
    {
        name: 'Pill',
        props: {
            label: 'Agenda',
            variant: 'pill',
            tabs: [
                { label: 'Deadlines', href: '/agenda', current: false },
                {
                    label: 'Reminders',
                    href: '/reminders',
                    count: 10,
                    current: true,
                },
            ],
        },
    },
    {
        name: 'A tab with nothing in it',
        props: {
            label: 'Files to sort',
            variant: 'pill',
            tabs: [
                { label: 'All', href: '/to-sort', count: 5, current: true },
                {
                    label: 'By mail',
                    href: '/to-sort?source=mail',
                    count: 0,
                    current: false,
                    disabled: true,
                },
                {
                    label: 'From the phone',
                    href: '/to-sort?source=phone',
                    count: 2,
                    current: false,
                },
            ],
        },
    },
]);
