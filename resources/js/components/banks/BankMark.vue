<script setup lang="ts">
/**
 * The tile of a bank: its logo or, without one, its initials on the colour of the banks. The size is the one of the
 * list of linked banks (44 px) or of the list of banks to choose from (40 px).
 */
import { computed } from 'vue';
import { bankInitials } from '@/lib/banks';
import { cn } from '@/lib/utils';

const {
    name,
    logo = null,
    small = false,
} = defineProps<{
    /** The name of the bank. */
    name: string;
    /** The address of the logo. */
    logo?: string | null;
    /** 40 px, as in the list of banks to choose from; 44 px otherwise. */
    small?: boolean;
}>();

/** The classes of the tile. */
const classes = computed((): string =>
    cn(
        'flex shrink-0 items-center justify-center overflow-hidden rounded-lg',
        small ? 'size-10' : 'size-11',
        logo ? 'bg-card' : 'bg-banks-soft text-label text-banks-text',
    ),
);
</script>

<template>
    <span :class="classes">
        <img
            v-if="logo"
            :src="logo"
            :alt="name"
            class="size-full object-contain"
        />
        <span v-else aria-hidden="true">{{ bankInitials(name) }}</span>
    </span>
</template>
