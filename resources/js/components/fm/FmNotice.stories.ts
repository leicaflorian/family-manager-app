/**
 * The stories of `FmNotice`: the four tones, a title
 * and an action.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import FmNotice from './FmNotice.vue';

const meta = {
    title: 'Basics/FmNotice',
    component: FmNotice,
} satisfies Meta<typeof FmNotice>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Information: Story = {
    args: { tone: 'info' },
    render: withSlots({
        default: 'The files are kept for you, only the family sees them.',
    }),
};

export const Done: Story = {
    args: { tone: 'ok' },
    render: withSlots({
        default: 'We sent the invitation to laura@example.com.',
    }),
};

export const Warning: Story = {
    args: { tone: 'warning' },
    render: withSlots({ default: 'This bill expired 3 days ago.' }),
};

export const Danger: Story = {
    args: { tone: 'danger' },
    render: withSlots({ default: 'The insurance expired 3 days ago.' }),
};

export const WithATitleAndAnAction: Story = {
    name: 'With a title and an action',
    args: { tone: 'warning', title: 'Due in 26 days' },
    render: withSlots({
        default: 'The reminder went out on 07/10.',
        action: '<a href="#">Upload the new one</a>',
    }),
};
