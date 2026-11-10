<script setup lang="ts">
/**
 * A movement on its own page, for the phone: the panel of the movement with
 * the link back to the month it belongs to. Without a movement it is the page
 * of *New expense or income*, which can start from a planned expense or income,
 * or from a card (*Add expense*): then the link leads back to the card.
 */
import { Head, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmBackLink from '@/components/fm/FmBackLink.vue';
import FmMovementPanel from '@/components/fm/FmMovementPanel.vue';
import type { MovementFormOptions, MovementStart } from '@/lib/budget';
import { usePageTrail } from '@/composables/usePageTrail';
import { movementsUrl } from '@/lib/budget';
import { budget } from '@/routes';
import { show as showCard } from '@/routes/cards';

const { movement, start, options } = defineProps<{
    /** The movement, or null for a new expense or income. */
    movement: App.Data.MovementData | null;
    /** What a new movement starts from. */
    start: MovementStart;
    /** The due dates that the movement can settle. */
    occurrences: App.Data.OptionData[];
    /** The choices of the form. */
    options: MovementFormOptions;
}>();

/** The card a new expense comes from, with its name, or null. */
const fromCard = computed((): { href: string; label: string } | null => {
    const [type, id] = start.card?.split(':') ?? [];

    if (movement || !type || !id) {
        return null;
    }

    return {
        href: showCard.url({ type, id: Number(id) }),
        label:
            options.cards.find((card) => card.value === start.card)?.label ??
            trans('Card'),
    };
});

/** The page of the card a new expense comes from, else of the month the movement belongs to. */
const back = computed(
    () =>
        fromCard.value?.href ??
        movementsUrl({ month: (movement?.date ?? start.date).slice(0, 7) }),
);

usePageTrail(() => [
    { label: trans('Budget'), href: budget().url },
    { label: movement?.title ?? trans('New expense or income') },
]);

/** Goes back to the card or to the month. */
function close(): void {
    router.get(back.value);
}
</script>

<template>
    <Head :title="movement?.title ?? $t('New expense or income')" />

    <div class="flex max-w-[720px] flex-col gap-4">
        <FmBackLink :href="back" :label="fromCard?.label ?? $t('Movements')" />
        <FmMovementPanel
            :movement="movement"
            :options="options"
            :start="start"
            :occurrences="occurrences"
            @close="close"
        />
    </div>
</template>
