<script setup lang="ts">
/**
 * *Documents*: the search, the filters (in the panel from below on the phone,
 * open on the left on the computer), the list, 30 at a time, and on the
 * computer the chosen document on the right. Without any document, the card
 * that invites to upload the first one.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { FileText, ListFilter, Search } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import FmActiveFilter from '@/components/fm/FmActiveFilter.vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDocumentDetail from '@/components/fm/FmDocumentDetail.vue';
import FmDocumentFilters from '@/components/fm/FmDocumentFilters.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { categoryOptions } from '@/lib/categories';
import type { DocumentFilterOptions, DocumentFilters } from '@/lib/documents';
import { filterQuery } from '@/lib/documents';
import { upload } from '@/routes';
import { index, show } from '@/routes/documents';

const props = defineProps<{
    /** The documents of this page of the list. */
    documents: App.Data.DocumentData[];
    /** How many documents pass the filters, on all the pages. */
    total: number;
    /** The address of the next page, if there is one. */
    nextPage: string | null;
    /** The address of the previous page, if there is one. */
    previousPage: string | null;
    /** The filters in use. */
    filters: DocumentFilters;
    /** The family has at least one document, whatever the filters. */
    hasDocuments: boolean;
    /** The choices of the filters. */
    options: DocumentFilterOptions;
    /** The document chosen on the computer, shown on the right. */
    selected: App.Data.DocumentDetailData | null;
}>();

const desktop = useMediaQuery('(min-width: 1024px)');

/** The panel of the filters, on the phone. */
const filtersOpen = ref(false);

/** The text in the search box, sent with Enter. */
const search = ref(props.filters.search ?? '');
watch(
    () => props.filters.search,
    (value) => (search.value = value ?? ''),
);

/**
 * Reloads the list with other filters, keeping the page where it is.
 *
 * @param filters - the new filters
 */
function apply(filters: DocumentFilters): void {
    router.get(index().url, filterQuery(filters), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

/** Sends the search. */
function searchDocuments(): void {
    apply({ ...props.filters, search: search.value.trim() || null });
}

/** The label of a chosen option, or the value itself if it is not among the options. */
function labelOf(
    options: { value: string; label: string }[],
    value: number | string,
): string {
    return (
        options.find((option) => option.value === String(value))?.label ??
        String(value)
    );
}

/** The filters in use, each with its chip and what removing it leaves. */
const chips = computed(() => {
    const filters = props.filters;
    const list: {
        key: string;
        label: string;
        without: Partial<DocumentFilters>;
    }[] = [];

    if (filters.category !== null) {
        list.push({
            key: 'category',
            label: labelOf(
                categoryOptions(props.options.categories),
                filters.category,
            ),
            without: { category: null },
        });
    }

    if (filters.person !== null) {
        list.push({
            key: 'person',
            label: labelOf(props.options.people, filters.person),
            without: { person: null },
        });
    }

    if (filters.organization !== null) {
        list.push({
            key: 'organization',
            label: labelOf(props.options.organizations, filters.organization),
            without: { organization: null },
        });
    }

    if (filters.status) {
        list.push({
            key: 'status',
            label: labelOf(props.options.statuses, filters.status),
            without: { status: null },
        });
    }

    if (filters.expiring) {
        list.push({
            key: 'expiring',
            label: trans('Expiring'),
            without: { expiring: false },
        });
    }

    if (filters.search) {
        list.push({
            key: 'search',
            label: `«${filters.search}»`,
            without: { search: null },
        });
    }

    return list;
});

const count = computed(() =>
    transChoice(
        '{1} :count document, the most recent first|[2,*] :count documents, the most recent first',
        props.total,
        {
            count: String(props.total),
        },
    ),
);

/**
 * Where a row leads: on the computer the document on the right of the list,
 * on the phone its page.
 *
 * @param document - the document of the row
 * @returns the address
 */
function rowHref(document: App.Data.DocumentData): string {
    return desktop.value
        ? index.url({
              query: { ...filterQuery(props.filters), document: document.id },
          })
        : show.url(document.id);
}
</script>

<template>
    <Head :title="$t('Documents')" />

    <div class="flex flex-col gap-4">
        <FmTitle :title="$t('Documents')" />

        <FmEmpty
            v-if="!hasDocuments"
            :icon="FileText"
            :title="$t('No documents, for now')"
            :text="
                $t(
                    'Upload a bill, a payslip or the identity card: you will find them here, with their expiry.',
                )
            "
        >
            <FmButton :href="upload().url">{{
                $t('Upload the first document')
            }}</FmButton>
        </FmEmpty>

        <div
            v-else
            :class="[
                'flex flex-col gap-6 lg:grid lg:items-start',
                selected
                    ? 'lg:grid-cols-[16rem_minmax(0,3fr)_minmax(0,2fr)]'
                    : 'lg:grid-cols-[16rem_minmax(0,1fr)]',
            ]"
        >
            <aside class="hidden rounded-[20px] bg-white p-5 lg:block">
                <FmDocumentFilters
                    :filters="filters"
                    :options="options"
                    @change="apply"
                />
            </aside>

            <div class="flex min-w-0 flex-col gap-3.5">
                <div class="flex gap-2.5">
                    <form
                        role="search"
                        class="min-w-0 grow"
                        @submit.prevent="searchDocuments"
                    >
                        <label
                            class="flex h-11.5 items-center gap-2.5 rounded-[10px] border border-edge bg-white px-3.5 text-ink-soft"
                        >
                            <FmIcon :icon="Search" :size="20" />
                            <input
                                v-model="search"
                                type="search"
                                :placeholder="$t('Search a document')"
                                :aria-label="$t('Search a document')"
                                class="min-w-0 grow border-0 bg-transparent text-base text-ink outline-none"
                            />
                        </label>
                    </form>
                    <button
                        type="button"
                        :aria-label="$t('Filters')"
                        class="flex size-11.5 shrink-0 items-center justify-center rounded-[10px] border border-edge bg-white text-ink lg:hidden"
                        @click="filtersOpen = true"
                    >
                        <FmIcon :icon="ListFilter" :size="22" />
                    </button>
                </div>

                <div v-if="chips.length > 0" class="flex flex-wrap gap-2">
                    <FmActiveFilter
                        v-for="chip in chips"
                        :key="chip.key"
                        :label="chip.label"
                        @remove="apply({ ...filters, ...chip.without })"
                    />
                </div>

                <template v-if="documents.length > 0">
                    <span class="mx-1 text-sm text-ink-soft">{{ count }}</span>
                    <FmCard list>
                        <FmDocumentRow
                            v-for="document in documents"
                            :key="document.id"
                            :document="document"
                            :href="rowHref(document)"
                            :current="selected?.id === document.id"
                        />
                    </FmCard>
                    <nav
                        v-if="nextPage || previousPage"
                        :aria-label="$t('Pages')"
                        class="flex justify-between gap-3"
                    >
                        <Link
                            v-if="previousPage"
                            :href="previousPage"
                            class="font-semibold"
                            >{{ $t('Previous') }}</Link
                        >
                        <span v-else />
                        <Link
                            v-if="nextPage"
                            :href="nextPage"
                            class="font-semibold"
                            >{{ $t('Next') }}</Link
                        >
                    </nav>
                </template>
                <FmEmpty
                    v-else
                    :icon="Search"
                    :title="$t('No document matches the filters')"
                    :text="$t('Remove a filter or change the search.')"
                />
            </div>

            <section v-if="selected" class="hidden min-w-0 lg:block">
                <FmDocumentDetail :document="selected" compact />
            </section>
        </div>

        <FmBottomSheet v-model:open="filtersOpen" :title="$t('Filters')">
            <FmDocumentFilters
                :filters="filters"
                :options="options"
                @change="apply"
            />
            <FmButton wide @click="filtersOpen = false">{{
                $t('Show the documents')
            }}</FmButton>
        </FmBottomSheet>
    </div>
</template>
