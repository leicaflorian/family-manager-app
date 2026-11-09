<script setup lang="ts">
/**
 * A QR code drawn by the server as an SVG, in a frame, for the panel *Link the
 * phone*. The drawing is put in the page only after it is read as an SVG and
 * cleaned: anything that is not an `svg` shows nothing, and scripts, foreign
 * content, event handlers and links are taken out, so the page never runs what
 * a drawing carries. The frame is white in both themes (`on-hero`), because a
 * code is read dark on light.
 *
 * @group figures
 */
import { computed } from 'vue';

const { svg } = defineProps<{
    /** The SVG as the server made it. */
    svg: string;
    /** What the image is, said to screen readers. */
    label: string;
}>();

/** The elements an SVG for a QR code never needs. */
const FORBIDDEN = ['script', 'foreignObject', 'style', 'image', 'use', 'a'];

/**
 * Reads the drawing and cleans it.
 *
 * @param source - the SVG as text
 * @returns the cleaned SVG, or an empty text if the source is not an SVG
 */
function clean(source: string): string {
    const root = new DOMParser().parseFromString(
        source,
        'image/svg+xml',
    ).documentElement;

    if (root.localName !== 'svg' || root.querySelector('parsererror')) {
        return '';
    }

    root.querySelectorAll(FORBIDDEN.join(',')).forEach((node) => node.remove());

    [root, ...root.querySelectorAll('*')].forEach((node) =>
        [...node.attributes]
            .filter(
                (attribute) =>
                    /^on/i.test(attribute.name) || /href/i.test(attribute.name),
            )
            .forEach((attribute) => node.removeAttribute(attribute.name)),
    );
    root.setAttribute('aria-hidden', 'true');

    return new XMLSerializer().serializeToString(root);
}

/** The drawing that is safe to show. */
const drawing = computed((): string => clean(svg));
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -- cleaned by `clean` -->
    <div
        role="img"
        :aria-label="label"
        class="inline-flex shrink-0 rounded-card border border-divider bg-on-hero p-3 [&>svg]:block [&>svg]:size-[176px]"
        v-html="drawing"
    />
</template>
