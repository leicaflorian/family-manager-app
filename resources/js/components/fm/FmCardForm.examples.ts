/**
 * The cases of `FmCardForm` for the component catalogue: a new insurance
 * policy, an existing one (the vehicle shows for a car policy), a utility
 * whose code is named by the type, an error of the server and the form while
 * it sends.
 */
import { defineExamples } from '@/catalog/examples';
import { policyFields, utilityFields } from '@/test/fixtures';
import FmCardForm from './FmCardForm.vue';

export default defineExamples(FmCardForm, [
    {
        name: 'New',
        props: { fields: policyFields, values: { type: 'car' } },
    },
    {
        name: 'Edit',
        props: {
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car', vehicle_id: 1 },
        },
    },
    {
        name: 'Label by type',
        props: {
            fields: utilityFields,
            values: { type: 'gas', supply_point_code: '14631000123456' },
        },
    },
    {
        name: 'With an error',
        props: {
            fields: policyFields,
            values: { title: '', type: 'home' },
            serverErrors: { title: 'The title is required.' },
        },
    },
    {
        name: 'Processing',
        props: {
            fields: policyFields,
            values: { title: 'RC auto Panda', type: 'car', vehicle_id: 1 },
            processing: true,
        },
    },
]);
