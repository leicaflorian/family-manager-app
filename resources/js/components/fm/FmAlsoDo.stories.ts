/**
 * The stories of `FmAlsoDo`: the box with two
 * items, and with another title.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { HeartPulse, Wallet } from '@lucide/vue';
import { h } from 'vue';
import FmAlsoDo from './FmAlsoDo.vue';
import FmAlsoDoItem from './FmAlsoDoItem.vue';

const items = () => [
    h(FmAlsoDoItem, {
        label: 'Create the expense of 84,20 €',
        hint: 'Already paid on 22/10 · Laura’s card',
        icon: Wallet,
        modelValue: true,
    }),
    h(FmAlsoDoItem, {
        label: 'Add a health event',
        icon: HeartPulse,
        modelValue: false,
    }),
];

const meta = {
    title: 'Page sections/FmAlsoDo',
    component: FmAlsoDo,
} satisfies Meta<typeof FmAlsoDo>;

export default meta;

type Story = StoryObj<typeof meta>;

export const TwoItems: Story = {
    name: 'Two items',
    render: withSlots({ default: items }),
};

export const AnotherTitle: Story = {
    name: 'Another title',
    args: { title: 'And also' },
    render: withSlots({ default: items }),
};
