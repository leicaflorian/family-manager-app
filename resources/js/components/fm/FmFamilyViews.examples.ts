/**
 * The cases of `FmFamilyViews` for the component catalogue: the *People* view
 * and the *Organizations* view with a button.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import { familySummary } from '@/test/fixtures';
import FmButton from './FmButton.vue';
import FmFamilyViews from './FmFamilyViews.vue';

export default defineExamples(FmFamilyViews, [
    { name: 'People', props: { family: familySummary, current: 'people' } },
    {
        name: 'Organizations, with a button',
        props: { family: familySummary, current: 'organizations' },
        slots: {
            actions: () =>
                h(
                    FmButton,
                    { variant: 'soft', href: '/organizations/create' },
                    () => 'Add',
                ),
        },
    },
]);
