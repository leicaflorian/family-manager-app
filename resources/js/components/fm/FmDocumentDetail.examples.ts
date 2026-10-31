/**
 * The cases of `FmDocumentDetail` for the component catalogue: a document as
 * its own page, as the panel beside the list, for a `member` who cannot edit,
 * and without links, tags or notes.
 */
import { defineExamples } from '@/catalog/examples';
import { documentDetail } from '@/test/fixtures';
import FmDocumentDetail from './FmDocumentDetail.vue';

/** A small inline picture of a page, as the server would draw a preview. */
const page =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="560" viewBox="0 0 420 560"><rect width="420" height="560" fill="#fff" stroke="#ccd"/><rect x="40" y="48" width="220" height="16" fill="#99a"/><rect x="40" y="96" width="340" height="10" fill="#ccd"/><rect x="40" y="120" width="300" height="10" fill="#ccd"/></svg>',
    );

/** The document of the fixtures, with a preview that needs no server. */
const document: App.Data.DocumentDetailData = {
    ...documentDetail,
    file: { ...documentDetail.file, preview_url: page },
};

export default defineExamples(FmDocumentDetail, [
    { name: 'Page', props: { document } },
    { name: 'Beside the list', props: { document, compact: true } },
    {
        name: 'Member',
        props: {
            document: { ...document, can_edit: false, can_delete: false },
        },
    },
    {
        name: 'Expired, bare',
        props: {
            document: {
                ...document,
                status: 'expired',
                status_label: 'Expired',
                expiry_date: '2026-09-30',
                notes: null,
                tags: [],
                links: [],
            },
        },
    },
]);
