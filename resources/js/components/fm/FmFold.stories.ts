/**
 * The stories of `FmFold`: the closed cards and the
 * planned expenses that ended, folded. Press the button to open the rows.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import FmFold from './FmFold.vue';

const meta = {
    title: 'Basics/FmFold',
    component: FmFold,
} satisfies Meta<typeof FmFold>;

export default meta;

type Story = StoryObj<typeof meta>;

export const ClosedCards: Story = {
    name: 'Closed cards',
    args: { label: 'Closed (2)' },
    render: withSlots({ default: 'Luce - Enel, Gas - Enel' }),
};

export const EndedExpenses: Story = {
    name: 'Ended expenses',
    args: { label: 'Ended (1)' },
    render: withSlots({ default: 'Palestra Giulia' }),
};
