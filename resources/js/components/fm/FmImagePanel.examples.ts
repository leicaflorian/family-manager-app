/**
 * The cases of `FmImagePanel` for the component catalogue: a photo to change
 * or remove, no photo yet and a photo refused by the server. The default slot
 * draws the image.
 */
import { h } from 'vue';
import { defineExamples } from '@/catalog/examples';
import FmImagePanel from './FmImagePanel.vue';

/** A small inline photo. */
const photo =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160"><rect width="160" height="160" fill="#8fb8de"/><circle cx="80" cy="64" r="28" fill="#fff"/><rect x="36" y="104" width="88" height="56" rx="28" fill="#fff"/></svg>',
    );

/**
 * Draws the image, or a grey circle when there is none. The catalogue gives a
 * slot no props, so each case says which image it draws.
 *
 * @param image - the image of the case, or null
 * @returns the function that draws the slot
 */
const draw = (image: string | null) => () =>
    image === null
        ? h('div', { class: 'size-32 rounded-full bg-divider' })
        : h('img', { src: image, alt: '', class: 'size-32 rounded-full' });

export default defineExamples(FmImagePanel, [
    {
        name: 'With a photo',
        props: {
            image: photo,
            addLabel: 'Add a photo',
            changeLabel: 'Change the photo',
        },
        slots: { default: draw(photo) },
    },
    {
        name: 'Without a photo',
        props: {
            image: null,
            addLabel: 'Add a photo',
            changeLabel: 'Change the photo',
        },
        slots: { default: draw(null) },
    },
    {
        name: 'With an error',
        props: {
            image: null,
            addLabel: 'Add a logo',
            changeLabel: 'Change the logo',
            error: 'The image is too big.',
        },
        slots: { default: draw(null) },
    },
]);
