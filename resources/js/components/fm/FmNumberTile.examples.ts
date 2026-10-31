/**
 * The cases of `FmNumberTile` for the component catalogue: an amount with a
 * hint, a count, an amount inside the hint and a tile without a hint.
 */
import { defineExamples } from '@/catalog/examples';
import FmNumberTile from './FmNumberTile.vue';

export default defineExamples(FmNumberTile, [
    {
        name: 'Amount with a hint',
        props: {
            number: {
                label: 'Planned',
                amount: 72,
                count: null,
                hint: 'per month, estimated',
                hint_amount: null,
            },
        },
    },
    {
        name: 'Count',
        props: {
            number: {
                label: 'Bills paid',
                amount: null,
                count: 9,
                hint: null,
                hint_amount: null,
            },
        },
    },
    {
        name: 'Amount in the hint',
        props: {
            number: {
                label: 'Paid in 2026',
                amount: 642.1,
                count: null,
                hint: 'of :amount planned',
                hint_amount: 864,
            },
        },
    },
    {
        name: 'Without a hint',
        props: {
            number: {
                label: 'Premium',
                amount: 418,
                count: null,
                hint: null,
                hint_amount: null,
            },
        },
    },
]);
