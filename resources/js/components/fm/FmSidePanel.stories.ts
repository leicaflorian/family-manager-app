/**
 * The stories of `FmSidePanel`: the preview of a document floating on the
 * right, as *Documents* shows it on a computer narrower than 1440 px.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { h } from 'vue';
import { withSlots } from '@/stories/slots';
import { documentDetail } from '@/test/fixtures';
import FmDocumentDetail from './FmDocumentDetail.vue';
import FmSidePanel from './FmSidePanel.vue';

const meta = {
    title: 'Panels/FmSidePanel',
    component: FmSidePanel,
    // The panel is fixed to the page: in the Docs it gets a frame of its own
    parameters: { docs: { story: { inline: false, iframeHeight: 720 } } },
} satisfies Meta<typeof FmSidePanel>;

export default meta;

type Story = StoryObj<typeof meta>;

export const Document: Story = {
    args: { label: documentDetail.title },
    render: withSlots({
        default: () =>
            h(FmDocumentDetail, { document: documentDetail, compact: true }),
    }),
};
