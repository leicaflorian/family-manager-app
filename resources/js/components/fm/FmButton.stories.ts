/**
 * The stories of `FmButton`: the five variants, with an icon, only the icon in
 * its four squares, wide, disabled, in focus, on a colour and in a sheet.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { provideOnColor } from '@/composables/useOnColor';
import { Plus, Trash2, Upload } from '@lucide/vue';
import { defineComponent, h } from 'vue';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';

const meta = {
    title: 'Basics/FmButton',
    component: FmButton,
} satisfies Meta<typeof FmButton>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The main button of a page: only one for each page. */
export const Solid: Story = {
    args: { variant: 'solid' },
    render: withSlots({ default: 'Save' }),
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

export const WithIcon: Story = {
    name: 'With icon',
    args: { icon: Upload },
    render: withSlots({ default: 'Choose a file' }),
};

export const AsALink: Story = {
    name: 'As a link',
    args: { icon: Upload, href: '/upload' },
    render: withSlots({ default: 'Upload a document' }),
};

/** The four squares: 44 px, 40 px (`small`), 30 px (`xsmall`) and `plain`. */
export const IconOnly: Story = {
    name: 'Icon only',
    args: { variant: 'soft', icon: Plus, label: 'Add a person' },
    render: (args) => ({
        components: { FmButton },
        setup: () => ({ args }),
        template: `<div class="flex items-center gap-3">
            <FmButton v-bind="args" />
            <FmButton v-bind="args" small />
            <FmButton v-bind="args" xsmall />
            <FmButton v-bind="args" plain />
        </div>`,
    }),
};

export const Wide: Story = {
    args: { wide: true },
    render: withSlots({ default: 'Save' }),
};

export const Disabled: Story = {
    args: { disabled: true },
    render: withSlots({ default: 'Save' }),
};

/** The ring of the keyboard focus, shown without touching the button. */
export const Focus: Story = {
    args: { variant: 'solid' },
    parameters: { pseudo: { focusVisible: true } },
    render: withSlots({ default: 'Save' }),
};

/** On the colour of a card page every variant turns translucent white. */
export const OnColour: Story = {
    name: 'On colour',
    render: (args) => ({
        components: { FmButton },
        setup: () => {
            provideOnColor();

            return { args, Plus };
        },
        template: `<div class="flex items-center gap-3 rounded-lg bg-hero p-5">
            <FmButton v-bind="args">Save</FmButton>
            <FmButton v-bind="args" variant="outline">Cancel</FmButton>
            <FmButton v-bind="args" variant="soft" :icon="Plus">Add</FmButton>
        </div>`,
    }),
};

/** The same buttons in a panel from below: 44 px, 52 px once Task 6 lands. */
export const InASheet: Story = {
    name: 'In a sheet',
    render: () =>
        defineComponent({
            setup: () => () =>
                h(
                    FmBottomSheet,
                    { open: true, title: 'Add a person' },
                    {
                        default: () =>
                            h('div', { class: 'flex flex-col gap-3' }, [
                                h(FmButton, { wide: true }, () => 'Save'),
                                h(
                                    FmButton,
                                    { wide: true, variant: 'outline' },
                                    () => 'Cancel',
                                ),
                            ]),
                    },
                ),
        }),
};
