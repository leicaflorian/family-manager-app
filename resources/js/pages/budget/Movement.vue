<script setup lang="ts">
/**
 * A movement on its own page, for the phone: the panel of the movement with
 * the link back to the month it belongs to. Without a movement it is the page
 * of *New expense or income*, which can start from a planned expense or income.
 */
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import FmBackLink from '@/components/fm/FmBackLink.vue';
import FmMovementPanel from '@/components/fm/FmMovementPanel.vue';
import type { MovementFormOptions, MovementStart } from '@/lib/budget';
import { movementsUrl } from '@/lib/budget';

const { movement, start } = defineProps<{
    /** The movement, or null for a new expense or income. */
    movement: App.Data.MovementData | null;
    /** What a new movement starts from. */
    start: MovementStart;
    /** The due dates that the movement can settle. */
    occurrences: App.Data.OptionData[];
    /** The choices of the form. */
    options: MovementFormOptions;
}>();

/** The page of the month the movement belongs to. */
const back = computed(() =>
    movementsUrl({ month: (movement?.date ?? start.date).slice(0, 7) }),
);

/** Goes back to the month. */
function close(): void {
    router.get(back.value);
}
</script>

<template>
    <Head :title="movement?.title ?? $t('New expense or income')" />

    <div class="flex max-w-[720px] flex-col gap-4">
        <FmBackLink :href="back" :label="$t('Movements')" />
        <FmMovementPanel
            :movement="movement"
            :options="options"
            :start="start"
            :occurrences="occurrences"
            @close="close"
        />
    </div>
</template>
