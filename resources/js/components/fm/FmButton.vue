<script setup lang="ts">
/**
 * The button of the canvas, in five variants. Only one solid button per page:
 * the one done most often. With `href` it becomes an Inertia link.
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
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
}>();

defineEmits<{
    /** The button was tapped. */
    click: [event: MouseEvent];
}>();

const styles: Record<ButtonVariant, string> = {
    solid: 'h-14 rounded-2xl bg-action px-6 text-[17px] font-bold text-white hover:bg-action-dark active:bg-action-dark',
    outline:
        'h-14 rounded-2xl border-2 border-action bg-white px-6 text-[17px] font-bold text-action hover:bg-action-soft',
    soft: 'h-11 rounded-xl bg-action-soft px-4 text-[15px] font-semibold text-action-dark',
    text: 'h-11 px-3 text-base font-semibold text-action hover:text-action-dark',
    danger: 'h-11 px-3 text-base font-semibold text-overdue',
};

const classes = computed((): string[] => [
    'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap transition-colors',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action',
    'disabled:cursor-not-allowed disabled:border-0 disabled:bg-edge disabled:text-ink-soft',
    styles[variant],
    wide ? 'w-full' : '',
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <FmIcon v-if="icon" :icon="icon" :size="20" />
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="disabled"
        :class="classes"
        @click="$emit('click', $event)"
    >
        <FmIcon v-if="icon" :icon="icon" :size="20" />
        <slot />
    </button>
</template>
