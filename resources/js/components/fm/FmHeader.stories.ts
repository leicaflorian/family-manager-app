/**
 * The stories of `FmHeader`: the title alone and
 * with the sentence below.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import FmHeader from './FmHeader.vue';

const meta = {
    title: 'Navigation/FmHeader',
    component: FmHeader,
} satisfies Meta<typeof FmHeader>;

export default meta;

type Story = StoryObj<typeof meta>;

export const TitleOnly: Story = {
    name: 'Title only',
    args: { title: 'Hi, Rosa' },
};

export const WithASentence: Story = {
    name: 'With a sentence',
    args: {
        title: 'Hi, Rosa',
        subtitle: 'Marco invited you to the Bianchi family.',
    },
};
