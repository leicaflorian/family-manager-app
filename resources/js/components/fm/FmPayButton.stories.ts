/**
 * The stories of `FmPayButton`: one open due date,
 * several planned items (it asks which one), an income and none (no button).
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import { danceDeadline, lateDeadline, salaryDeadline } from '@/test/fixtures';
import FmPayButton from './FmPayButton.vue';

const meta = {
    title: 'Basics/FmPayButton',
    component: FmPayButton,
} satisfies Meta<typeof FmPayButton>;

export default meta;

type Story = StoryObj<typeof meta>;

export const OneDueDate: Story = {
    name: 'One due date',
    args: {
        deadlines: [{ ...lateDeadline, date: fromToday(-3) }],
        label: 'Record payment',
    },
};

export const SeveralPlannedItems: Story = {
    name: 'Several planned items',
    args: {
        deadlines: [{ ...lateDeadline, date: fromToday(-3) }, danceDeadline],
        label: 'Record payment',
    },
};

export const Income: Story = {
    args: { deadlines: [salaryDeadline], label: 'Record income' },
};

export const NoOpenDueDate: Story = {
    name: 'No open due date',
    args: { deadlines: [], label: 'Record payment' },
};
