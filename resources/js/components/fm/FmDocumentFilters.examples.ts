/**
 * The cases of `FmDocumentFilters` for the component catalogue: no filter,
 * some filters in use and only the expiring documents.
 */
import { defineExamples } from '@/catalog/examples';
import { documentFilterOptions } from '@/test/fixtures';
import FmDocumentFilters from './FmDocumentFilters.vue';

const none = {
    category: null,
    person: null,
    organization: null,
    status: null,
    expiring: false,
    search: null,
};

export default defineExamples(FmDocumentFilters, [
    {
        name: 'No filter',
        props: { filters: none, options: documentFilterOptions },
    },
    {
        name: 'Filters in use',
        props: {
            filters: { ...none, category: 4, person: 7, status: 'active' },
            options: documentFilterOptions,
        },
    },
    {
        name: 'Expiring',
        props: {
            filters: { ...none, expiring: true },
            options: documentFilterOptions,
        },
    },
]);
