/**
 * The stories of `FmCardHeader`: the coloured top
 * of a card page in two areas, with the buttons on the right and with a long
 * name.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { h } from 'vue';
import { pandaRow, policyDetail } from '@/test/fixtures';
import FmButton from './FmButton.vue';
import FmCardHeader from './FmCardHeader.vue';

const meta = {
    title: 'Page sections/FmCardHeader',
    component: FmCardHeader,
} satisfies Meta<typeof FmCardHeader>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Vehicle: Story = {
    args: { card: pandaRow, heading: 'Plate GH 482 TK · Marco' },
};

export const InsuranceWithActions: Story = {
    name: 'Insurance with actions',
    args: { card: policyDetail.card, heading: policyDetail.heading },
    render: withSlots({
        actions: () => h(FmButton, { variant: 'text' }, () => 'Edit'),
    }),
};

export const LongName: Story = {
    name: 'Long name',
    args: {
        card: {
            ...policyDetail.card,
            label: 'Polizza assicurativa RC auto e furto incendio - Fiat Panda Cross 4x4 - UnipolSai Assicurazioni',
        },
        heading: 'Car · policyholder Marco Bianchi · expires 20/11/2026',
    },
};
