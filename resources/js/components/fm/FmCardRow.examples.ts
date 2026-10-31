/**
 * The cases of `FmCardRow` for the component catalogue: a card with a date
 * ahead, an overdue one, one with only a subtitle and a closed one.
 */
import { fromToday } from '@/catalog/dates';
import { defineExamples } from '@/catalog/examples';
import { pandaRow } from '@/test/fixtures';
import FmCardRow from './FmCardRow.vue';

export default defineExamples(FmCardRow, [
    {
        name: 'Date ahead',
        props: { card: { ...pandaRow, next_date: fromToday(75) } },
    },
    {
        name: 'Overdue',
        props: {
            card: {
                ...pandaRow,
                label: 'TV Samsung',
                area: 'home',
                type: 'belonging',
                next_label: 'Warranty end',
                next_date: fromToday(-5),
                overdue: true,
            },
        },
    },
    {
        name: 'Subtitle only',
        props: {
            card: {
                ...pandaRow,
                label: 'Casa di via Roma',
                area: 'home',
                type: 'home',
                subtitle: 'Via Roma 12, Bologna',
                next_label: null,
                next_date: null,
            },
        },
    },
    {
        name: 'Closed',
        props: {
            card: {
                ...pandaRow,
                label: 'Luce - Enel',
                area: 'home',
                type: 'utility',
                subtitle: 'Enel',
                next_label: null,
                next_date: null,
                closed: true,
            },
        },
    },
]);
