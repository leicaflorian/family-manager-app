/**
 * The stories of `FmSectionLink`: a link, a link
 * with an icon and a button without an address.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { Plus } from '@lucide/vue';
import FmSectionLink from './FmSectionLink.vue';

const meta = {
    title: 'Navigation/FmSectionLink',
    component: FmSectionLink,
} satisfies Meta<typeof FmSectionLink>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Link: Story = {
    args: { href: '/budget/planned' },
    render: withSlots({ default: 'See all the bills, year by year' }),
};

export const WithAnIcon: Story = {
    name: 'With an icon',
    args: { href: '/budget/planned/create', icon: Plus },
    render: withSlots({ default: 'Add a planned expense' }),
};

export const Button: Story = {
    render: withSlots({ default: 'Show more' }),
};

/** Under the pointer: darker, underlined. */
export const Hover: Story = {
    args: { href: '/budget/planned', icon: Plus },
    parameters: { pseudo: { hover: true } },
    render: withSlots({ default: 'Add a planned expense' }),
};

/** The ring of the keyboard focus. */
export const Focus: Story = {
    args: { href: '/budget/planned', icon: Plus },
    parameters: { pseudo: { focusVisible: true } },
    render: withSlots({ default: 'Add a planned expense' }),
};
