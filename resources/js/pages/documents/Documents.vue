<script setup lang="ts">
/**
 * *Documents*: the search, the filters (in the panel from below on the phone,
 * open on the left on the computer), the list, 30 at a time, and on the
 * computer the chosen document in the left column in place of the filters
 * (`FmFilterAside`), with *Back to the filters*. Without any document, the
 * card that invites to upload the first one.
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { FileText, Search } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { useListFilters } from '@/composables/useListFilters';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDocumentActions from '@/components/fm/FmDocumentActions.vue';
import FmDocumentDetail from '@/components/fm/FmDocumentDetail.vue';
import FmDocumentFilters from '@/components/fm/FmDocumentFilters.vue';
import FmFilterAside from '@/components/fm/FmFilterAside.vue';
import FmFilterPills from '@/components/fm/FmFilterPills.vue';
import FmFilterButton from '@/components/fm/FmFilterButton.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmPager from '@/components/fm/FmPager.vue';
import FmSearchField from '@/components/fm/FmSearchField.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmToSortLink from '@/components/fm/FmToSortLink.vue';
import { useToSortCount } from '@/composables/useToSortCount';
import { categoryOptions } from '@/lib/categories';
import type { DocumentFilterOptions, DocumentFilters } from '@/lib/documents';
import { filterQuery } from '@/lib/documents';
import { upload } from '@/routes';
import { index, show } from '@/routes/documents';

/** The files waiting in *To sort*, for the link beside the title. */
const toSortCount = useToSortCount();

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

const page = usePage();

/** Closes the document in the left column: the same list, filters and page, without the chosen document. */
function closeSelected(): void {
    const address = new URL(page.url, window.location.origin);
    address.searchParams.delete('document');

    router.get(
        address.pathname + address.search,
        {},
        { preserveState: true, preserveScroll: true },
    );
}

/** The panel of the filters, on the phone. */
const filtersOpen = ref(false);

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
const pills = computed(() => {
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

/** The search box, the visits with other filters, the pills and the layout. */
const {
    desktop,
    searchText,
    submitSearch,
    go,
    removePill,
    removeAll,
    filterCount,
    layout,
} = useListFilters<DocumentFilters>({
    filters: () => props.filters,
    pills: () => pills.value,
    url: (query) => index.url({ query: filterQuery(query as DocumentFilters) }),
});

/** How many documents pass the filters, said beside the pills (or above the list without filters). */
const count = computed(() =>
    transChoice(
        '{1} :count document, the most recent first|[2,*] :count documents, the most recent first',
        props.total,
        {
            count: String(props.total),
        },
    ),
);

/** The page of the list in the address, or null on the first. */
function currentPage(): string | null {
    return new URL(page.url, window.location.origin).searchParams.get('page');
}

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
              query: {
                  ...filterQuery(props.filters),
                  ...(currentPage() ? { page: currentPage() } : {}),
                  document: document.id,
              },
          })
        : show.url(document.id);
}
</script>

<template>
    <Head :title="$t('Documents')" />

    <div class="flex flex-col gap-4">
        <FmTitle :title="$t('Documents')">
            <template #actions>
                <FmToSortLink :count="toSortCount" />
            </template>
        </FmTitle>

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

        <div v-else :class="layout">
            <FmFilterAside
                v-if="desktop"
                :detail="selected !== null"
                :detail-label="selected?.title"
                @close="closeSelected"
            >
                <template #filters>
                    <FmDocumentFilters
                        :filters="filters"
                        :options="options"
                        @change="go"
                    />
                </template>
                <template #detail-actions>
                    <FmDocumentActions
                        v-if="selected"
                        :document="selected"
                        compact
                        menu-only
                    />
                </template>
                <template #detail>
                    <FmDocumentDetail
                        v-if="selected"
                        :document="selected"
                        compact
                        embedded
                    />
                </template>
            </FmFilterAside>

            <div class="flex min-w-0 flex-col gap-3.5">
                <div class="flex gap-2.5">
                    <form
                        role="search"
                        class="min-w-0 grow"
                        @submit.prevent="submitSearch"
                    >
                        <FmSearchField
                            v-model="searchText"
                            :label="$t('Search a document')"
                        />
                    </form>
                    <FmFilterButton
                        v-if="!desktop"
                        :count="filterCount"
                        :expanded="filtersOpen"
                        @click="filtersOpen = true"
                    />
                </div>

                <FmFilterPills
                    v-if="pills.length > 0"
                    :pills="pills"
                    :found="documents.length > 0 ? count : ''"
                    @remove="removePill"
                    @remove-all="removeAll"
                />

                <template v-if="documents.length > 0">
                    <span
                        v-if="pills.length === 0"
                        class="mx-1 text-body-sm text-ink-soft"
                        >{{ count }}</span
                    >
                    <FmCard list>
                        <FmDocumentRow
                            v-for="document in documents"
                            :key="document.id"
                            :document="document"
                            :href="rowHref(document)"
                            :preserve-scroll="desktop"
                            :current="selected?.id === document.id"
                            menu
                        />
                    </FmCard>
                    <FmPager :previous="previousPage" :next="nextPage" />
                </template>
                <FmEmpty
                    v-else
                    :icon="Search"
                    :title="$t('No document matches the filters')"
                    :text="$t('Remove a filter or change the search.')"
                />
            </div>
        </div>

        <FmBottomSheet
            v-if="!desktop"
            v-model:open="filtersOpen"
            :title="$t('Filters')"
        >
            <FmDocumentFilters
                :filters="filters"
                :options="options"
                @change="go"
            />
            <FmButton wide @click="filtersOpen = false">{{
                $t('Show the documents')
            }}</FmButton>
        </FmBottomSheet>
    </div>
</template>
