<script setup lang="ts">
/**
 * The small sheet that stands for a file in front of its row: a document of
 * the family, or a file chosen and not saved yet.
 */
import { FileText } from '@lucide/vue';
import FmIcon from './FmIcon.vue';
</script>

<template>
    <span
        aria-hidden="true"
        class="flex h-[46px] w-[38px] shrink-0 items-center justify-center rounded-lg border border-edge bg-canvas text-ink-soft"
    >
        <FmIcon :icon="FileText" :size="20" />
    </span>
</template>
