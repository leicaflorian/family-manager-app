/**
 * The stories of `FmCategoryBar`: spent as planned,
 * an estimate, a late payment, over the plan and nothing planned.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmCategoryBar from './FmCategoryBar.vue';

const meta = {
    title: 'Figures/FmCategoryBar',
    component: FmCategoryBar,
} satisfies Meta<typeof FmCategoryBar>;

export default meta;

type Story = StoryObj<typeof meta>;

export const AsPlanned: Story = {
    name: 'As planned',
    args: {
        category: 'Casa e affitto',
        spent: 612,
        planned: 612,
        color: '#2563eb',
    },
};

export const Estimated: Story = {
    args: {
        category: 'Spesa',
        spent: 150.1,
        planned: 320,
        estimated: true,
        color: '#16a34a',
    },
};

export const Late: Story = {
    args: {
        category: 'Bollette e utenze',
        spent: 29.9,
        planned: 223.3,
        lateTitle: 'Luce',
        color: '#e35169',
    },
};

export const OverThePlan: Story = {
    name: 'Over the plan',
    args: { category: 'Svago', spent: 180, planned: 120 },
};

export const NothingPlanned: Story = {
    name: 'Nothing planned',
    args: {
        category: 'Salute',
        spent: 108.4,
        planned: 0,
        color: '#d9467a',
    },
};
