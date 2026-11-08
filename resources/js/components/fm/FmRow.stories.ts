/**
 * The stories of `FmRow`: a link, an overdue
 * deadline, a button, the dot of the area, a long title and a value on the
 * right.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import FmRow from './FmRow.vue';

const meta = {
    title: 'Rows/FmRow',
    component: FmRow,
} satisfies Meta<typeof FmRow>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Link: Story = {
    args: {
        title: 'Bolletta luce ottobre',
        subtitle: 'Scade il 15/11/2026',
        href: '/cards/utility/1',
    },
};

export const Overdue: Story = {
    args: {
        title: 'Bolletta luce ottobre',
        subtitle: 'Scade il 15/11/2026',
        href: '/cards/utility/1',
        tone: 'overdue',
    },
};

export const Button: Story = {
    args: { title: 'Carica un documento', button: true },
};

export const AreaDot: Story = {
    name: 'Area dot',
    args: {
        title: 'Assicurazione auto',
        subtitle: 'Scade il 03/02/2027',
        area: 'vehicles',
    },
};

export const LongTitle: Story = {
    name: 'Long title',
    args: {
        title: 'Contratto di locazione dell’appartamento di via Garibaldi 12, Milano - rinnovo 2026',
        subtitle: 'Scade il 31/12/2028',
    },
};

export const Done: Story = {
    args: { title: 'Carica la prima bolletta', done: true },
};

export const WithAValueOnTheRight: Story = {
    name: 'With a value on the right',
    args: { title: 'Bolletta luce ottobre', subtitle: 'Pagata' },
    render: withSlots({ after: '84,20 €' }),
};
