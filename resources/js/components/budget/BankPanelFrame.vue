<script setup lang="ts">
/**
 * The frame of the panels of *From the bank* that follow an action, as the boards *Dalla banca, altri movimenti come
 * questo* draw it: a bottom sheet on the phone, and when wide a floating panel of 400 px at the right of the page,
 * with the title and the × at the top. The content goes in the slot.
 *
 * @group panels
 */
import { X } from '@lucide/vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import { useWideLayout } from '@/composables/useWideLayout';

const { title } = defineProps<{
    /** The title of the panel. */
    title: string;
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

defineSlots<{
    /** The content under the title. */
    default: () => unknown;
}>();

const wide = useWideLayout();
</script>

<template>
    <FmBottomSheet v-if="!wide" v-model:open="open" :title="title">
        <slot />
    </FmBottomSheet>
    <aside
        v-else-if="open"
        role="dialog"
        :aria-label="title"
        class="fixed right-6 bottom-6 z-30 flex max-h-[calc(100dvh-3rem)] w-[400px] flex-col gap-3.5 overflow-y-auto rounded-sheet bg-card px-6 pt-5 pb-6 shadow-float"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 class="m-0 text-sheet-title text-ink">{{ title }}</h2>
            <button
                type="button"
                :aria-label="$t('Close')"
                class="-mr-2.5 flex size-11 cursor-pointer items-center justify-center border-0 bg-transparent p-0 text-ink-soft"
                @click="open = false"
            >
                <X aria-hidden="true" :size="22" />
            </button>
        </div>
        <slot />
    </aside>
</template>
