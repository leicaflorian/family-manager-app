/**
 * The stories of `FmCardRow`: a card with a date
 * ahead, an overdue one, one with only a subtitle and a closed one.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import { pandaRow } from '@/test/fixtures';
import FmCardRow from './FmCardRow.vue';

const meta = {
    title: 'Rows/FmCardRow',
    component: FmCardRow,
} satisfies Meta<typeof FmCardRow>;

export default meta;

type Story = StoryObj<typeof meta>;

export const DateAhead: Story = {
    name: 'Date ahead',
    args: { card: { ...pandaRow, next_date: fromToday(75) } },
};

export const Overdue: Story = {
    args: {
        card: {
            ...pandaRow,
            label: 'TV Samsung',
            area: 'home',
            type: 'belonging',
            next_label: 'Warranty end',
            next_date: fromToday(-5),
            overdue: true,
        },
    },
};

export const SubtitleOnly: Story = {
    name: 'Subtitle only',
    args: {
        card: {
            ...pandaRow,
            label: 'Casa di via Roma',
            area: 'home',
            type: 'home',
            subtitle: 'Via Roma 12, Bologna',
            next_label: null,
            next_date: null,
        },
    },
};

export const Closed: Story = {
    args: {
        card: {
            ...pandaRow,
            label: 'Luce - Enel',
            area: 'home',
            type: 'utility',
            subtitle: 'Enel',
            next_label: null,
            next_date: null,
            closed: true,
        },
    },
};
