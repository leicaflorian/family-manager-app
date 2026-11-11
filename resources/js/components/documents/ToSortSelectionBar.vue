<script setup lang="ts">
/**
 * The bar of the chosen files in *To sort* on the phone, as the board
 * *Da smistare*: «3 chosen», the bin and *Check 3 files*, just above the
 * navigation bar. It does not decide when to show: the page shows it only
 * when at least one file is chosen, and gives the list room under it.
 * A part of the page `documents/ToSort`, not a component of the canvas.
 */
import { Sparkles, Trash2 } from '@lucide/vue';
import FmIcon from '@/components/fm/FmIcon.vue';

const { processing = false } = defineProps<{
    /** The files chosen. */
    count: number;
    /** Whether the person can delete files: without it the bin is not there. */
    canDelete: boolean;
    /** A request is on its way: the buttons cannot be tapped. */
    processing?: boolean;
}>();

defineEmits<{
    /** *Check N files* was tapped. */
    check: [];
    /** The bin was tapped. */
    delete: [];
}>();
</script>

<template>
    <div
        class="sticky bottom-0 flex items-center justify-between gap-3 border-t border-edge-soft bg-card px-5 py-3"
    >
        <span class="grow text-label whitespace-nowrap">
            {{ $tChoice('{1} :count chosen|[2,*] :count chosen', count) }}
        </span>
        <button
            v-if="canDelete"
            type="button"
            :disabled="processing"
            :aria-label="
                $tChoice(
                    '{1} Delete the chosen file|[2,*] Delete the :count chosen files',
                    count,
                )
            "
            class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-edge-soft bg-card text-overdue disabled:cursor-not-allowed disabled:opacity-50"
            @click="$emit('delete')"
        >
            <FmIcon :icon="Trash2" :size="20" />
        </button>
        <!-- 48 px as the board, not the 44 px of FmButton: a class cannot override h-control -->
        <button
            type="button"
            :disabled="processing"
            class="flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-action px-4.5 text-body-strong whitespace-nowrap text-on-action hover:bg-action-dark disabled:cursor-not-allowed disabled:bg-edge disabled:text-ink-soft"
            @click="$emit('check')"
        >
            <FmIcon :icon="Sparkles" :size="20" />
            {{
                $tChoice(
                    '{1} Check :count file|[2,*] Check :count files',
                    count,
                )
            }}
        </button>
    </div>
</template>
