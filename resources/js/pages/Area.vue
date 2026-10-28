<script setup lang="ts">
/**
 * *An area*: the open cards of the area by name, with how many there are and
 * how many are overdue; the closed ones stay folded under *Closed*. An
 * `admin` adds a card with *New*, which opens the choice of the type of the
 * area, or the form when the area has only one.
 */
import { Head } from '@inertiajs/vue3';
import { ChevronDown, Plus, Shapes } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCardRow from '@/components/fm/FmCardRow.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
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

/** The closed cards are shown. */
const showClosed = ref(false);

/** The cards still open. */
const open = computed(() => area.cards.filter((card) => !card.closed));

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

        <div
            v-if="open.length > 0"
            v-auto-animate
            class="flex flex-col divide-y divide-divider overflow-hidden rounded-2xl bg-white"
        >
            <FmCardRow
                v-for="card in open"
                :key="`${card.type}-${card.id}`"
                :card="card"
            />
        </div>

        <div v-if="closed.length > 0" class="flex flex-col gap-2">
            <button
                type="button"
                :aria-expanded="showClosed"
                class="flex items-center gap-1.5 self-start border-0 bg-transparent p-0 text-[15px] font-semibold text-ink-soft"
                @click="showClosed = !showClosed"
            >
                <FmIcon
                    :icon="ChevronDown"
                    :size="18"
                    :class="showClosed && 'rotate-180'"
                />
                {{ $t('Closed') }} ({{ closed.length }})
            </button>
            <div
                v-auto-animate
                class="flex flex-col divide-y divide-divider overflow-hidden rounded-2xl bg-white"
            >
                <template v-if="showClosed">
                    <FmCardRow
                        v-for="card in closed"
                        :key="`${card.type}-${card.id}`"
                        :card="card"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
