<script setup lang="ts">
/**
 * The button of the canvas, in five variants. Only one solid button per page:
 * the one done most often. With `href` it becomes an Inertia link.
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

/** The variants of the *Components* page. */
export type ButtonVariant = 'solid' | 'outline' | 'soft' | 'text' | 'danger';

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
} = defineProps<{
    /** The look: solid (blue), outline, soft (light blue), text, danger (red). */
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
}>();

defineEmits<{
    /** The button was tapped. */
    click: [event: MouseEvent];
}>();

const styles: Record<ButtonVariant, string> = {
    solid: cn(
        'bg-action px-5 font-normal text-white hover:bg-action-dark active:bg-action-dark',
    ),
    outline: cn(
        'border-2 border-action bg-white px-5 font-normal text-action hover:bg-action-soft',
    ),
    soft: cn('bg-action-soft px-5 font-normal text-action-dark'),
    text: cn('font-norml px-3 text-action hover:text-action-dark'),
    danger: cn('px-3 font-normal text-overdue'),
};

const classes = computed((): string =>
    cn(
        // Every variant has the same size: two buttons side by side must match
        'inline-flex h-11.5 shrink-0 items-center justify-center gap-2 rounded-[10px] text-base whitespace-nowrap transition-colors',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action',
        'disabled:cursor-not-allowed disabled:border-0 disabled:bg-edge disabled:text-ink-soft',
        styles[variant],
        wide && 'w-full',
        // Only the icon: the light grey square of the canvas
        label && 'size-11.5 border-0 bg-canvas px-0 hover:bg-divider',
        label && small && 'size-10',
        label && xsmall && 'size-7.5 rounded-lg',
        label && variant !== 'danger' && 'text-ink',
    ),
);

/** The size of the icon: smaller in the smallest square. */
const iconSize = computed((): number => (label && xsmall ? 16 : 20));
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
