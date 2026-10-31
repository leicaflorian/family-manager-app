/**
 * The cases of `FmMovementPanel` for the component catalogue: the panel of a
 * movement. A new expense, a new income, a payment of a planned item with its
 * due dates, an expense to edit, an income to edit, and the compact one
 * beside a list.
 */
import { defineExamples } from '@/catalog/examples';
import { fromToday } from '@/catalog/dates';
import {
    danceMovement,
    movementOptions,
    salaryMovement,
} from '@/test/fixtures';
import FmMovementPanel from './FmMovementPanel.vue';

export default defineExamples(FmMovementPanel, [
    {
        name: 'New expense',
        props: {
            movement: null,
            options: movementOptions,
            start: { kind: 'expense', planned_id: null, date: fromToday(0) },
        },
    },
    {
        name: 'New income',
        props: {
            movement: null,
            options: movementOptions,
            start: { kind: 'income', planned_id: null, date: fromToday(0) },
        },
    },
    {
        name: 'Pay a planned item',
        props: {
            movement: null,
            options: movementOptions,
            start: { kind: 'expense', planned_id: '4', date: fromToday(0) },
            occurrences: [
                { value: '2026-11-05', label: '05/11/2026', help: null },
                { value: '2026-12-05', label: '05/12/2026', help: null },
            ],
        },
    },
    {
        name: 'Edit an expense',
        props: {
            movement: danceMovement,
            options: movementOptions,
            start: { kind: 'expense', planned_id: null, date: fromToday(0) },
        },
    },
    {
        name: 'Edit an income',
        props: {
            movement: salaryMovement,
            options: movementOptions,
            start: { kind: 'income', planned_id: null, date: fromToday(0) },
        },
    },
    {
        name: 'Member (cannot delete)',
        props: {
            movement: { ...danceMovement, can_delete: false },
            options: movementOptions,
            start: { kind: 'expense', planned_id: null, date: fromToday(0) },
            compact: true,
        },
    },
]);
