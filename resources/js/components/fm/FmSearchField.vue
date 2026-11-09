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
 * text as it is typed. With a list of results under it (`controls`), the box
 * says which list it opens and whether it is open (`expanded`); the arrow keys
 * reach the page as `keydown` on the box.
 *
 * @group fields
 */
import { Search, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { nextTick, onMounted, useTemplateRef } from 'vue';
import { desktopSystem } from '@/lib/devices';
import FmIcon from './FmIcon.vue';

const {
    label,
    small = false,
    clearable = false,
    shortcut = false,
    autofocus = false,
    controls,
    expanded = false,
} = defineProps<{
    /** What is searched: the name of the box and the text shown while it is empty. */
    label: string;
    /** As high as the buttons of a toolbar, 36 px, with a smaller text. */
    small?: boolean;
    /**
     * A round button that empties the box, shown only with text. It marks the
     * whole-app search box (Home and *Search*): only that one has the blue focus
     * ring; the other boxes keep the grey border and show the cursor.
     */
    clearable?: boolean;
    /** Shows the key that focuses the box (⌘K or Ctrl K) while it is empty. */
    shortcut?: boolean;
    /** The box takes the focus when it appears. */
    autofocus?: boolean;
    /** The id of the list of results the box opens while typing, if any. */
    controls?: string;
    /** That list is open; said only with `controls`. */
    expanded?: boolean;
}>();

const emit = defineEmits<{
    /** Enter was pressed in the box. */
    submit: [];
    /** Esc was pressed in the box. */
    escape: [];
}>();

/** The text typed. */
const text = defineModel<string>({ required: true });

const box = useTemplateRef<HTMLInputElement>('box');

/** The key of the shortcut as it is written on this computer. */
const shortcutKey = desktopSystem() === 'mac' ? '⌘K' : 'Ctrl K';

/** Puts the cursor in the box. */
function focus(): void {
    box.value?.focus();
}

/** Empties the box and gives it the focus back. */
function clear(): void {
    text.value = '';
    focus();
}

onMounted(() => {
    if (autofocus) {
        // after the page has put the box in the document, or the focus is lost
        void nextTick(focus);
    }
});

defineExpose({ focus });
</script>

<template>
    <label
        class="flex shrink-0 items-center rounded-md border border-edge-soft bg-card text-ink-soft"
        :class="[
            small
                ? 'h-9 gap-2 px-3 text-body-sm'
                : 'h-control-md gap-2.5 px-3.5 text-body',
            clearable
                ? 'focus-within:border-action focus-within:shadow-focus focus-within:outline focus-within:outline-1 focus-within:-outline-offset-1 focus-within:outline-action'
                : undefined,
        ]"
    >
        <FmIcon :icon="Search" :size="small ? 18 : 20" />
        <input
            ref="box"
            v-model="text"
            type="search"
            :placeholder="label"
            :aria-label="label"
            :aria-keyshortcuts="shortcut ? 'Meta+K Control+K' : undefined"
            :aria-controls="controls"
            :aria-expanded="controls ? expanded : undefined"
            class="min-w-0 grow border-0 bg-transparent text-ink outline-none"
            @keydown.enter="emit('submit')"
            @keydown.esc="emit('escape')"
        />
        <button
            v-if="clearable && text !== ''"
            type="button"
            :aria-label="trans('Clear the search')"
            class="-mr-2 flex size-8 shrink-0 items-center justify-center rounded-full bg-segment text-ink-soft"
            @click="clear"
        >
            <FmIcon :icon="X" :size="16" />
        </button>
        <kbd
            v-if="shortcut && text === ''"
            aria-hidden="true"
            class="shrink-0 rounded-md border border-edge-soft bg-canvas px-1.5 py-0.5 text-meta-strong whitespace-nowrap text-ink-soft"
            >{{ shortcutKey }}</kbd
        >
    </label>
</template>
