<script setup lang="ts">
/**
 * *Search*, the page of the results of the global search (`/search?q=…`): the
 * box with the text, the chips of the groups with their numbers (*All results*
 * and one per group that found something) and the groups one after the other,
 * or only the one of the chip pressed. While the person types a new text, on the
 * phone the first results open in place of the results; Enter, or *See all*,
 * searches the text on this page. A search that finds nothing says so with
 * three tips; at the bottom, always, what is searched.
 *
 * On the phone the page has its own box (with the arrow back beside it, and the
 * title only for screen readers); on the tablet and the computer the box and
 * its menu are in the top bar, which shows the text, and the link to the Home
 * and the title are above the results.
 */
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, SearchX } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, useTemplateRef } from 'vue';
import FmChip from '@/components/fm/FmChip.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmSearchMenu from '@/components/fm/FmSearchMenu.vue';
import FmSearchResult from '@/components/fm/FmSearchResult.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import {
    SEARCH_MENU_ID,
    SEARCH_PAGE_FIELD,
    useSearchBox,
} from '@/composables/useSearchBox';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { dashboard, search } from '@/routes';

const { results } = defineProps<{
    /** What was searched and what was found, in groups. */
    results: App.Data.Search.SearchResultsData;
}>();

/** One piece of a tip: plain text, or an example in bold or in italics. */
type TipPart = { text: string; tag?: 'strong' | 'em' };

const mode = useLayoutMode();

/** The phone: the page has its own box and menu; the tablet and the computer have them in the top bar. */
const phone = computed((): boolean => mode.value === 'phone');

/** The text in the box. */
const text = ref(results.query);

const menu = useTemplateRef<InstanceType<typeof FmSearchMenu>>('menu');

const { onFocusIn, onFocusOut, menuResults, enter, onKeydown, closeMenu } =
    useSearchBox(text, menu, submit);

/** On the phone the menu takes the place of the chips and of the results. */
const inlineMenu = computed(
    (): boolean => phone.value && menuResults.value !== null,
);

/** The groups listed: all of them, or only the one of the chip pressed. */
const groups = computed((): App.Data.Search.SearchGroupData[] =>
    results.type === null
        ? results.groups
        : results.groups.filter((group) => group.group === results.type),
);

/** The title of the page: the number of results, or just *Search* when none is listed. */
const title = computed((): string =>
    groups.value.length > 0
        ? transChoice(
              '{1} :count result for «:query»|[2,*] :count results for «:query»',
              results.total,
              { query: results.query },
          )
        : trans('Search'),
);

/** Something was searched and nothing is listed. */
const nothingFound = computed(
    (): boolean => results.query !== '' && groups.value.length === 0,
);

/** The tips of a search that found nothing, with their examples. */
const tips = computed((): TipPart[][] => [
    tip('a part of the word: :part also finds :word', {
        part: { text: 'Pand', tag: 'strong' },
        word: { text: 'Panda', tag: 'em' },
    }),
    tip('the name of who sent it: :first, :second', {
        first: { text: 'UnipolSai', tag: 'strong' },
        second: { text: 'Enel', tag: 'strong' },
    }),
    tip('an amount or a date: :amount, :date', {
        amount: { text: '84,20', tag: 'strong' },
        date: { text: '15/10/2026', tag: 'strong' },
    }),
]);

/**
 * Translates a tip and puts its examples in place of the placeholders, so the
 * sentence is translated whole and only the examples are marked.
 *
 * @param key - the English text, with a placeholder per example
 * @param examples - the examples by placeholder name
 * @returns the pieces of the tip in order
 */
function tip(key: string, examples: Record<string, TipPart>): TipPart[] {
    return trans(key)
        .split(/(:[a-z]+)/)
        .filter((piece) => piece !== '')
        .map((piece) => examples[piece.slice(1)] ?? { text: piece });
}

/** Searches the text in the box on this page. */
function submit(): void {
    const q = text.value.trim();

    router.get(search.url(), q === '' ? {} : { q }, { preserveState: false });
}

/**
 * Shows the results of one group, or of all of them.
 *
 * @param type - the group, `null` for all
 */
function filter(type: App.Enums.SearchGroup | null): void {
    router.get(
        search.url(),
        type === null ? { q: results.query } : { q: results.query, type },
        // A filter is not a new place: Back leaves the search, not the filter
        { preserveState: false, replace: true },
    );
}

/** The arrow of the phone: back to the page before, or to the Home if there is none. */
function goBack(): void {
    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(dashboard().url);
}
</script>

<template>
    <Head :title="$t('Search')" />

    <div
        class="flex w-full flex-col"
        :class="phone ? 'gap-5' : 'mx-auto max-w-220 gap-6'"
    >
        <FmTitle
            v-if="!phone"
            :title="title"
            :back="dashboard().url"
            :back-label="$t('Home')"
        />
        <h1 v-else class="sr-only">{{ title }}</h1>

        <div class="flex flex-col" :class="phone ? 'gap-3' : 'gap-6'">
            <div
                v-if="phone"
                class="relative flex items-center gap-2"
                @focusin="onFocusIn"
                @focusout="onFocusOut"
            >
                <button
                    type="button"
                    :aria-label="$t('Back')"
                    class="-ml-2.5 flex size-11 shrink-0 items-center justify-center rounded-full text-ink"
                    @click="goBack"
                >
                    <FmIcon :icon="ArrowLeft" :size="24" />
                </button>
                <FmSearchField
                    v-model="text"
                    :input-id="SEARCH_PAGE_FIELD"
                    :label="$t('Search in Family Manager')"
                    clearable
                    autofocus
                    :controls="SEARCH_MENU_ID"
                    :expanded="menuResults !== null"
                    class="min-w-0 grow"
                    :active-descendant="
                        menuResults ? menu?.activeId : undefined
                    "
                    @submit="enter"
                    @escape="closeMenu"
                    @keydown="onKeydown"
                />
            </div>

            <div
                v-if="results.total > 0 && !inlineMenu && !nothingFound"
                role="group"
                :aria-label="$t('Filters')"
                class="flex gap-2"
                :class="
                    phone
                        ? '-mx-(--main-gutter) overflow-x-auto px-(--main-gutter)'
                        : 'flex-wrap'
                "
            >
                <FmChip
                    :label="$t('All results')"
                    :count="results.total"
                    :pressed="results.type === null"
                    @click="filter(null)"
                />
                <FmChip
                    v-for="group in results.groups"
                    :key="group.group"
                    :label="group.label"
                    :count="group.count"
                    :pressed="results.type === group.group"
                    @click="filter(group.group)"
                />
            </div>
        </div>

        <div
            v-if="inlineMenu && menuResults"
            class="overflow-hidden rounded-card bg-card"
        >
            <FmSearchMenu
                :id="SEARCH_MENU_ID"
                ref="menu"
                :results="menuResults"
                :query="menuResults.query"
                inline
                @mousedown.prevent
            />
        </div>
        <template v-else>
            <FmEmpty
                v-if="nothingFound"
                :icon="SearchX"
                :title="$t('No results for «:query»', { query: results.query })"
                :text="
                    $t(
                        'Check how it is written. If you do not remember it well, try:',
                    )
                "
            >
                <ul
                    class="m-0 flex list-none flex-col gap-2 p-0 text-left text-body text-ink"
                >
                    <li
                        v-for="(parts, i) in tips"
                        :key="i"
                        class="flex items-baseline gap-2.5"
                    >
                        <span aria-hidden="true" class="shrink-0 text-action"
                            >•</span
                        >
                        <span
                            ><template v-for="(part, j) in parts" :key="j"
                                ><strong v-if="part.tag === 'strong'">{{
                                    part.text
                                }}</strong
                                ><em v-else-if="part.tag === 'em'">{{
                                    part.text
                                }}</em
                                ><template v-else>{{
                                    part.text
                                }}</template></template
                            ></span
                        >
                    </li>
                </ul>
            </FmEmpty>

            <section
                v-for="group in groups"
                :key="group.group"
                :aria-label="group.label"
                class="flex flex-col gap-2"
            >
                <h2 class="m-0 flex items-baseline gap-2 text-section">
                    {{ group.label }}
                    <span class="text-label text-ink-soft">{{
                        group.count
                    }}</span>
                </h2>
                <div
                    class="flex flex-col divide-y divide-divider overflow-hidden rounded-card bg-card"
                >
                    <FmSearchResult
                        v-for="result in group.results"
                        :key="result.key"
                        :result="result"
                        :query="results.query"
                    />
                </div>
            </section>
        </template>

        <p class="m-0 text-body-sm text-ink-soft">
            {{
                $t(
                    'I search in the names of the cards, in the titles, labels and read text of the documents, in the expenses, in the organizations and in the people.',
                )
            }}
        </p>
    </div>
</template>
