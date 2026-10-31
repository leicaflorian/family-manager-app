/**
 * The cases of `FmSidebar` for the component catalogue: Home, inside Areas on
 * an area, and Settings.
 */
import { defineExamples } from '@/catalog/examples';
import { areaCounts } from '@/test/fixtures';
import FmSidebar from './FmSidebar.vue';
import { navigationItems } from './navigation';

const shared = {
    items: navigationItems(),
    areas: areaCounts,
    family: 'Bianchi',
    user: 'Marco Bianchi',
};

export default defineExamples(FmSidebar, [
    { name: 'Home', props: { ...shared, active: 'home', path: '/' } },
    {
        name: 'Inside an area',
        props: { ...shared, active: 'areas', path: '/areas/home' },
    },
    {
        name: 'Settings',
        props: { ...shared, active: 'settings', path: '/settings' },
    },
]);
