/**
 * The stories of `FmNoticeList`: new and seen
 * notices, only seen ones and no notice at all.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { seenNotices, unreadNotices } from '@/test/fixtures';
import FmNoticeList from './FmNoticeList.vue';

const meta = {
    title: 'Page sections/FmNoticeList',
    component: FmNoticeList,
} satisfies Meta<typeof FmNoticeList>;

export default meta;

type Story = StoryObj<typeof meta>;

export const NewAndSeen: Story = {
    name: 'New and seen',
    args: { unread: unreadNotices, seen: seenNotices },
};

export const OnlySeen: Story = {
    name: 'Only seen',
    args: { unread: [], seen: seenNotices },
};

export const None: Story = {
    args: { unread: [], seen: [] },
};
