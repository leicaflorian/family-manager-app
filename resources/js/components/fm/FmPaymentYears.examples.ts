/**
 * The cases of `FmPaymentYears` for the component catalogue: the payments by
 * year, folded, with a count per year, linked and with the full date.
 */
import { defineExamples } from '@/catalog/examples';
import { lucePayments } from '@/test/fixtures';
import FmPaymentYears from './FmPaymentYears.vue';

export default defineExamples(FmPaymentYears, [
    { name: 'All open', props: { years: lucePayments } },
    { name: 'Folded', props: { years: lucePayments, folded: true } },
    {
        name: 'With counts',
        props: {
            years: lucePayments,
            counts: { 2026: '3 bills', 2025: '1 bill' },
        },
    },
    {
        name: 'Linked, full date',
        props: { years: lucePayments, linked: true, fullDate: true },
    },
    { name: 'Nothing paid', props: { years: [] } },
]);
