/**
 * The cases of `FmDateTile` for the component catalogue: a date in the future,
 * an expiring date already passed (red), and a date that does not expire.
 */
import { fromToday } from '@/catalog/dates';
import { defineExamples } from '@/catalog/examples';
import FmDateTile from './FmDateTile.vue';

export default defineExamples(FmDateTile, [
    {
        name: 'Expires soon',
        props: {
            date: { label: 'Expires', date: fromToday(20), expires: true },
        },
    },
    {
        name: 'Expired',
        props: {
            date: { label: 'Expires', date: fromToday(-3), expires: true },
        },
    },
    {
        name: 'Does not expire',
        props: {
            date: { label: 'Issued', date: fromToday(-200), expires: false },
        },
    },
]);
