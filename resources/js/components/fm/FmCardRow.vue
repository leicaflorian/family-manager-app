<script setup lang="ts">
/**
 * A card in a list: the icon of its type in the colour of its area, the name
 * and below the subtitle or the next date («Inspection · in 75 days», red once
 * overdue). It leads to the card; a closed card is grey.
 */
import { computed } from 'vue';
import { areaOf, areaTile } from '@/lib/areas';
import { cardIcon } from '@/lib/cards';
import { relativeDays } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show } from '@/routes/cards';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

const { card } = defineProps<{
    /** The card, as a row of a list. */
    card: App.Data.CardData;
}>();

/** The line below the name: the next date if there is one, else the subtitle. */
const line = computed((): string | undefined => {
    if (card.next_label !== null && card.next_date !== null) {
        return `${card.next_label} · ${relativeDays(card.next_date)}`;
    }

    return card.subtitle || undefined;
});

/** The colours of the icon tile: those of the area, grey when the card is closed. */
const tile = computed((): string => {
    const area = areaOf(card.area);

    return cn(
        'flex size-10 shrink-0 items-center justify-center rounded-xl',
        card.closed || area === undefined
            ? 'bg-canvas text-ink-soft'
            : areaTile[area],
    );
});
</script>

<template>
    <FmRow
        :title="card.label"
        :subtitle="line"
        :tone="card.overdue && card.next_date !== null ? 'overdue' : 'normal'"
        :href="show({ type: card.type, id: card.id }).url"
        :class="card.closed && 'opacity-60'"
    >
        <template #before>
            <span :class="tile">
                <FmIcon :icon="cardIcon(card.type)" :size="20" />
            </span>
        </template>
    </FmRow>
</template>
