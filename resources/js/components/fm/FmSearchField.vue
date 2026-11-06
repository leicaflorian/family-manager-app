<script setup lang="ts">
/**
 * The search box with the magnifying glass: in *Documents*, in the panel that
 * links a document to a card and, `small`, in the toolbar of *Reminders*. What
 * it does with the text is up to the page.
 *
 * @group forms
 */
import { Search } from '@lucide/vue';
import FmIcon from './FmIcon.vue';

const { label, small = false } = defineProps<{
    /** What is searched: the name of the box and the text shown while it is empty. */
    label: string;
    /** As high as the buttons of a toolbar, 36 px, with a smaller text. */
    small?: boolean;
}>();

/** The text typed. */
const text = defineModel<string>({ required: true });
</script>

<template>
    <label
        class="flex shrink-0 items-center rounded-md border border-edge bg-white text-ink-soft"
        :class="
            small ? 'h-9 gap-2 px-3 text-sm' : 'h-11.5 gap-2.5 px-3.5 text-base'
        "
    >
        <FmIcon :icon="Search" :size="small ? 18 : 20" />
        <input
            v-model="text"
            type="search"
            :placeholder="label"
            :aria-label="label"
            class="min-w-0 grow border-0 bg-transparent text-ink outline-none"
        />
    </label>
</template>
