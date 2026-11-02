/**
 * The cases of `FmDeadlineDateSheet` for the component catalogue: the panel of
 * a passed deadline, opened with *Open* on the catalogue page, to correct its
 * date or to write the date of the renewal.
 */
import { defineExamples } from '@/catalog/examples';
import { agendaEntries } from '@/test/fixtures';
import FmDeadlineDateSheet from './FmDeadlineDateSheet.vue';

export default defineExamples(FmDeadlineDateSheet, [
    {
        name: 'Update the date',
        props: { open: false, entry: agendaEntries[0], renewed: false },
    },
    {
        name: 'Renewed',
        props: { open: false, entry: agendaEntries[0], renewed: true },
    },
]);
