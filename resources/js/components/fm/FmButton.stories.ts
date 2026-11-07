/**
 * The stories of `FmButton`: the five variants, with
 * an icon, as a link, disabled, and only the icon.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { Plus, Trash2, Upload } from '@lucide/vue';
import FmButton from './FmButton.vue';

const meta = {
    title: 'Basics/FmButton',
    component: FmButton,
} satisfies Meta<typeof FmButton>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The main button of a page. */
export const Solid: Story = {
    args: { variant: 'solid' },
    render: (args) => ({
        components: { FmButton },
        setup: () => ({ args }),
        template: '<FmButton v-bind="args">Save</FmButton>',
    }),
};

export const Outline: Story = {
    args: { variant: 'outline' },
    render: withSlots({ default: 'Cancel' }),
};

export const Soft: Story = {
    args: { variant: 'soft' },
    render: withSlots({ default: 'Add' }),
};

export const Text: Story = {
    args: { variant: 'text' },
    render: withSlots({ default: 'Skip' }),
};

export const Danger: Story = {
    args: { variant: 'danger', icon: Trash2 },
    render: withSlots({ default: 'Delete' }),
};

export const WithAnIconAsALink: Story = {
    name: 'With an icon, as a link',
    args: { icon: Upload, href: '/upload' },
    render: withSlots({ default: 'Upload a document' }),
};

export const Disabled: Story = {
    args: { disabled: true },
    render: withSlots({ default: 'Save' }),
};

export const OnlyTheIcon: Story = {
    name: 'Only the icon',
    args: { variant: 'soft', icon: Plus, label: 'Add a person' },
};
