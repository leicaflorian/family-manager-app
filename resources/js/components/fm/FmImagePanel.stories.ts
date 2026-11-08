/**
 * The stories of `FmImagePanel`: a photo to change
 * or remove, no photo yet and a photo refused by the server. The default slot
 * draws the image.
 */
import type { Meta, StoryObj } from '@storybook/vue3-vite';
import { withSlots } from '@/stories/slots';
import { h } from 'vue';
import FmImagePanel from './FmImagePanel.vue';

/** A small inline photo. */
const photo =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><rect width="160" height="160" fill="#8fb8de"/><circle cx="80" cy="64" r="28" fill="#fff"/><rect x="36" y="104" width="88" height="56" rx="28" fill="#fff"/></svg>',
    );

/**
 * Draws the image, or a grey circle when there is none. A slot gets no
 * props, so each story says which image it draws.
 *
 * @param image - the image of the case, or null
 * @returns the function that draws the slot
 */
const draw = (image: string | null) => () =>
    image === null
        ? h('div', { class: 'size-32 rounded-full bg-divider' })
        : h('img', { src: image, alt: '', class: 'size-32 rounded-full' });

const meta = {
    title: 'Panels/FmImagePanel',
    component: FmImagePanel,
} satisfies Meta<typeof FmImagePanel>;

export default meta;

type Story = StoryObj<typeof meta>;

export const WithAPhoto: Story = {
    name: 'With a photo',
    args: {
        image: photo,
        addLabel: 'Add a photo',
        changeLabel: 'Change the photo',
    },
    render: withSlots({ default: draw(photo) }),
};

export const WithoutAPhoto: Story = {
    name: 'Without a photo',
    args: {
        image: null,
        addLabel: 'Add a photo',
        changeLabel: 'Change the photo',
    },
    render: withSlots({ default: draw(null) }),
};

export const WithAnError: Story = {
    name: 'With an error',
    args: {
        image: null,
        addLabel: 'Add a logo',
        changeLabel: 'Change the logo',
        error: 'The image is too big.',
    },
    render: withSlots({ default: draw(null) }),
};
