<script setup lang="ts">
/**
 * The link beside the title of a section (`FmCard`), as *Upload*, *Add* or
 * *Record payment*: blue text with its icon, normal weight, no box around it,
 * underlined on hover. With `href` it is an Inertia link, otherwise a button.
 * Every section uses it, so the links beside the titles look the same on
 * every page.
 *
 * @group navigation
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const { href, icon } = defineProps<{
    /** The address: if there is one, it is a link. */
    href?: string;
    /** The icon before the text. */
    icon?: LucideIcon;
}>();

defineEmits<{
    /** The button was tapped. */
    click: [event: MouseEvent];
}>();

defineSlots<{
    /** The text of the link. */
    default: () => unknown;
}>();

/** The look of the link, the same as a link and as a button. */
const classes = cn(
    'inline-flex shrink-0 items-center gap-2 rounded-md text-base font-normal whitespace-nowrap text-action underline-offset-2 hover:text-action-dark hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action',
);
</script>

<template>
    <Link v-if="href" :href="href" :class="[classes, 'no-underline']">
        <FmIcon v-if="icon" :icon="icon" :size="20" />
        <slot />
    </Link>
    <button
        v-else
        type="button"
        :class="[classes, 'cursor-pointer border-0 bg-transparent p-0']"
        @click="$emit('click', $event)"
    >
        <FmIcon v-if="icon" :icon="icon" :size="20" />
        <slot />
    </button>
</template>
