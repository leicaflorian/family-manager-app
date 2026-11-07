/**
 * The stories of `FmTabs`: the three pages of the
 * budget, and the two of the Agenda with the number of reminders.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmTabs from './FmTabs.vue';

const meta = {
    title: 'Navigation/FmTabs',
    component: FmTabs,
} satisfies Meta<typeof FmTabs>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Budget: Story = {
    args: {
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
};

export const WithANumber: Story = {
    name: 'With a number',
    args: {
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
};

export const Pill: Story = {
    args: {
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
};

export const ATabWithNothingInIt: Story = {
    name: 'A tab with nothing in it',
    args: {
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
};
