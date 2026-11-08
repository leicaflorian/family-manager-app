/**
 * The stories of `FmFilePanel`: the file of a
 * document, compact, with *Change the file* and a file without preview.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
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

const meta = {
    title: 'Panels/FmFilePanel',
    component: FmFilePanel,
} satisfies Meta<typeof FmFilePanel>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Page: Story = {
    args: { file },
};

export const Compact: Story = {
    args: { file, compact: true },
};

export const ChangeTheFile: Story = {
    name: 'Change the file',
    args: { file, changeUrl: '/documents/5/file' },
};

export const NoPreview: Story = {
    name: 'No preview',
    args: { file: { ...file, preview_url: null } },
};
