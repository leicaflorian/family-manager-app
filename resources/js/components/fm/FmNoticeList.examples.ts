/**
 * The cases of `FmNoticeList` for the component catalogue: new and seen
 * notices, only seen ones and no notice at all.
 */
import { defineExamples } from '@/catalog/examples';
import { seenNotices, unreadNotices } from '@/test/fixtures';
import FmNoticeList from './FmNoticeList.vue';

export default defineExamples(FmNoticeList, [
    {
        name: 'New and seen',
        props: { unread: unreadNotices, seen: seenNotices },
    },
    { name: 'Only seen', props: { unread: [], seen: seenNotices } },
    { name: 'None', props: { unread: [], seen: [] } },
]);
