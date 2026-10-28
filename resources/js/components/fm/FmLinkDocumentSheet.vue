<script setup lang="ts">
/**
 * *Link a document*: the panel with the documents of the family that can be
 * linked to a card, searched while typing, 15 at a time with the next ones
 * loaded at the end of the list, each with ↗ to open it in a new tab. It
 * only says which document was chosen: the page of the card links it at
 * once, its form when it is saved. Its props come from the page and are
 * reloaded without the rest of it, keeping the address.
 */
import { router, WhenVisible } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import { documentSubtitle } from '@/lib/documents';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';
import FmRow from './FmRow.vue';
import FmSearchField from './FmSearchField.vue';

const {
    linkable,
    linkableNext = null,
    linkableSearch = '',
    hidden = [],
} = defineProps<{
    /** The pages loaded so far of the documents that can be linked and match the search. */
    linkable: App.Data.DocumentData[];
    /** The number of the next page of `linkable`, null at the last one. */
    linkableNext?: number | null;
    /** The search that `linkable` answers. */
    linkableSearch?: string;
    /** The ids of the documents not to offer, as already chosen. */
    hidden?: number[];
}>();

const emit = defineEmits<{
    /** A document was chosen. */
    pick: [document: App.Data.DocumentData];
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

/** The props of the panel, reloaded without the rest of the page. */
const LINKABLE_PROPS = ['linkable', 'linkableNext', 'linkableSearch'];

/** The text in the search box. */
const search = ref(linkableSearch);

/** The documents offered: those loaded, without the hidden ones. */
const shown = computed(() =>
    linkable.filter((document) => !hidden.includes(document.id)),
);

// A new search starts again from the first page, instead of adding below.
// The address stays that of the page: opened again, it starts from the top.
watchDebounced(
    search,
    (text) =>
        router.reload({
            data: { link_search: text.trim(), link_page: 1 },
            only: LINKABLE_PROPS,
            reset: ['linkable'],
            preserveUrl: true,
        }),
    { debounce: 300 },
);

/**
 * Gives the document chosen and closes the panel.
 *
 * @param document - the document chosen
 */
function pick(document: App.Data.DocumentData): void {
    emit('pick', document);
    open.value = false;
}
</script>

<template>
    <FmBottomSheet v-model:open="open" :title="$t('Link a document')">
        <div class="flex h-[min(600px,70dvh)] min-h-0 flex-col gap-3.5">
            <FmSearchField v-model="search" :label="$t('Search a document')" />
            <div
                v-if="shown.length > 0 || linkableNext"
                class="flex min-h-0 flex-col divide-y divide-divider overflow-y-auto rounded-2xl border border-divider"
            >
                <div
                    v-for="document in shown"
                    :key="document.id"
                    class="flex items-center"
                >
                    <FmRow
                        button
                        :arrow="false"
                        :title="document.title"
                        :subtitle="documentSubtitle(document) || undefined"
                        @click="pick(document)"
                    />
                    <FmButton
                        class="mr-1.5"
                        variant="text"
                        :icon="ExternalLink"
                        :label="
                            $t('Open :title in a new tab', {
                                title: document.title,
                            })
                        "
                        small
                        plain
                        :href="document.file_url"
                        external
                    />
                </div>
                <WhenVisible
                    v-if="linkableNext"
                    :key="linkableNext"
                    always
                    :params="{
                        data: {
                            link_search: linkableSearch,
                            link_page: linkableNext,
                        },
                        only: LINKABLE_PROPS,
                        preserveUrl: true,
                    }"
                />
            </div>
            <p v-else class="m-0 p-5 text-[15px] text-ink-soft">
                {{ $t('No document found.') }}
            </p>
        </div>
    </FmBottomSheet>
</template>
