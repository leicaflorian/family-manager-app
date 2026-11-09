<script setup lang="ts">
/**
 * The results shown under the search box while the person types: the groups
 * (cards, documents, expenses and income, due dates, organizations and people)
 * with their first rows, and at the bottom the link to all the results with
 * the Enter key. The box moves the choice with `move` and opens it with
 * `open` (arrow keys and Enter); the menu does not read the keyboard itself.
 * Every option has a stable id, and `activeId` is the chosen one, for
 * `aria-activedescendant` of the box.
 *
 * Rules: on a computer it sits under the box (`absolute`, 440 px, radius 14,
 * `shadow-menu`); on a phone, `inline`, it fills the white panel. The rows are
 * the compact `FmSearchResult`. The classes of the page win over its own
 * (`max-w-none` lets it be wider than the box on the Home).
 *
 * @group fields
 */
import { Link, router } from '@inertiajs/vue3';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, useAttrs, watch } from 'vue';
import type { ClassValue } from 'clsx';
import { cn } from '@/lib/utils';
import { search } from '@/routes';
import FmSearchResult from './FmSearchResult.vue';

// The classes of the page are merged with its own, so they can replace them
defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

const {
    results,
    query,
    inline = false,
} = defineProps<{
    /** The results, in groups. */
    results: App.Data.Search.SearchResultsData;
    /** What the person wrote. */
    query: string;
    /** On a phone: full width inside the white panel instead of floating. */
    inline?: boolean;
}>();

/**
 * The classes of the menu: its own, then the page's, which win. Called from
 * the template, so it follows the page at each render.
 *
 * @returns the merged classes
 */
function rootClass(): string {
    return cn(
        'flex flex-col bg-card p-1.5',
        inline
            ? 'w-full'
            : 'absolute top-13 right-0 z-10 w-110 max-w-full rounded-xl shadow-menu',
        attrs.class as ClassValue,
    );
}

/**
 * The other attributes of the page (id, listeners), without the classes.
 *
 * @returns the attributes for the menu
 */
function otherAttrs(): Record<string, unknown> {
    const { class: _class, ...rest } = attrs;

    return rest;
}

/** The rows of all the groups in the order they are shown. */
const rows = computed(() => results.groups.flatMap((g) => g.results));

/** The index of the chosen row; -1 when none. */
const current = ref(-1);

watch(
    () => results,
    () => {
        current.value = -1;
    },
);

/** Where *See all* leads. */
const allUrl = computed(() => search.url({ query: { q: query } }));

/**
 * The id of the option of a result, for `aria-activedescendant` of the box.
 *
 * @param key - the key of the result
 * @returns the id of its row
 */
function optionId(key: string): string {
    return `search-option-${key}`;
}

/** The id of the chosen option; undefined when none. */
const activeId = computed((): string | undefined => {
    const row = rows.value[current.value];

    return row === undefined ? undefined : optionId(row.key);
});

/**
 * Moves the choice by one row, without going out of the list.
 *
 * @param step - 1 for the next row, -1 for the previous one
 */
function move(step: 1 | -1): void {
    if (rows.value.length === 0) {
        return;
    }

    current.value = Math.min(
        rows.value.length - 1,
        Math.max(0, current.value + step),
    );
}

/**
 * Opens the chosen row.
 *
 * @returns false if no row is chosen
 */
function open(): boolean {
    const row = rows.value[current.value];

    if (row === undefined) {
        return false;
    }

    router.visit(row.href);

    return true;
}

/**
 * Whether a result is the chosen one.
 *
 * @param key - the key of the result
 * @returns true for the chosen row
 */
function isCurrent(key: string): boolean {
    return rows.value[current.value]?.key === key;
}

defineExpose({ move, open, activeId });
</script>

<template>
    <div v-bind="otherAttrs()" :class="rootClass()">
        <div
            role="listbox"
            :aria-label="trans('Results')"
            class="flex flex-col"
        >
            <div
                v-for="group in results.groups"
                :key="group.group"
                role="group"
                :aria-label="group.label"
                class="flex flex-col"
            >
                <span class="px-3 pt-2 pb-1 text-meta-strong text-ink-soft">{{
                    group.label
                }}</span>
                <FmSearchResult
                    v-for="result in group.results"
                    :key="result.key"
                    :result="result"
                    :query="query"
                    compact
                    :active="isCurrent(result.key)"
                    :id="optionId(result.key)"
                    role="option"
                    :aria-selected="isCurrent(result.key)"
                />
            </div>
        </div>
        <Link
            :href="allUrl"
            class="mt-1 flex min-h-11 items-center justify-between gap-3 border-t border-divider px-3 text-row text-ink no-underline"
            >{{
                transChoice(
                    '{1} See the result|[2,*] See all :count results',
                    results.total,
                    { count: String(results.total) },
                )
            }}
            <kbd
                class="rounded-sm bg-segment px-1.5 py-0.5 text-meta-strong text-ink-soft"
                >{{ trans('Enter') }}</kbd
            ></Link
        >
    </div>
</template>
