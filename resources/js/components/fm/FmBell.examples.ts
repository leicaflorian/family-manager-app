/**
 * The cases of `FmBell` for the component catalogue: nothing new, a few
 * notices, a lot of them and the menu still loading.
 */
import { defineExamples } from '@/catalog/examples';
import { seenNotices, unreadNotices } from '@/test/fixtures';
import FmBell from './FmBell.vue';

export default defineExamples(FmBell, [
    {
        name: 'Nothing new',
        props: { count: 0, notices: { unread: [], seen: seenNotices } },
    },
    {
        name: 'Two new',
        props: {
            count: 2,
            notices: { unread: unreadNotices, seen: seenNotices },
        },
    },
    {
        name: 'Many',
        props: {
            count: 120,
            notices: { unread: unreadNotices, seen: seenNotices },
        },
    },
    { name: 'Loading', props: { count: 2 } },
]);
