<script setup lang="ts">
/**
 * The documents of a card on grey, beside its form, as the file beside *Edit
 * the document*: the documents already linked (only to see them, they are
 * unlinked from the card page), the files chosen and not saved yet, and the
 * zone to add more. Nothing is sent here: the form sends the files with the
 * card, and *Upload* opens to file them.
 */
import { FileText, X } from '@lucide/vue';
import { useId } from 'vue';
import FmDocumentRow from './FmDocumentRow.vue';
import FmFileDrop from './FmFileDrop.vue';
import FmIcon from './FmIcon.vue';
import FmNotice from './FmNotice.vue';

const { documents = [], errors = [] } = defineProps<{
    /** The documents already linked to the card; none for a new card. */
    documents?: App.Data.DocumentData[];
    /** Why the server refused some of the files. */
    errors?: string[];
}>();

/** The files chosen and not saved yet. */
const files = defineModel<File[]>('files', { default: () => [] });

/** The id that ties the box to its title. */
const titleId = useId();

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
        <div
            v-if="documents.length > 0"
            v-auto-animate
            class="flex flex-col gap-1.5"
        >
            <FmDocumentRow
                v-for="document in documents"
                :key="document.id"
                :document="document"
            />
        </div>
        <p v-else-if="files.length === 0" class="m-0 text-sm text-ink-soft">
            {{ $t('No documents yet') }}
        </p>
        <FmNotice v-if="errors.length > 0" tone="warning">
            <p v-for="message in errors" :key="message" class="m-0">
                {{ message }}
            </p>
        </FmNotice>
        <ul
            v-if="files.length > 0"
            v-auto-animate
            class="m-0 flex list-none flex-col gap-1.5 p-0"
        >
            <li
                v-for="(file, index) in files"
                :key="`${index}-${file.name}`"
                class="flex items-center gap-2 rounded-xl bg-white px-3 py-2"
            >
                <FmIcon :icon="FileText" :size="18" />
                <span class="min-w-0 flex-1 text-sm break-all">{{
                    file.name
                }}</span>
                <button
                    type="button"
                    :aria-label="$t('Remove :name', { name: file.name })"
                    class="flex size-8 items-center justify-center rounded-lg text-ink-soft hover:bg-canvas"
                    @click="remove(index)"
                >
                    <FmIcon :icon="X" :size="16" />
                </button>
            </li>
        </ul>
        <FmFileDrop
            compact
            :label="$t('Its documents? Drag them here, or choose them')"
            @files="add"
        />
    </section>
</template>
