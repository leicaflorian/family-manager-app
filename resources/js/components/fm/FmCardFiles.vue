<script setup lang="ts">
/**
 * The documents of a card on grey, beside its form, as the file beside *Edit
 * the document*: *Link* beside the title, at the top the zone to add files,
 * then the files chosen and not saved yet, then the documents: those chosen
 * in *Link*, and those already linked, with the menu of the card page. Every
 * row opens its file in a new tab, so the form is not left. Nothing is sent
 * here: the form sends the files with the card, and *Upload* opens to file
 * them; it links the documents chosen and unlinks those marked when saved,
 * so until then a choice can be taken back.
 */
import { Link2, X } from '@lucide/vue';
import { onBeforeUnmount, ref, useId, watch } from 'vue';
import { cn } from '@/lib/utils';
import FmButton from './FmButton.vue';
import FmCardDocumentActions from './FmCardDocumentActions.vue';
import FmDocumentRow from './FmDocumentRow.vue';
import FmFileDrop from './FmFileDrop.vue';
import FmFileTile from './FmFileTile.vue';
import FmIcon from './FmIcon.vue';
import FmLinkDocumentSheet from './FmLinkDocumentSheet.vue';
import FmNotice from './FmNotice.vue';
import FmRow from './FmRow.vue';

const {
    documents = [],
    card,
    canDelete = false,
    errors = [],
    linkable = [],
    linkableNext = null,
    linkableSearch = '',
    hasLinkable = false,
} = defineProps<{
    /** The documents already linked to the card; none for a new card. */
    documents?: App.Data.DocumentData[];
    /** The address of the card in the routes; none for a new card. */
    card?: { type: string; id: number };
    /** Whether whoever edits may delete the linked documents. */
    canDelete?: boolean;
    /** Why the server refused some of the files. */
    errors?: string[];
    /** The pages loaded so far of the documents that can be linked, for *Link*. */
    linkable?: App.Data.DocumentData[];
    /** The number of the next page of `linkable`, null at the last one. */
    linkableNext?: number | null;
    /** The search that `linkable` answers. */
    linkableSearch?: string;
    /** At least one document of the family can be linked. */
    hasLinkable?: boolean;
}>();

/** The files chosen and not saved yet. */
const files = defineModel<File[]>('files', { default: () => [] });

/** The documents chosen in *Link*, linked when the card is saved. */
const link = defineModel<App.Data.DocumentData[]>('link', {
    default: () => [],
});

/** The ids of the linked documents to unlink when the card is saved. */
const unlink = defineModel<number[]>('unlink', { default: () => [] });

/** The panel *Link a document* is open. */
const linking = ref(false);

/** The look of the ✕ and of *Undo* at the end of a row waiting for the save. */
const sideButton = cn(
    'mr-3 flex h-10 min-w-10 shrink-0 cursor-pointer items-center justify-center rounded-[10px] border-0 bg-transparent px-2 text-sm text-ink-soft hover:bg-action-soft',
);

/** The id that ties the box to its title. */
const titleId = useId();

/** The object URL of each chosen file, to open it before it is saved. */
const urls = new Map<File, string>();

/**
 * The address that opens a chosen file, made the first time it is asked.
 *
 * @param file - a chosen file
 * @returns its object URL
 */
function urlOf(file: File): string {
    const known = urls.get(file);

    if (known !== undefined) {
        return known;
    }

    const url = URL.createObjectURL(file);
    urls.set(file, url);

    return url;
}

// A file taken off the list lets go of its URL, which holds it in memory
watch(files, (current) => {
    for (const [file, url] of urls) {
        if (!current.includes(file)) {
            URL.revokeObjectURL(url);
            urls.delete(file);
        }
    }
});

onBeforeUnmount(() => {
    urls.forEach((url) => URL.revokeObjectURL(url));
    urls.clear();
});

/**
 * Adds the chosen files to the ones already there.
 *
 * @param chosen - the files given by the zone
 */
function add(chosen: File[]): void {
    files.value = [...files.value, ...chosen];
}

/**
 * Takes a file off the list.
 *
 * @param index - its place in the list
 */
function remove(index: number): void {
    files.value = files.value.filter((_, at) => at !== index);
}

/**
 * Takes a document chosen in *Link* off the list.
 *
 * @param id - the id of the document
 */
function removeLink(id: number): void {
    link.value = link.value.filter((document) => document.id !== id);
}

/**
 * Keeps a linked document linked: it is no longer unlinked when saved.
 *
 * @param id - the id of the document
 */
function keep(id: number): void {
    unlink.value = unlink.value.filter((unlinked) => unlinked !== id);
}
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-3xl bg-[#e9edf3] p-5 lg:p-7"
        :aria-labelledby="titleId"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 :id="titleId" class="m-0 text-base font-bold">
                {{ $t('Documents') }}
            </h2>
            <FmButton
                v-if="hasLinkable"
                variant="text"
                inline
                :icon="Link2"
                @click="linking = true"
                >{{ $t('Link document') }}</FmButton
            >
        </div>
        <FmFileDrop
            compact
            :label="$t('Its documents? Drag them here, or choose them')"
            @files="add"
        />
        <FmNotice v-if="errors.length > 0" tone="warning">
            <p v-for="message in errors" :key="message" class="m-0">
                {{ message }}
            </p>
        </FmNotice>
        <ul
            v-if="files.length > 0"
            v-auto-animate
            class="m-0 flex list-none flex-col divide-y divide-divider overflow-hidden rounded-2xl bg-white p-0"
        >
            <li
                v-for="(file, index) in files"
                :key="`${index}-${file.name}`"
                class="flex items-center"
            >
                <FmRow
                    class="min-w-0 grow"
                    :title="file.name"
                    :subtitle="$t('To file after saving')"
                    :href="urlOf(file)"
                    external
                    :arrow="false"
                >
                    <template #before>
                        <FmFileTile />
                    </template>
                </FmRow>
                <button
                    type="button"
                    :aria-label="$t('Remove :name', { name: file.name })"
                    :class="sideButton"
                    @click="remove(index)"
                >
                    <FmIcon :icon="X" :size="18" />
                </button>
            </li>
        </ul>
        <div
            v-if="link.length > 0 || documents.length > 0"
            v-auto-animate
            class="flex flex-col divide-y divide-divider overflow-hidden rounded-2xl bg-white"
        >
            <div
                v-for="document in link"
                :key="`link-${document.id}`"
                class="flex items-center"
            >
                <FmRow
                    class="min-w-0 grow"
                    :title="document.title"
                    :subtitle="$t('Linked when you save')"
                    :href="document.file_url"
                    external
                    :arrow="false"
                >
                    <template #before>
                        <FmFileTile />
                    </template>
                </FmRow>
                <button
                    type="button"
                    :aria-label="$t('Remove :name', { name: document.title })"
                    :class="sideButton"
                    @click="removeLink(document.id)"
                >
                    <FmIcon :icon="X" :size="18" />
                </button>
            </div>
            <div
                v-for="document in documents"
                :key="document.id"
                class="flex items-center"
            >
                <template v-if="unlink.includes(document.id)">
                    <FmRow
                        class="min-w-0 grow opacity-50"
                        :title="document.title"
                        :subtitle="$t('Unlinked when you save')"
                        :href="document.file_url"
                        external
                        :arrow="false"
                    >
                        <template #before>
                            <FmFileTile />
                        </template>
                    </FmRow>
                    <button
                        type="button"
                        :aria-label="
                            $t('Keep :title linked', { title: document.title })
                        "
                        :class="sideButton"
                        @click="keep(document.id)"
                    >
                        {{ $t('Undo') }}
                    </button>
                </template>
                <template v-else>
                    <FmDocumentRow
                        class="min-w-0 grow"
                        :document="document"
                        :href="document.file_url"
                        external
                        dateless
                    />
                    <FmCardDocumentActions
                        v-if="card"
                        :document="document"
                        :card="card"
                        :can-delete="canDelete"
                        in-form
                        @unlink="unlink = [...unlink, document.id]"
                    />
                </template>
            </div>
        </div>
        <p v-else-if="files.length === 0" class="m-0 text-sm text-ink-soft">
            {{ $t('No documents yet') }}
        </p>
        <FmLinkDocumentSheet
            v-model:open="linking"
            :linkable="linkable"
            :linkable-next="linkableNext"
            :linkable-search="linkableSearch"
            :hidden="link.map((document) => document.id)"
            @pick="link = [...link, $event]"
        />
    </section>
</template>
