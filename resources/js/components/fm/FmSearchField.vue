<script setup lang="ts">
/**
 * The search box with the magnifying glass: in *Documents*, in the panel that
 * links a document to a card and, `small`, in the toolbar of *Reminders*. What
 * it does with the text is up to the page.
 *
 * Rules: the box is 44 px high (`h-control-md`) and, `small`, 36 px with a
 * smaller text and icon, as high as the buttons of a toolbar; the border is
 * `edge`, the icon and the placeholder are `ink-soft`; the label of the prop
 * is both the placeholder and the name for a screen reader, so it says what is
 * searched («Search the documents»). It has no button: the page reacts to the
 * text as it is typed.
 *
 * @group fields
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
        class="flex shrink-0 items-center rounded-md border border-edge bg-card text-ink-soft"
        :class="
            small
                ? 'h-9 gap-2 px-3 text-body-sm'
                : 'h-control-md gap-2.5 px-3.5 text-body'
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
