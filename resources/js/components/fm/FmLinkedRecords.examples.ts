/**
 * The cases of `FmLinkedRecords` for the component catalogue: the records that
 * name a person or an organization, with one type, with several (a tab for
 * each) and with none.
 */
import { defineExamples } from '@/catalog/examples';
import FmLinkedRecords from './FmLinkedRecords.vue';

/** The cards of the vehicles that name the person. */
const vehicles: App.Data.LinkGroupData = {
    type: 'vehicle',
    label: 'Vehicles',
    options: [
        { value: '1', label: 'Fiat Panda', help: null },
        { value: '2', label: 'Vespa Primavera', help: null },
    ],
};

/** The policies that name the person. */
const policies: App.Data.LinkGroupData = {
    type: 'insurance_policy',
    label: 'Insurance policies',
    options: [
        { value: '1', label: 'RC auto Panda - UnipolSai', help: null },
        { value: '2', label: 'Polizza vita Marco', help: null },
        { value: '3', label: 'Casa e famiglia - Generali', help: null },
    ],
};

export default defineExamples(FmLinkedRecords, [
    { name: 'One type', props: { groups: [vehicles] } },
    { name: 'Several types', props: { groups: [vehicles, policies] } },
    { name: 'No records', props: { groups: [] } },
]);
