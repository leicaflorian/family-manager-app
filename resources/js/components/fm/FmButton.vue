<script setup lang="ts">
/**
 * The button of the canvas, in six variants. With `href` it becomes an Inertia link.
 *
 * Rules of use:
 * - Only one solid button per page: the one done most often.
 * - Red only to delete, and it asks first: use `FmDeleteButton`.
 * - Icon only: always with `label`, which screen readers say. `muted` greys
 *   the icon, as the bin at the end of a row; with a text it greys the text and
 *   leaves no background, as *Ignore* beside the buttons of a row.
 * - The text is a verb, informal (*Save*, *Choose a file*).
 * - Size: 44 px; 52 px in the panels from below. `compact` is the pair of buttons
 *   under a row of the Agenda: on the phone they share the line, on the computer 40 px.
 *   `rowAction` is a button of a row of *From the bank*, under it on the phone and
 *   in its column on the computer: its own width, 44 px, the body-sm text, an
 *   18 px icon and 10 px corners.
 *
 * @group basics
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useOnColor } from '@/composables/useOnColor';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

/** The variants of the *Components* page. */
export type ButtonVariant =
    | 'solid'
    | 'outline'
    | 'soft'
    | 'text'
    | 'danger'
    | 'neutral';

const {
    variant = 'solid',
    type = 'button',
    href,
    disabled = false,
    icon,
    wide = false,
    download = false,
    external = false,
    label,
    small = false,
    xsmall = false,
    plain = false,
    muted = false,
    compact = false,
    rowAction = false,
} = defineProps<{
    /** The look: solid (blue), outline, soft (light blue), text, danger (red), neutral (white with a grey edge). */
    variant?: ButtonVariant;
    /** The type of the button, when it is not a link. */
    type?: 'button' | 'submit';
    /** The address: if there is one, the button is a link. */
    href?: string;
    /** Disabled: it cannot be tapped. */
    disabled?: boolean;
    /** The icon before the text. */
    icon?: LucideIcon;
    /** Full width, like the main button on the phone. */
    wide?: boolean;
    /** With `href`: a plain link that downloads the file, not an Inertia visit. */
    download?: boolean;
    /** With `href`: a plain link that opens the address in a new tab. */
    external?: boolean;
    /**
     * Only the icon, square: this is the text shown on hover and said to screen
     * readers, and the text of the slot is left out.
     */
    label?: string;
    /** With `label`: a smaller square, 40 px, as beside the title of a panel. */
    small?: boolean;
    /** With `label`: the smallest square, 30 px, as beside the tabs of a card. */
    xsmall?: boolean;
    /** With `label`: no background until hovered, as beside a row. */
    plain?: boolean;
    /**
     * With `label`: the icon in grey, whatever the variant, as the bin of a row.
     * With a text: the text in grey with no background, as *Ignore* beside the
     * buttons of a row.
     */
    muted?: boolean;
    /**
     * The pair of buttons under a row, in the label style: on the phone as tall
     * as a control and sharing the line with the other button, with no room at
     * the sides; on the computer its own width, 40 px with 14 px at the sides.
     */
    compact?: boolean;
    /**
     * A button of a row of *From the bank* (under it on the phone, in its column
     * on the computer): its own width, as tall as a control, in the body-sm
     * text, an 18 px icon, 10 px at the sides and 10 px corners.
     */
    rowAction?: boolean;
}>();

defineEmits<{
    /** The button was tapped. */
    click: [event: MouseEvent];
}>();

const styles: Record<ButtonVariant, string> = {
    solid: cn(
        'bg-action px-5 text-on-action hover:bg-action-dark active:bg-action-dark',
    ),
    outline: cn(
        'border-2 border-action bg-card px-5 text-action hover:bg-action-soft',
    ),
    soft: cn('bg-action-soft px-5 text-action-dark hover:bg-action/15'),
    text: cn('px-3 text-action hover:bg-action-soft hover:text-action-dark'),
    danger: cn('px-3 text-overdue hover:bg-overdue/10'),
    neutral: cn(
        'border border-edge-soft bg-card px-5 text-ink hover:bg-canvas',
    ),
};

/** On a coloured background every variant is translucent white. */
const onColor = useOnColor();

const classes = computed((): string =>
    cn(
        // Every variant has the same size: two buttons side by side must match
        'inline-flex shrink-0 items-center justify-center gap-2 text-body whitespace-nowrap transition-colors',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action',
        'disabled:cursor-not-allowed disabled:border-0 disabled:bg-edge disabled:text-ink-soft',
        // With a text the height follows the panel; the icon squares do not
        !label && 'h-control rounded-control',
        styles[variant],
        compact &&
            'h-control flex-1 basis-0 gap-[7px] rounded-lg px-0 text-label lg:h-10 lg:flex-none lg:px-3.5',
        rowAction && 'h-control gap-1.5 rounded-md px-2.5 text-body-sm',
        wide && 'w-full',
        // Only the icon: the light grey square of the canvas
        label &&
            'size-control-md rounded-md border-0 bg-canvas px-0 hover:bg-divider',
        label && small && 'size-10',
        label && xsmall && 'size-7.5 rounded-tag',
        label && plain && 'bg-transparent hover:bg-canvas',
        label && variant !== 'danger' && 'text-ink',
        label && muted && 'text-ink-soft',
        !label && muted && 'text-ink-soft hover:bg-canvas hover:text-ink',
        // The colour behind shows through, a bit less on hover
        onColor &&
            'border-0 bg-on-hero-soft text-body-strong text-on-hero hover:bg-on-hero/25 focus-visible:outline-on-hero active:bg-on-hero/30',
    ),
);

/** The size of the icon: smaller in the smallest square and in a button of a row. */
const iconSize = computed((): number => {
    if (label && xsmall) {
        return 16;
    }

    return rowAction && !label ? 18 : 20;
});
</script>

<template>
    <a
        v-if="href && (download || external)"
        :href="href"
        :download="download ? '' : undefined"
        :target="external ? '_blank' : undefined"
        :rel="external ? 'noopener' : undefined"
        :aria-label="label"
        :title="label"
        :class="classes"
    >
        <FmIcon v-if="icon" :icon="icon" :size="iconSize" />
        <slot v-if="!label" />
    </a>
    <Link
        v-else-if="href"
        :href="href"
        :aria-label="label"
        :title="label"
        :class="classes"
    >
        <FmIcon v-if="icon" :icon="icon" :size="iconSize" />
        <slot v-if="!label" />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="disabled"
        :aria-label="label"
        :title="label"
        :class="classes"
        @click="$emit('click', $event)"
    >
        <FmIcon v-if="icon" :icon="icon" :size="iconSize" />
        <slot v-if="!label" />
    </button>
</template>
