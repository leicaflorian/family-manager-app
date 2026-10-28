<script setup lang="ts">
/**
 * *Areas*: the nine areas of family life (home, vehicles, insurance…), each
 * with how many cards it has, how many are overdue and its first cards. The
 * search finds a card in every area by its name. An `admin` adds cards; with
 * no cards at all the page invites to create the first one.
 */
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Search, Shapes } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmActions from '@/components/fm/FmActions.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCardRow from '@/components/fm/FmCardRow.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { newCardUrl } from '@/lib/cards';
import { cardCountText, overdueText } from '@/lib/cardTexts';
import { cn } from '@/lib/utils';
import { create } from '@/routes/cards';
import { show } from '@/routes/areas';

/** How many cards of an area are shown under its name. */
const PREVIEW = 3;

const { areas, types, canCreate } = defineProps<{
    /** The nine areas, each with its cards. */
    areas: App.Data.AreaData[];
    /** The card types, to know where *Add* leads. */
    types: App.Data.CardTypeData[];
    /** Whoever is looking adds cards (an `admin`). */
    canCreate: boolean;
}>();

/** The text in the search box. */
const query = ref('');

/** There is no card in any area. */
const empty = computed(() => areas.every((area) => area.count === 0));

/** The cards whose name or subtitle holds the search, from every area. */
const found = computed((): App.Data.CardData[] => {
    const text = query.value.trim().toLowerCase();

    return areas
        .flatMap((area) => area.cards)
        .filter((card) =>
            `${card.label} ${card.subtitle}`.toLowerCase().includes(text),
        );
});

/**
 * The first open cards of an area.
 *
 * @param area - the area
 * @returns the cards to show under its name
 */
function preview(area: App.Data.AreaData): App.Data.CardData[] {
    return area.cards.filter((card) => !card.closed).slice(0, PREVIEW);
}

/**
 * The classes of the icon tile of an area.
 *
 * @param area - the code of the area
 * @returns the classes
 */
function tile(area: string): string {
    const code = areaOf(area);

    return cn(
        'flex size-11 shrink-0 items-center justify-center rounded-xl',
        code === undefined ? 'bg-canvas text-ink-soft' : areaTile[code],
    );
}
</script>

<template>
    <Head :title="$t('Areas')" />

    <div class="flex flex-col gap-5">
        <FmTitle :title="$t('Areas')">
            <template v-if="canCreate" #actions>
                <FmActions
                    :actions="[
                        {
                            label: $t('New card'),
                            icon: Plus,
                            href: create().url,
                        },
                    ]"
                />
            </template>
        </FmTitle>

        <FmEmpty
            v-if="empty"
            :icon="Shapes"
            :title="$t('No cards yet')"
            :text="
                $t(
                    'A card gathers the things of the family, such as the home, a vehicle or a policy, with their dates and documents.',
                )
            "
        >
            <FmButton v-if="canCreate" :href="create().url">{{
                $t('Create the first card')
            }}</FmButton>
        </FmEmpty>

        <label
            v-else
            class="flex items-center gap-2.5 rounded-xl border border-edge bg-white px-3.5"
        >
            <FmIcon :icon="Search" :size="20" />
            <input
                v-model="query"
                type="search"
                :placeholder="$t('Search all cards')"
                :aria-label="$t('Search all cards')"
                class="h-11 min-w-0 flex-1 border-0 bg-transparent text-base outline-none"
            />
        </label>

        <div v-if="query.trim() !== ''" v-auto-animate class="flex flex-col">
            <div
                v-if="found.length > 0"
                class="flex flex-col divide-y divide-divider overflow-hidden rounded-2xl bg-white"
            >
                <FmCardRow
                    v-for="card in found"
                    :key="`${card.type}-${card.id}`"
                    :card="card"
                />
            </div>
            <p v-else class="m-0 text-[15px] text-ink-soft">
                {{ $t('No card found.') }}
            </p>
        </div>

        <div
            v-else
            v-auto-animate
            class="grid gap-4 lg:grid-cols-[repeat(auto-fill,minmax(340px,1fr))] lg:gap-5"
        >
            <section
                v-for="area in areas"
                :key="area.area"
                class="flex flex-col overflow-hidden rounded-[20px] bg-white"
            >
                <div class="flex items-center gap-2 pr-4">
                    <Link
                        :href="show(area.area).url"
                        class="flex min-w-0 flex-1 items-center gap-3 p-4 text-inherit no-underline"
                    >
                        <span :class="tile(area.area)">
                            <FmIcon :icon="areaIcon(area.area)" :size="22" />
                        </span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span class="text-base font-bold">{{
                                area.label
                            }}</span>
                            <span class="flex flex-wrap gap-x-2 text-[13px]">
                                <span class="text-ink-soft">{{
                                    cardCountText(area.count)
                                }}</span>
                                <span
                                    v-if="area.overdue > 0"
                                    class="font-semibold text-overdue"
                                    >{{ overdueText(area.overdue) }}</span
                                >
                            </span>
                        </span>
                    </Link>
                    <FmButton
                        v-if="canCreate && area.count === 0"
                        variant="soft"
                        :href="newCardUrl(types, area.area)"
                        >{{ $t('Add') }}</FmButton
                    >
                </div>
                <div
                    v-if="preview(area).length > 0"
                    class="flex flex-col divide-y divide-divider border-t border-divider"
                >
                    <FmCardRow
                        v-for="card in preview(area)"
                        :key="`${card.type}-${card.id}`"
                        :card="card"
                    />
                </div>
            </section>
        </div>
    </div>
</template>
