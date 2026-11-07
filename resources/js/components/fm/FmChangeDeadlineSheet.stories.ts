/**
 * The stories of `FmChangeDeadlineSheet`: the panel
 * that changes one due date, opened with the `open` control. It
 * starts from a changed one, a skipped one and a plain one.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { danceDeadline, lateDeadline } from '@/test/fixtures';
import FmChangeDeadlineSheet from './FmChangeDeadlineSheet.vue';

const meta = {
    title: 'Panels/FmChangeDeadlineSheet',
    component: FmChangeDeadlineSheet,
} satisfies Meta<typeof FmChangeDeadlineSheet>;

export default meta;

type Story = StoryObj<typeof meta>;

export const ChangedAmountAndNote: Story = {
    name: 'Changed amount and note',
    args: {
        open: false,
        deadline: danceDeadline,
        action: '/budget/planned/expense/4/occurrences/2026-12-05',
    },
};

export const Skipped: Story = {
    args: {
        open: false,
        deadline: { ...danceDeadline, skipped: true },
        action: '/budget/planned/expense/4/occurrences/2026-12-05',
    },
};

export const EstimatedAndLate: Story = {
    name: 'Estimated and late',
    args: {
        open: false,
        deadline: lateDeadline,
        action: '/budget/planned/expense/7/occurrences/2026-10-23',
    },
};
