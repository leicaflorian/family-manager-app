/**
 * The cases of `FmBottomBar` for the component catalogue: Home marked, and
 * More marked because Family is open.
 */
import { defineExamples } from '@/catalog/examples';
import FmBottomBar from './FmBottomBar.vue';
import { navigationItems } from './navigation';

const items = navigationItems();

export default defineExamples(FmBottomBar, [
    { name: 'Home', props: { items, active: 'home' } },
    { name: 'Documents', props: { items, active: 'documents' } },
    {
        name: 'Files to sort',
        props: { items, active: 'home', uploadCount: 4 },
    },
    { name: 'More is marked', props: { items, active: 'family' } },
]);
