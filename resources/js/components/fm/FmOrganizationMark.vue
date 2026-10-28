<script setup lang="ts">
/**
 * The mark of an organization: its logo or, without one, the icon of its
 * type. The size, the corners and the background come from the `class` of
 * whoever uses it, so the list and the page keep their own.
 */
import { organizationIcon } from '@/lib/family';
import FmIcon from './FmIcon.vue';

const {
    types,
    logo = null,
    name = '',
    iconSize = 20,
} = defineProps<{
    /** The values of its types, for the icon. */
    types: readonly string[];
    /** The address of the logo. */
    logo?: string | null;
    /** The name, for those who cannot see the logo. */
    name?: string;
    /** The side of the icon, in pixels. */
    iconSize?: number;
}>();
</script>

<template>
    <span
        class="flex shrink-0 items-center justify-center overflow-hidden text-[#4A5565]"
    >
        <img
            v-if="logo"
            :src="logo"
            :alt="name"
            class="size-full object-contain"
        />
        <FmIcon v-else :icon="organizationIcon(types)" :size="iconSize" />
    </span>
</template>
