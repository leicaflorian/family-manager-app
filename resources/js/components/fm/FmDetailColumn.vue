<script setup lang="ts">
/**
 * The column on the right of a list on the wide layout: the detail of the
 * chosen row, sticky under the top bar and never taller than the window below
 * it (it scrolls inside). The header has ‹ › to walk the rows of the list,
 * «N of M», the `actions` slot and a ×. Escape closes it, unless something
 * already took the key or an open menu, list or question has it.
 *
 * @group panels
 */
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { useEscapeClose } from '@/composables/useEscapeClose';
import { detailStep } from '@/lib/detail';
import FmIcon from './FmIcon.vue';

const { position, total } = defineProps<{
    /** What the column is called, said to screen readers: the title of the row. */
    label: string;
    /** The place of the chosen row in the list (from 1), or null when it is not in this page. */
    position: number | null;
    /** How many rows the list has. */
    total: number;
    /** The name of the ‹ button. */
    previousLabel: string;
    /** The name of the › button. */
    nextLabel: string;
}>();

const emit = defineEmits<{
    /** ‹ was pressed: open the row before. */
    previous: [];
    /** › was pressed: open the row after. */
    next: [];
    /** The × or Escape: close the column. */
    close: [];
}>();

defineSlots<{
    /** The detail of the row. */
    default(): unknown;
    /** Icon buttons of the header, before the ×. */
    actions?(): unknown;
}>();

// Escape closes the column, but not when it is meant for an open menu, a list of the
// filters beside it or a question (the delete confirm) of the actions
useEscapeClose(
    () => emit('close'),
    ['[role="dialog"]', '[role="alertdialog"]'],
);
</script>

<template>
    <aside
        :aria-label="label"
        class="sticky top-(--app-header) flex max-h-[calc(100dvh-var(--app-header,0px)-(--spacing(6)))] w-[380px] shrink-0 flex-col gap-3.5 self-start overflow-y-auto rounded-card-lg bg-card px-5 pt-4.5 pb-5.5 shadow-card computer:w-[400px]"
    >
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    :class="detailStep"
                    :aria-label="previousLabel"
                    :disabled="position === null || position <= 1"
                    @click="emit('previous')"
                >
                    <FmIcon :icon="ChevronLeft" :size="20" />
                </button>
                <button
                    type="button"
                    :class="detailStep"
                    :aria-label="nextLabel"
                    :disabled="position === null || position >= total"
                    @click="emit('next')"
                >
                    <FmIcon :icon="ChevronRight" :size="20" />
                </button>
                <span class="text-body-sm text-ink-soft">{{
                    position === null
                        ? $t('– of :total', { total: String(total) })
                        : $t(':position of :total', {
                              position: String(position),
                              total: String(total),
                          })
                }}</span>
            </div>
            <div class="-mr-2 flex shrink-0 items-center">
                <slot name="actions" />
                <button
                    type="button"
                    :aria-label="trans('Close')"
                    class="flex size-touch shrink-0 cursor-pointer items-center justify-center border-0 bg-transparent text-ink-soft"
                    @click="emit('close')"
                >
                    <FmIcon :icon="X" :size="22" />
                </button>
            </div>
        </div>
        <slot />
    </aside>
</template>
