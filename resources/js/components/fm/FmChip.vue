<script setup lang="ts">
/**
 * A filter chip: a pill that is on or off, with the number of things it
 * leaves, as the types of the search results («Documents 3») or the sources
 * of *To sort*.
 *
 * Rules: 38 px high, full radius. Soft (default), on: `action-soft`
 * background and `action-dark` text, strong; solid, on: `action` background,
 * `on-action` text. Off, always a `card` background with an `edge-soft`
 * border. The number is `meta-strong`, `ink-soft` (inherited when on solid).
 * Without `href` it is a button with `aria-pressed`; with `href` it is a
 * link, and when on it says `aria-current="page"` (the filter is in the
 * address). Disabled with `href` is a dimmed `span`, not a link.
 *
 * @group basics
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const {
    label,
    count,
    pressed = false,
    href,
    variant = 'soft',
    disabled = false,
} = defineProps<{
    /** What the chip filters. */
    label: string;
    /** How many things it leaves; not shown if missing. */
    count?: number;
    /** The chip is on. */
    pressed?: boolean;
    /** Makes the chip a link (an Inertia visit) to this address. */
    href?: string;
    /** Look of the chip when on: `soft` (tinted) or `solid` (filled). */
    variant?: 'soft' | 'solid';
    /** With `href`: a dimmed chip that is not a link. */
    disabled?: boolean;
}>();

defineEmits<{
    /** The chip was pressed. */
    click: [];
}>();

/** The look of the chip: on (soft or solid) or off, dimmed when disabled. */
const classes = computed((): string =>
    cn(
        'inline-flex h-9.5 shrink-0 items-center gap-1.5 rounded-full border px-3.5 whitespace-nowrap',
        pressed
            ? variant === 'solid'
                ? 'border-action bg-action text-label text-on-action'
                : 'border-action-soft bg-action-soft text-label text-action-dark'
            : 'border-edge-soft bg-card text-body-sm text-ink',
        disabled && 'opacity-50',
    ),
);

/** The look of the number: on the solid chip it takes the text of the chip. */
const countClasses = computed((): string =>
    cn(
        'text-meta-strong',
        pressed && variant === 'solid' ? 'text-on-action' : 'text-ink-soft',
    ),
);
</script>

<template>
    <span v-if="href && disabled" aria-disabled="true" :class="classes">
        {{ label }}
        <span v-if="count !== undefined" :class="countClasses">{{
            count
        }}</span>
    </span>
    <Link
        v-else-if="href"
        :href="href"
        :aria-current="pressed ? 'page' : undefined"
        :class="classes"
    >
        {{ label }}
        <span v-if="count !== undefined" :class="countClasses">{{
            count
        }}</span>
    </Link>
    <button
        v-else
        type="button"
        :aria-pressed="pressed"
        :disabled="disabled"
        :class="classes"
        @click="$emit('click')"
    >
        {{ label }}
        <span v-if="count !== undefined" :class="countClasses">{{
            count
        }}</span>
    </button>
</template>
