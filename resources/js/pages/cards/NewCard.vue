<script setup lang="ts">
/**
 * *New card*: the card types by area, each with what it is for. Choosing one
 * opens the form of that type. On top, as the boards *Nuova* and *Nuova (pc)*,
 * *Have a document? Start from it* leads to *Upload*, where the reading
 * proposes the card. Opened after an import with *Create the card*, the
 * documents travel to the form of the type chosen, already linked, and the
 * link to *Upload* is not shown.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ScanLine } from '@lucide/vue';
import { computed } from 'vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { areaOf, areaTile } from '@/lib/areas';
import { cardIcon } from '@/lib/cards';
import { cn } from '@/lib/utils';
import { areas, upload } from '@/routes';
import { create } from '@/routes/cards';

const { types, document = null } = defineProps<{
    /** The card types to choose from, grouped by their area. */
    types: App.Data.CardTypeData[];
    /** The ids of the documents to link to the new card (`1,2`), from an import; null without. */
    document?: string | null;
}>();

/**
 * Where a type leads: its form, with the documents to link.
 *
 * @param type - the code of the card type
 * @returns the address of the form
 */
function formUrl(type: string): string {
    return create({
        query: { card: type, ...(document === null ? {} : { document }) },
    }).url;
}

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
        'flex size-11 shrink-0 items-center justify-center rounded-lg',
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

        <Link
            v-if="document === null"
            :href="upload().url"
            class="flex max-w-[760px] items-center gap-3 rounded-card bg-action-soft px-4 py-3.5 text-action-dark no-underline"
        >
            <FmIcon :icon="ScanLine" :size="24" class="shrink-0" />
            <span class="flex flex-col gap-0.5">
                <span class="text-base font-bold">{{
                    $t('Have a document? Start from it')
                }}</span>
                <span class="text-sm text-ink-soft">{{
                    $t(
                        'Upload the bill, the policy or the booklet: we propose the card.',
                    )
                }}</span>
            </span>
        </Link>

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
                    :href="formUrl(type.type)"
                    class="flex items-center gap-3 rounded-card bg-card p-4 text-inherit no-underline"
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
