/**
 * The cases of `FmCardHeader` for the component catalogue: the coloured top
 * of a card page in two areas, with the buttons on the right and with a long
 * name.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import { pandaRow, policyDetail } from '@/test/fixtures';
import FmButton from './FmButton.vue';
import FmCardHeader from './FmCardHeader.vue';

export default defineExamples(FmCardHeader, [
    {
        name: 'Vehicle',
        props: { card: pandaRow, heading: 'Plate GH 482 TK · Marco' },
    },
    {
        name: 'Insurance with actions',
        props: { card: policyDetail.card, heading: policyDetail.heading },
        slots: {
            actions: () => h(FmButton, { variant: 'text' }, () => 'Edit'),
        },
    },
    {
        name: 'Long name',
        props: {
            card: {
                ...policyDetail.card,
                label: 'Polizza assicurativa RC auto e furto incendio - Fiat Panda Cross 4x4 - UnipolSai Assicurazioni',
            },
            heading: 'Car · policyholder Marco Bianchi · expires 20/11/2026',
        },
    },
]);
