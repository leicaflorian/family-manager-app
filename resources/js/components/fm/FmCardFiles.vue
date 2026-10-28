<script setup lang="ts">
/**
 * The documents of a card on grey, beside its form, as the file beside *Edit
 * the document*: at the top the zone to add files, then the files chosen and
 * not saved yet, then the documents already linked, with the buttons of the
 * card page. Every row opens in a new tab, so the form is not left. Nothing is
 * sent here: the form sends the files with the card, and *Upload* opens to
 * file them.
 */
import { X } from '@lucide/vue';
import { onBeforeUnmount, useId, watch } from 'vue';
import { show } from '@/routes/documents';
import FmCardDocumentActions from './FmCardDocumentActions.vue';
import FmDocumentRow from './FmDocumentRow.vue';
import FmFileDrop from './FmFileDrop.vue';
import FmFileTile from './FmFileTile.vue';
import FmIcon from './FmIcon.vue';
import FmNotice from './FmNotice.vue';
import FmRow from './FmRow.vue';

const {
    documents = [],
    card,
    canDelete = false,
    errors = [],
} = defineProps<{
    /** The documents already linked to the card; none for a new card. */
    documents?: App.Data.DocumentData[];
    /** The address of the card in the routes; none for a new card. */
    card?: { type: string; id: number };
    /** Whether whoever edits may delete the linked documents. */
    canDelete?: boolean;
    /** Why the server refused some of the files. */
    errors?: string[];
}>();

/** The files chosen and not saved yet. */
const files = defineModel<File[]>('files', { default: () => [] });

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
</script>

<template>
    <section
        class="flex flex-col gap-3 rounded-3xl bg-[#e9edf3] p-5 lg:p-7"
        :aria-labelledby="titleId"
    >
        <h2 :id="titleId" class="m-0 text-base font-bold">
            {{ $t('Documents') }}
        </h2>
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
                >
                    <template #before>
                        <FmFileTile />
                    </template>
                </FmRow>
                <button
                    type="button"
                    :aria-label="$t('Remove :name', { name: file.name })"
                    class="mr-3 flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-[10px] border-0 bg-transparent text-ink-soft hover:bg-action-soft"
                    @click="remove(index)"
                >
                    <FmIcon :icon="X" :size="18" />
                </button>
            </li>
        </ul>
        <div
            v-if="documents.length > 0"
            v-auto-animate
            class="flex flex-col divide-y divide-divider overflow-hidden rounded-2xl bg-white"
        >
            <div
                v-for="document in documents"
                :key="document.id"
                class="flex items-center"
            >
                <FmDocumentRow
                    class="min-w-0 grow"
                    :document="document"
                    :href="show.url(document.id)"
                    external
                    dateless
                />
                <FmCardDocumentActions
                    v-if="card"
                    :document="document"
                    :card="card"
                    :can-delete="canDelete"
                />
            </div>
        </div>
        <p v-else-if="files.length === 0" class="m-0 text-sm text-ink-soft">
            {{ $t('No documents yet') }}
        </p>
    </section>
</template>
