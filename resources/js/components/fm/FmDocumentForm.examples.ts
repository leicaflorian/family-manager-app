/**
 * The cases of `FmDocumentForm` for the component catalogue: the form of a new
 * document (with the file it comes from) and the form of an existing one,
 * with a cancel button. Saving goes nowhere: the visit is blocked.
 */
import { fromToday } from '@/catalog/dates';
import { defineExamples } from '@/catalog/examples';
import { documentValues, newDocument } from '@/lib/documents';
import { documentDetail, documentFormOptions } from '@/test/fixtures';
import FmDocumentForm from './FmDocumentForm.vue';

export default defineExamples(FmDocumentForm, [
    {
        name: 'New',
        props: {
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
    },
    {
        name: 'Edit',
        props: {
            action: { url: '/documents/5', method: 'put' },
            initial: {
                ...documentValues(documentDetail),
                expiry_date: fromToday(365),
            },
            options: documentFormOptions,
            submitLabel: 'Save',
            cancelLabel: 'Cancel',
        },
    },
]);
