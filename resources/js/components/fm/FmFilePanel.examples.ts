/**
 * The cases of `FmFilePanel` for the component catalogue: the file of a
 * document, compact, with *Change the file* and a file without preview.
 */
import { defineExamples } from '@/catalog/examples';
import { documentDetail } from '@/test/fixtures';
import FmFilePanel from './FmFilePanel.vue';

/** A small inline picture of a page, as the server would draw a preview. */
const page =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="560" viewBox="0 0 420 560"><rect width="420" height="560" fill="#fff" stroke="#ccd"/><rect x="40" y="48" width="220" height="16" fill="#99a"/><rect x="40" y="96" width="340" height="10" fill="#ccd"/><rect x="40" y="120" width="300" height="10" fill="#ccd"/></svg>',
    );

/** The file of the document, with a preview that needs no server. */
const file: App.Data.FileData = { ...documentDetail.file, preview_url: page };

export default defineExamples(FmFilePanel, [
    { name: 'Page', props: { file } },
    { name: 'Compact', props: { file, compact: true } },
    {
        name: 'Change the file',
        props: { file, changeUrl: '/documents/5/file' },
    },
    {
        name: 'No preview',
        props: { file: { ...file, preview_url: null } },
    },
]);
