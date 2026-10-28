<script setup lang="ts">
/**
 * The zone where the files of documents are dragged or chosen (PDF, JPG, PNG,
 * HEIC, up to 25 MB each). It does not send them: it gives them to the page,
 * which uploads them (*Upload*) or keeps them for later (*New card*).
 */
import { FolderOpen, Upload as UploadIcon } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { ref, useId } from 'vue';
import { DOCUMENT_FILE_TYPES } from '@/lib/files';
import FmIcon from './FmIcon.vue';

const {
    label = trans('Drag the files here, or choose them'),
    compact = false,
    processing = false,
} = defineProps<{
    /** What the zone asks for. */
    label?: string;
    /** A small zone, below a form, instead of the large one of an empty page. */
    compact?: boolean;
    /** The files are being sent: the zone says so. */
    processing?: boolean;
}>();

const emit = defineEmits<{
    /** The person chose or dropped files: at least one. */
    files: [files: File[]];
}>();

/** The id that ties the zone to its hidden input. */
const id = useId();

/** A file is being dragged over the zone. */
const dragging = ref(false);

/**
 * Gives the files, when there are any.
 *
 * @param files - the files, from the picker or from the drop
 */
function give(files: FileList | null | undefined): void {
    if (files && files.length > 0) {
        emit('files', Array.from(files));
    }
}

/**
 * Gives the files of the picker, and empties it so the same file can be chosen again.
 *
 * @param event - the change of the input
 */
function chosen(event: Event): void {
    const input = event.target as HTMLInputElement;
    give(input.files);
    input.value = '';
}

/**
 * Gives the dropped files.
 *
 * @param event - the drop on the zone
 */
function dropped(event: DragEvent): void {
    dragging.value = false;
    give(event.dataTransfer?.files);
}
</script>

<template>
    <label
        :for="id"
        class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-[20px] border-2 border-dashed bg-white text-center text-ink-soft transition-colors"
        :class="[
            dragging ? 'border-action bg-action-soft' : 'border-edge',
            compact ? 'p-5' : 'min-h-[320px] p-8',
        ]"
        @dragover.prevent="dragging = true"
        @dragleave="dragging = false"
        @drop.prevent="dropped"
    >
        <FmIcon
            :icon="compact ? FolderOpen : UploadIcon"
            :size="compact ? 22 : 40"
            class="text-action"
        />
        <span class="text-base font-semibold text-ink">{{ label }}</span>
        <span class="text-sm">{{
            $t('PDF, JPG, PNG or HEIC, up to 25 MB each')
        }}</span>
        <span v-if="processing" class="text-sm font-semibold text-action">{{
            $t('Uploading…')
        }}</span>
        <input
            :id="id"
            type="file"
            multiple
            :accept="DOCUMENT_FILE_TYPES"
            :aria-label="$t('Choose the files')"
            class="sr-only"
            @change="chosen"
        />
    </label>
</template>
