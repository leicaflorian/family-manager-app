<script setup lang="ts">
/**
 * An entry of `FmMenu`: an action, a page of the app (`href`) or an address
 * opened in a new tab (`href` and `external`).
 */
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { DropdownMenuItem } from 'reka-ui';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const {
    icon,
    href,
    external = false,
    danger = false,
} = defineProps<{
    /** The icon before the text. */
    icon?: LucideIcon;
    /** The address: if there is one, the entry is a link. */
    href?: string;
    /** With `href`: a plain link that opens the address in a new tab. */
    external?: boolean;
    /** Red, for the entry that deletes. */
    danger?: boolean;
}>();

defineEmits<{
    /** The entry was chosen. */
    select: [];
}>();

defineSlots<{
    /** The text of the entry. */
    default: () => unknown;
}>();

const classes = cn(
    'flex h-11 w-full cursor-pointer items-center gap-3 rounded-[10px] px-3 text-left text-base whitespace-nowrap outline-none data-highlighted:bg-action-soft',
    danger ? 'text-overdue' : 'text-ink',
);
</script>

<template>
    <DropdownMenuItem as-child @select="$emit('select')">
        <a
            v-if="href && external"
            :href="href"
            target="_blank"
            rel="noopener"
            :class="classes"
        >
            <FmIcon v-if="icon" :icon="icon" :size="20" />
            <slot />
        </a>
        <Link v-else-if="href" :href="href" :class="classes">
            <FmIcon v-if="icon" :icon="icon" :size="20" />
            <slot />
        </Link>
        <button v-else type="button" :class="classes">
            <FmIcon v-if="icon" :icon="icon" :size="20" />
            <slot />
        </button>
    </DropdownMenuItem>
</template>
