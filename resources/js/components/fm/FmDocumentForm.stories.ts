/**
 * The stories of `FmDocumentForm`: the form of a new
 * document (with the file it comes from) and the form of an existing one,
 * with a cancel button, and the form with no action, which only tells its values. Saving goes nowhere: the visit is blocked.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { fromToday } from '@/stories/dates';
import { documentValues, newDocument } from '@/lib/documents';
import { documentDetail, documentFormOptions } from '@/test/fixtures';
import FmDocumentForm from './FmDocumentForm.vue';

const meta = {
    title: 'Forms/FmDocumentForm',
    component: FmDocumentForm,
} satisfies Meta<typeof FmDocumentForm>;

export default meta;

type Story = StoryObj<typeof meta>;

export const New: Story = {
    args: {
        action: { url: '/documents', method: 'post' },
        // Both dates are filled: an empty datepicker warns in the console
        initial: {
            ...newDocument('Busta paga novembre 2026'),
            date: fromToday(0),
            expiry_date: fromToday(365),
        },
        extra: { file_id: 12 },
        options: documentFormOptions,
        submitLabel: 'Save the document',
    },
};

export const WithAReading: Story = {
    name: 'With a reading',
    args: {
        action: { url: '/documents', method: 'post' },
        initial: {
            ...newDocument('bolletta-edison.pdf'),
            date: fromToday(0),
            expiry_date: fromToday(365),
        },
        extra: { file_id: 12 },
        options: documentFormOptions,
        submitLabel: 'Save the document',
        proposal: { title: 'Luce ottobre', person_id: '1' },
    },
};

export const WithoutAnAction: Story = {
    name: 'Without an action',
    args: {
        initial: {
            ...newDocument('bolletta-edison.pdf'),
            date: fromToday(0),
            expiry_date: fromToday(365),
        },
        options: documentFormOptions,
        proposal: { title: 'Luce ottobre', person_id: '1' },
        proposalIsStart: true,
    },
};

export const WithErrorsFromThePage: Story = {
    name: 'With errors from the page',
    args: {
        initial: {
            ...newDocument(''),
            date: fromToday(0),
            expiry_date: fromToday(365),
        },
        options: documentFormOptions,
        errors: { title: 'Write what the document is.' },
    },
};

export const Edit: Story = {
    args: {
        action: { url: '/documents/5', method: 'put' },
        initial: {
            ...documentValues(documentDetail),
            expiry_date: fromToday(365),
        },
        options: documentFormOptions,
        submitLabel: 'Save',
        cancelLabel: 'Cancel',
    },
};
