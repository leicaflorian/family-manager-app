<script setup lang="ts">
/**
 * *Drawings and crafts*: the documents of the gallery category as square pictures, by upload year,
 * with the filters *All* and *Favourites* and one chip per child when there is more than one. A
 * picture opens the drawing: on the phone it covers the page (`screen`), on the computer it sits in
 * the column beside the grid (`panel`); the arrows and the heart work on both. The drawing open is
 * in the address (`drawing`), so a link opens it. *Add* opens the panel that takes a photo or PDF.
 * Without a gallery category the page says where to choose one (who can change the categories) or whom
 * to ask (the others);
 * with a gallery and no drawing the card invites to add the first.
 */
import { Head, router } from '@inertiajs/vue3';
import { Images, Plus } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import AddDrawingSheet from '@/components/documents/AddDrawingSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmChip from '@/components/fm/FmChip.vue';
import FmDrawingTile from '@/components/fm/FmDrawingTile.vue';
import FmDrawingViewer from '@/components/fm/FmDrawingViewer.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { usePageTrail } from '@/composables/usePageTrail';
import { useWideLayout } from '@/composables/useWideLayout';
import { index as categories } from '@/routes/categories';
import {
    favorite,
    gallery,
    index as documents,
    unfavorite,
} from '@/routes/documents';

const props = defineProps<{
    /** The drawings by upload year, the newest year first. */
    years: App.Data.DrawingYearData[];
    /** The people with at least one drawing, for the filter. */
    people: { id: number; name: string }[];
    /** The filters in use. */
    filters: { filter: string; person: number | null };
    /** The drawing open, if there is one. */
    selected: App.Data.DrawingData | null;
    /** How many drawings pass the filters. */
    total: number;
    /** The person can add a drawing. */
    canAdd: boolean;
    /** The person can change the categories, so can choose the gallery category. */
    canManageCategories: boolean;
    /** A category is shown as a gallery. */
    hasGallery: boolean;
    /** The choices of the panel *Add a drawing*. */
    addOptions: { people: { id: number; name: string }[] };
}>();

const wide = useWideLayout();

/** The panel *Add a drawing* is open. */
const adding = ref(false);

usePageTrail(() => [
    { label: trans('Documents'), href: documents().url },
    { label: trans('Drawings and crafts') },
]);

/** All the drawings in the order of the page, to move through them. */
const order = computed((): App.Data.DrawingData[] =>
    props.years.flatMap((year) => year.drawings),
);

/** The place of the drawing open, from 1; 0 when it is not among the listed ones (unfavourited under *Favourites*). */
const position = computed(
    (): number =>
        order.value.findIndex((drawing) => drawing.id === props.selected?.id) +
        1,
);

/** Something is filtered: an empty list means «nothing here», not «no gallery yet». */
const filtered = computed(
    (): boolean =>
        props.filters.filter === 'favorites' || props.filters.person !== null,
);

/** The sentence under the title: how many pictures, and of whom when it is one child. */
const subtitle = computed((): string => {
    const only =
        props.people.length === 1
            ? props.people[0]
            : props.people.find((person) => person.id === props.filters.person);

    return only
        ? transChoice(
              '{1} :count drawing or craft of :name|[2,*] :count drawings and crafts of :name',
              props.total,
              { name: only.name },
          )
        : transChoice(
              '{1} :count drawing or craft|[2,*] :count drawings and crafts',
              props.total,
          );
});

/**
 * The address of the gallery with some filters and, maybe, a drawing open.
 *
 * @param filter - `all` or `favorites`
 * @param person - the child, or none
 * @param drawing - the drawing open, or none
 * @returns the address
 */
function address(
    filter: string,
    person: number | null,
    drawing: number | null = null,
): string {
    return gallery({
        query: {
            ...(filter === 'favorites' ? { filter } : {}),
            ...(person === null ? {} : { person }),
            ...(drawing === null ? {} : { drawing }),
        },
    }).url;
}

/**
 * Opens a drawing, or closes the one open with `null`: only the open drawing is asked of the server.
 *
 * @param id - the drawing, or none
 */
function open(id: number | null): void {
    router.get(
        address(props.filters.filter, props.filters.person, id),
        {},
        {
            only: ['selected'],
            preserveState: true,
            preserveScroll: true,
        },
    );
}

/**
 * Opens the next or the previous drawing in the order of the page; nothing at the ends, or when
 * the drawing open is not among the listed ones.
 *
 * @param step - 1 for the next, -1 for the previous
 */
function move(step: 1 | -1): void {
    const target = order.value[position.value - 1 + step];

    if (position.value >= 1 && target) {
        open(target.id);
    }
}

/**
 * Sets or removes the heart of the drawing open.
 *
 * @param value - the value the favourite should take
 */
function setFavorite(value: boolean): void {
    if (!props.selected) {
        return;
    }

    const options = {
        only: ['years', 'selected', 'total'],
        preserveScroll: true,
        preserveState: true,
    };

    if (value) {
        router.put(favorite(props.selected.id).url, {}, options);
    } else {
        router.delete(unfavorite(props.selected.id).url, options);
    }
}
</script>

<template>
    <Head :title="$t('Drawings and crafts')" />

    <div class="flex flex-col gap-5">
        <FmTitle
            :title="$t('Drawings and crafts')"
            :subtitle="hasGallery && total > 0 ? subtitle : undefined"
        >
            <template v-if="canAdd && hasGallery" #actions>
                <FmButton :icon="Plus" @click="adding = true">{{
                    $t('Add')
                }}</FmButton>
            </template>
        </FmTitle>

        <FmEmpty
            v-if="!hasGallery"
            :icon="Images"
            :title="$t('The gallery is not set up yet')"
            :text="
                canManageCategories
                    ? $t(
                          'Choose the category to show as a gallery in Categories.',
                      )
                    : $t(
                          'Ask an admin of the family to choose the category of the gallery.',
                      )
            "
        >
            <FmButton v-if="canManageCategories" :href="categories().url">{{
                $t('Go to Categories')
            }}</FmButton>
        </FmEmpty>

        <FmEmpty
            v-else-if="total === 0 && !filtered"
            :icon="Images"
            :title="$t('No drawings yet')"
            :text="
                $t(
                    'Take a photo of a drawing or upload the PDF of a craft: you will find them here, by year.',
                )
            "
        >
            <FmButton v-if="canAdd" :icon="Plus" @click="adding = true">{{
                $t('Add a drawing')
            }}</FmButton>
        </FmEmpty>

        <template v-else>
            <div :class="wide ? 'flex items-start gap-6' : undefined">
                <div class="flex min-w-0 grow flex-col gap-5">
                    <nav :aria-label="$t('Show')" class="flex flex-wrap gap-2">
                        <FmChip
                            :label="$t('All')"
                            :pressed="filters.filter !== 'favorites'"
                            :href="address('all', filters.person)"
                            preserve-state
                            preserve-scroll
                        />
                        <FmChip
                            :label="$t('Favourites')"
                            :pressed="filters.filter === 'favorites'"
                            :href="address('favorites', filters.person)"
                            preserve-state
                            preserve-scroll
                        />
                        <template v-if="people.length > 1">
                            <FmChip
                                v-for="person in people"
                                :key="person.id"
                                :label="person.name"
                                :pressed="filters.person === person.id"
                                preserve-state
                                preserve-scroll
                                :href="
                                    address(
                                        filters.filter,
                                        filters.person === person.id
                                            ? null
                                            : person.id,
                                    )
                                "
                            />
                        </template>
                    </nav>

                    <p v-if="total === 0" class="m-0 text-body text-ink-soft">
                        {{ $t('No drawings here.') }}
                    </p>

                    <section
                        v-for="year in years"
                        :key="year.year"
                        :aria-label="String(year.year)"
                        class="flex flex-col gap-2"
                    >
                        <div class="mx-0.5 flex items-baseline justify-between">
                            <h2 class="m-0 text-sheet-title">
                                {{ year.year }}
                            </h2>
                            <span class="text-meta text-ink-soft">{{
                                $tChoice(
                                    '{1} :count drawing|[2,*] :count drawings',
                                    year.count,
                                )
                            }}</span>
                        </div>
                        <div
                            class="grid grid-cols-3 gap-1 wide:grid-cols-6 wide:gap-2"
                        >
                            <FmDrawingTile
                                v-for="drawing in year.drawings"
                                :key="drawing.id"
                                :drawing="drawing"
                                :selected="wide && selected?.id === drawing.id"
                                @open="open"
                            />
                        </div>
                    </section>
                </div>

                <div v-if="wide && selected" class="sticky top-4 shrink-0">
                    <FmDrawingViewer
                        :drawing="selected"
                        :position="position"
                        :total="total"
                        variant="panel"
                        @close="open(null)"
                        @previous="move(-1)"
                        @next="move(1)"
                        @favorite="setFavorite"
                    />
                </div>
            </div>
        </template>

        <FmDrawingViewer
            v-if="!wide && selected"
            :drawing="selected"
            :position="position"
            :total="total"
            variant="screen"
            @close="open(null)"
            @previous="move(-1)"
            @next="move(1)"
            @favorite="setFavorite"
        />

        <AddDrawingSheet v-model:open="adding" :people="addOptions.people" />
    </div>
</template>
