/**
 * The stories of `FmTitle`: the title alone, with a
 * sentence, with the link back, and with buttons on the right.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { h } from 'vue';
import FmButton from './FmButton.vue';
import FmTitle from './FmTitle.vue';

const meta = {
    title: 'Basics/FmTitle',
    component: FmTitle,
} satisfies Meta<typeof FmTitle>;

export default meta;

type Story = StoryObj<typeof meta>;

export const TitleOnly: Story = {
    name: 'Title only',
    args: { title: 'Documents' },
};

export const WithASentence: Story = {
    name: 'With a sentence',
    args: { title: 'Documents', subtitle: 'Everything the family keeps.' },
};

export const WithTheLinkBack: Story = {
    name: 'With the link back',
    args: {
        title: 'Bolletta luce ottobre',
        subtitle: 'Edison',
        back: '/areas/home',
        backLabel: 'Home',
    },
};

export const WithSomethingBeside: Story = {
    name: 'With something beside',
    args: { title: 'Agenda' },
    render: withSlots({
        beside: () =>
            h(
                'span',
                { class: 'rounded-lg bg-[#E6EAF0] px-4 py-2' },
                'Deadlines · Reminders',
            ),
    }),
};

export const WithButtons: Story = {
    name: 'With buttons',
    args: { title: 'Documents' },
    render: withSlots({
        actions: () =>
            h(FmButton, { variant: 'soft', href: '/upload' }, () => 'Upload'),
    }),
};
