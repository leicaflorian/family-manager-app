<script setup lang="ts">
/**
 * *Drawings and crafts*: the documents of the gallery category as square pictures, by upload year,
 * with the filters *All* (no filter: it takes the others away) and *Favourites* and one chip per child when there is more than one (or
 * one child is filtered, so that her chip clears the filter). A
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
import { usePageMeta } from '@/composables/usePageMeta';
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

const { title, head } = usePageMeta({
    title: () => trans('Drawings and crafts'),
    parent: 'documents',
});

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

/** The child the gallery is filtered by, when she is among the children with drawings. */
const filteredPerson = computed(() =>
    props.people.find((person) => person.id === props.filters.person),
);

/**
 * The person chips show with more than one child, and whenever a child is filtered, so that the
 * filter can always be taken away by pressing her chip again.
 */
const personChips = computed(
    (): boolean =>
        props.people.length > 1 || filteredPerson.value !== undefined,
);

/**
 * The child kept by *Favourites*: the one filtered, unless she has no chip (no drawings left), when
 * *Favourites* takes the filter away. *All* always takes every filter away.
 */
const keptPerson = computed((): number | null =>
    filteredPerson.value === undefined ? null : props.filters.person,
);

/** The sentence under the title: how many pictures, and of whom when every one is of the same child. */
const subtitle = computed((): string => {
    const only = filteredPerson.value ?? props.people[0];
    const ofOne =
        only !== undefined &&
        order.value.length > 0 &&
        order.value.every((drawing) => drawing.person_id === only.id);

    return ofOne
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
 * @param replace - takes the place of the current entry of the history, so that Back does not step through drawings
 */
function open(id: number | null, replace = false): void {
    router.get(
        address(props.filters.filter, props.filters.person, id),
        {},
        {
            only: ['selected'],
            preserveState: true,
            preserveScroll: true,
            replace,
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
        open(target.id, true);
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
    <Head :title="head" />

    <div class="flex flex-col gap-5">
        <FmTitle
            :title="title"
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
                            :pressed="!filtered"
                            :href="address('all', null)"
                            preserve-state
                            preserve-scroll
                        />
                        <FmChip
                            :label="$t('Favourites')"
                            :pressed="filters.filter === 'favorites'"
                            :href="address('favorites', keptPerson)"
                            preserve-state
                            preserve-scroll
                        />
                        <template v-if="personChips">
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
                                @open="open($event)"
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

        <AddDrawingSheet
            v-model:open="adding"
            :people="addOptions.people"
            :person="filters.person"
        />
    </div>
</template>
