/**
 * The cases of `FmDateTile` for the component catalogue: a date in the future,
 * an expiring date already passed (red), and a date that does not expire.
 */
import { defineExamples } from '@/catalog/examples';
import FmDateTile from './FmDateTile.vue';

/** A date `days` from today, as `aaaa-mm-gg`, so the cases stay true over time. */
function fromToday(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

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
