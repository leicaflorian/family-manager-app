/**
 * The stories of `FmBell`: nothing new, a few
 * notices, a lot of them and the menu still loading.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { seenNotices, unreadNotices } from '@/test/fixtures';
import FmBell from './FmBell.vue';

const meta = {
    title: 'Navigation/FmBell',
    component: FmBell,
} satisfies Meta<typeof FmBell>;

export default meta;

type Story = StoryObj<typeof meta>;

export const NothingNew: Story = {
    name: 'Nothing new',
    args: { count: 0, notices: { unread: [], seen: seenNotices } },
};

export const TwoNew: Story = {
    name: 'Two new',
    args: {
        count: 2,
        notices: { unread: unreadNotices, seen: seenNotices },
    },
};

export const Many: Story = {
    args: {
        count: 120,
        notices: { unread: unreadNotices, seen: seenNotices },
    },
};

export const Loading: Story = {
    args: { count: 2 },
};
