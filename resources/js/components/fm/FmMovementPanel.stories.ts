/**
 * The stories of `FmMovementPanel`: the panel of a
 * movement. A new expense, a new income, a payment of a planned item with its
 * due dates, an expense to edit, an income to edit, and the compact one
 * beside a list.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import {
    danceMovement,
    movementOptions,
    salaryMovement,
} from '@/test/fixtures';
import FmMovementPanel from './FmMovementPanel.vue';

const meta = {
    title: 'Panels/FmMovementPanel',
    component: FmMovementPanel,
} satisfies Meta<typeof FmMovementPanel>;

export default meta;

type Story = StoryObj<typeof meta>;

export const NewExpense: Story = {
    name: 'New expense',
    args: {
        movement: null,
        options: movementOptions,
        start: { kind: 'expense', planned_id: null, date: fromToday(0) },
    },
};

export const NewIncome: Story = {
    name: 'New income',
    args: {
        movement: null,
        options: movementOptions,
        start: { kind: 'income', planned_id: null, date: fromToday(0) },
    },
};

export const PayAPlannedItem: Story = {
    name: 'Pay a planned item',
    args: {
        movement: null,
        options: movementOptions,
        start: { kind: 'expense', planned_id: '4', date: fromToday(0) },
        occurrences: [
            { value: '2026-11-05', label: '05/11/2026', help: null },
            { value: '2026-12-05', label: '05/12/2026', help: null },
        ],
    },
};

export const EditAnExpense: Story = {
    name: 'Edit an expense',
    args: {
        movement: danceMovement,
        options: movementOptions,
        start: { kind: 'expense', planned_id: null, date: fromToday(0) },
    },
};

export const EditAnIncome: Story = {
    name: 'Edit an income',
    args: {
        movement: salaryMovement,
        options: movementOptions,
        start: { kind: 'income', planned_id: null, date: fromToday(0) },
    },
};

export const MemberCannotDelete: Story = {
    name: 'Member (cannot delete)',
    args: {
        movement: { ...danceMovement, can_delete: false },
        options: movementOptions,
        start: { kind: 'expense', planned_id: null, date: fromToday(0) },
        compact: true,
    },
};
