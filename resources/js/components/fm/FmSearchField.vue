<script setup lang="ts">
/**
 * The search box with the magnifying glass: in *Documents* and in the panel
 * that links a document to a card. What it does with the text is up to the page.
 */
import { Search } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const { label } = defineProps<{
    /** What is searched: the name of the box and the text shown while it is empty. */
    label: string;
}>();

/** The text typed. */
const text = defineModel<string>({ required: true });
</script>

<template>
    <label
        class="flex h-11.5 items-center gap-2.5 rounded-[10px] border border-edge bg-white px-3.5 text-ink-soft"
    >
        <FmIcon :icon="Search" :size="20" />
        <input
            v-model="text"
            type="search"
            :placeholder="label"
            :aria-label="label"
            class="min-w-0 grow border-0 bg-transparent text-base text-ink outline-none"
        />
    </label>
</template>
