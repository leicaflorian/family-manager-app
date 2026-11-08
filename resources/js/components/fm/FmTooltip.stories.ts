/**
 * The stories of `FmTooltip`: under a button, and beside it. Hover the
 * button, or reach it with Tab, to see the text.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { h } from 'vue';
import { withSlots } from '@/stories/slots';
import FmButton from './FmButton.vue';
import FmTooltip from './FmTooltip.vue';

const meta = {
    title: 'Basics/FmTooltip',
    component: FmTooltip,
} satisfies Meta<typeof FmTooltip>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The button the text is about. */
const button = (): ReturnType<typeof h> => h(FmButton, null, () => 'Upload');

export const Under: Story = {
    args: { text: 'Upload a document from the computer' },
    render: withSlots({ default: button }),
};

export const Beside: Story = {
    args: { text: 'Upload a document from the computer', side: 'right' },
    render: withSlots({ default: button }),
};
