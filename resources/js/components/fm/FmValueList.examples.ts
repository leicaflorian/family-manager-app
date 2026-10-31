/**
 * The cases of `FmValueList` for the component catalogue: plain values, values
 * that link to a person or an organization and the same links in a new tab.
 */
import { defineExamples } from '@/catalog/examples';
import FmValueList from './FmValueList.vue';

/** Values of a policy, with the person and the insurer as links. */
const values: App.Data.CardValueData[] = [
    { label: 'Type', text: 'Auto', href: null },
    { label: 'Policyholder', text: 'Marco Bianchi', href: '/people/1' },
    { label: 'Insurer', text: 'UnipolSai', href: '/organizations/4' },
];

export default defineExamples(FmValueList, [
    {
        name: 'Plain',
        props: {
            values: [
                { label: 'Type', text: 'Auto', href: null },
                { label: 'Plate', text: 'FG 123 AB', href: null },
            ],
        },
    },
    { name: 'With links', props: { values } },
    { name: 'Links in a new tab', props: { values, newTab: true } },
]);
