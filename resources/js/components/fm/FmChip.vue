<script setup lang="ts">
/**
 * A filter chip: a pill that is on or off, with the number of things it
 * leaves, as the types of the search results («Documents 3»).
 *
 * Rules: 36 px high, full radius. On, the background is `action-soft` and the
 * text `action-dark`, strong; off, a `card` background with an `edge-soft`
 * border. The number is `ink-soft`, semibold. It is a button with
 * `aria-pressed`, so its name is the label and the number.
 *
 * @group basics
 */
const {
    label,
    count,
    pressed = false,
} = defineProps<{
    /** What the chip filters. */
    label: string;
    /** How many things it leaves; not shown if missing. */
    count?: number;
    /** The chip is on. */
    pressed?: boolean;
}>();

defineEmits<{
    /** The chip was pressed. */
    click: [];
}>();
</script>

<template>
    <button
        type="button"
        :aria-pressed="pressed"
        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 whitespace-nowrap"
        :class="
            pressed
                ? 'border-action-soft bg-action-soft text-label text-action-dark'
                : 'border-edge-soft bg-card text-body-sm text-ink'
        "
        @click="$emit('click')"
    >
        {{ label }}
        <span
            v-if="count !== undefined"
            class="text-meta-strong text-ink-soft"
            >{{ count }}</span
        >
    </button>
</template>
