<script setup lang="ts">
/**
 * *An area*: the open cards of the area by name, with how many there are and
 * how many are overdue, grouped by type when the area has more types (an
 * empty type is left out); the closed ones stay folded under *Closed*. An
 * `admin` adds a card with *New*, which opens the choice of the type of the
 * area, or the form when the area has only one.
 */
import { Head } from '@inertiajs/vue3';
import { Plus, Shapes } from '@lucide/vue';
import { computed } from 'vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCardRow from '@/components/fm/FmCardRow.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFold from '@/components/fm/FmFold.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { newCardUrl } from '@/lib/cards';
import { areaSummary } from '@/lib/cardTexts';
import { areas } from '@/routes';

const { area, types, canCreate } = defineProps<{
    /** The area with its cards, the open ones first. */
    area: App.Data.AreaData;
    /** The card types of the area. */
    types: App.Data.CardTypeData[];
    /** Whoever is looking adds cards (an `admin`). */
    canCreate: boolean;
}>();

/** The cards still open. */
const open = computed(() => area.cards.filter((card) => !card.closed));

/** A list of open cards on the page, with the heading of its type. */
interface CardGroup {
    /** The type of the cards, or `all` when the area is not grouped. */
    type: string;
    /** The heading: the type in the plural, or null when the area is not grouped. */
    label: string | null;
    /** The open cards, by name. */
    cards: App.Data.CardData[];
}

/**
 * The open cards by type, in the order of *New card*, when the area has more
 * types; otherwise a single list without a heading.
 */
const groups = computed((): CardGroup[] => {
    if (types.length <= 1) {
        return [{ type: 'all', label: null, cards: open.value }];
    }

    return types
        .map((type) => ({
            type: type.type,
            label: type.plural_label,
            cards: open.value.filter((card) => card.type === type.type),
        }))
        .filter((group) => group.cards.length > 0);
});

/** The cards that are closed. */
const closed = computed(() => area.cards.filter((card) => card.closed));
</script>

<template>
    <Head :title="area.label" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmTitle
            :title="area.label"
            :subtitle="areaSummary(area.count, area.overdue)"
            :back="areas().url"
            :back-label="$t('Areas')"
        >
            <template v-if="canCreate" #actions>
                <FmActions
                    :actions="[
                        {
                            label: $t('New'),
                            icon: Plus,
                            href: newCardUrl(types, area.area),
                        },
                    ]"
                />
            </template>
        </FmTitle>

        <FmEmpty
            v-if="area.cards.length === 0"
            :icon="Shapes"
            :title="$t('No cards in this area yet')"
        >
            <FmButton v-if="canCreate" :href="newCardUrl(types, area.area)">{{
                $t('Add a card')
            }}</FmButton>
        </FmEmpty>

        <template v-if="open.length > 0">
            <section
                v-for="group in groups"
                :key="group.type"
                :aria-labelledby="
                    group.label ? `group-${group.type}` : undefined
                "
                class="flex flex-col gap-2"
            >
                <h2
                    v-if="group.label"
                    :id="`group-${group.type}`"
                    class="m-0 flex items-baseline gap-2 text-[17px] font-bold"
                >
                    {{ group.label }}
                    <span class="text-[15px] font-normal text-ink-soft">{{
                        group.cards.length
                    }}</span>
                </h2>
                <div
                    v-auto-animate
                    class="flex flex-col divide-y divide-divider overflow-hidden rounded-card bg-card"
                >
                    <FmCardRow
                        v-for="card in group.cards"
                        :key="`${card.type}-${card.id}`"
                        :card="card"
                    />
                </div>
            </section>
        </template>

        <FmFold
            v-if="closed.length > 0"
            :label="`${$t('Closed')} (${closed.length})`"
        >
            <FmCardRow
                v-for="card in closed"
                :key="`${card.type}-${card.id}`"
                :card="card"
            />
        </FmFold>
    </div>
</template>
