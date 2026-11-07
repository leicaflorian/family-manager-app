/**
 * The stories of `FmNoticeRow`: a new notice, one
 * seen whose reminder is late and one seen whose reminder is done.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import type { NoticeItem } from '@/lib/reminders';
import FmNoticeRow from './FmNoticeRow.vue';

/** A notice not yet seen. */
const notice: NoticeItem = {
    id: 1,
    title: 'Rinnovare la polizza · RC auto Panda - UnipolSai',
    due_text: 'Scade il 21/11, tra 26 giorni',
    sent_at: '2026-10-07T06:00:00+02:00',
    read: false,
    area: 'insurance',
    state: null,
    href: '/cards/insurance/2',
};

const meta = {
    title: 'Rows/FmNoticeRow',
    component: FmNoticeRow,
} satisfies Meta<typeof FmNoticeRow>;

export default meta;

type Story = StoryObj<typeof meta>;

export const New: Story = {
    args: { notice, readHref: '/notices/1/read' },
};

export const SeenOverdue: Story = {
    name: 'Seen, overdue',
    args: {
        notice: {
            ...notice,
            title: 'Rinnovare la tessera sanitaria di Giulia',
            due_text: 'Scadeva il 30/09: domani',
            read: true,
            area: 'health',
            state: 'overdue',
        },
    },
};

export const SeenDone: Story = {
    name: 'Seen, done',
    args: {
        notice: { ...notice, read: true, area: 'paperwork', state: 'done' },
    },
};
