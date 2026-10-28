<script setup lang="ts">
/**
 * *New card*: the card types by area, each with what it is for. Choosing one
 * opens the form of that type.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { areaOf, areaTile } from '@/lib/areas';
import { cardIcon } from '@/lib/cards';
import { cn } from '@/lib/utils';
import { areas } from '@/routes';
import { create } from '@/routes/cards';

const { types } = defineProps<{
    /** The card types to choose from, grouped by their area. */
    types: App.Data.CardTypeData[];
}>();

/** The types grouped by area, the areas in the order they first appear. */
const groups = computed(() => {
    const byArea = new Map<string, App.Data.CardTypeData[]>();

    for (const type of types) {
        byArea.set(type.area, [...(byArea.get(type.area) ?? []), type]);
    }

    return [...byArea].map(([area, ofArea]) => ({ area, types: ofArea }));
});

/**
 * The classes of the icon tile of a type, in the colour of its area.
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
    <Head :title="$t('New card')" />

    <div class="flex max-w-[1120px] flex-col gap-5">
        <FmTitle
            :title="$t('New card')"
            :subtitle="$t('What do you want to add?')"
            :back="areas().url"
            :back-label="$t('Areas')"
        />

        <section
            v-for="group in groups"
            :key="group.area"
            class="flex flex-col gap-2"
        >
            <h2 class="m-0 text-[17px] font-bold">
                {{ group.types[0]?.area_label }}
            </h2>
            <div
                class="grid gap-3 lg:grid-cols-[repeat(auto-fill,minmax(320px,1fr))]"
            >
                <Link
                    v-for="type in group.types"
                    :key="type.type"
                    :href="create({ query: { type: type.type } }).url"
                    class="flex items-center gap-3 rounded-2xl bg-white p-4 text-inherit no-underline"
                >
                    <span :class="tile(type.area)">
                        <FmIcon :icon="cardIcon(type.type)" :size="22" />
                    </span>
                    <span class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-base font-bold">{{
                            type.label
                        }}</span>
                        <span class="text-[13px] text-ink-soft">{{
                            type.description
                        }}</span>
                    </span>
                </Link>
            </div>
        </section>
    </div>
</template>
