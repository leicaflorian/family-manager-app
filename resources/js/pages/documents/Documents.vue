<script setup lang="ts">
/**
 * *Documents*: the search, the filters (in the panel from below on the phone,
 * in a box under the search on the wide layout), the list, 30 at a time, and
 * on the wide layout the chosen document in the column on the right
 * (`FmDetailColumn`), with ‹ › to walk the list. Without any document, the
 * card that invites to upload the first one.
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import { FileText, Search } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { usePageMeta } from '@/composables/usePageMeta';
import { useListFilters } from '@/composables/useListFilters';
import GalleryCard from '@/components/documents/GalleryCard.vue';
import FmBottomSheet from '@/components/fm/FmBottomSheet.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDocumentActions from '@/components/fm/FmDocumentActions.vue';
import FmDocumentDetail from '@/components/fm/FmDocumentDetail.vue';
import FmDocumentFilters from '@/components/fm/FmDocumentFilters.vue';
import FmDetailColumn from '@/components/fm/FmDetailColumn.vue';
import FmFilterBox from '@/components/fm/FmFilterBox.vue';
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
    /** The card of the drawings gallery, null when no category is shown in a gallery. */
    gallery: App.Data.GalleryTeaserData | null;
}>();

const page = usePage();

/** Closes the document in the column on the right: the same list, filters and page, without the chosen document. */
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

/** The search box, the visits with other filters, the pills and whether the filter box is open. */
const {
    wide,
    boxOpen,
    searchText,
    submitSearch,
    go,
    removePill,
    removeAll,
    filterCount,
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

/** The place of the chosen document in this page of the list (from 1), or null when it is not there. */
const position = computed(() => {
    const index = props.documents.findIndex(
        (document) => document.id === props.selected?.id,
    );

    return index === -1 ? null : index + 1;
});

/**
 * Opens the document next to the chosen one in the list, keeping the scroll.
 *
 * @param step - -1 for the one before, 1 for the one after
 */
function walk(step: -1 | 1): void {
    if (position.value === null) {
        return;
    }

    const neighbour = props.documents[position.value - 1 + step];

    if (neighbour) {
        router.visit(rowHref(neighbour), { preserveScroll: true });
    }
}

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
    return wide.value
        ? index.url({
              query: {
                  ...filterQuery(props.filters),
                  ...(currentPage() ? { page: currentPage() } : {}),
                  document: document.id,
              },
          })
        : show.url(document.id);
}

const { title, head } = usePageMeta({
    title: () => trans('Documents'),
});
</script>

<template>
    <Head :title="head" />

    <div class="flex flex-col gap-4">
        <FmTitle :title="title">
            <template #actions>
                <FmToSortLink :count="toSortCount" />
            </template>
        </FmTitle>

        <GalleryCard v-if="!hasDocuments && gallery" :gallery="gallery" />

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

        <template v-else>
            <div
                :class="
                    wide && selected
                        ? 'wide:grid wide:grid-cols-[minmax(0,1fr)_380px] wide:gap-5 computer:grid-cols-[minmax(0,1fr)_400px] computer:gap-7'
                        : ''
                "
            >
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
                            v-if="wide"
                            labelled
                            controls="document-filters"
                            :count="filterCount"
                            :expanded="boxOpen"
                            @click="boxOpen = !boxOpen"
                        />
                        <FmFilterButton
                            v-else
                            :count="filterCount"
                            :expanded="filtersOpen"
                            @click="filtersOpen = true"
                        />
                    </div>

                    <FmFilterBox v-if="wide && boxOpen" id="document-filters">
                        <FmDocumentFilters
                            columns
                            :filters="filters"
                            :options="options"
                            @change="go"
                        />
                    </FmFilterBox>

                    <FmFilterPills
                        v-if="pills.length > 0"
                        :pills="pills"
                        :found="documents.length > 0 ? count : ''"
                        @remove="removePill"
                        @remove-all="removeAll"
                    />

                    <GalleryCard v-if="gallery" :gallery="gallery" />

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
                                :preserve-scroll="wide"
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

                <FmDetailColumn
                    v-if="wide && selected"
                    :label="selected.title"
                    :position="position"
                    :total="documents.length"
                    :previous-label="$t('Previous document')"
                    :next-label="$t('Next document')"
                    @previous="walk(-1)"
                    @next="walk(1)"
                    @close="closeSelected"
                >
                    <template #actions>
                        <FmDocumentActions
                            :document="selected"
                            compact
                            menu-only
                        />
                    </template>
                    <FmDocumentDetail :document="selected" compact embedded />
                </FmDetailColumn>
            </div>
        </template>

        <FmBottomSheet
            v-if="!wide"
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
