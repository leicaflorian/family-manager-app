<script setup lang="ts">
/**
 * The file of a document on grey, as the boards *Un documento* and *Modifica
 * documento* show it, with the proportions of an A4 sheet: the preview and,
 * under it, the name of the file. Used by the page of a document, by its panel
 * beside the list and by *Edit*, where *Change the file* follows the name.
 */
import { useForm } from '@inertiajs/vue3';
import { useTemplateRef } from 'vue';
import { DOCUMENT_FILE_TYPES } from '@/lib/files';
import { cn } from '@/lib/utils';
import FmFilePreview from './FmFilePreview.vue';

const {
    file,
    compact = false,
    changeUrl,
} = defineProps<{
    /** The file. */
    file: App.Data.FileData;
    /** Smaller corners and padding, inside the card of the panel. */
    compact?: boolean;
    /** Where the new file goes: with it, *Change the file* follows the name. */
    changeUrl?: string;
}>();

/** The new file, sent as soon as it is chosen. */
const newFile = useForm({ file: null as File | null });
const picker = useTemplateRef<HTMLInputElement>('picker');

/**
 * Sends the file just chosen. The state of the page stays, so what is
 * written in the form and not saved yet is not lost.
 */
function chosen(): void {
    newFile.file = picker.value?.files?.[0] ?? null;

    if (newFile.file !== null && changeUrl) {
        newFile.post(changeUrl, {
            preserveScroll: true,
            preserveState: true,
        });
    }

    if (picker.value) {
        picker.value.value = '';
    }
}
</script>

<template>
    <div
        :class="
            cn(
                'flex aspect-[210/297] w-full flex-col items-center gap-3.5 bg-[#e9edf3]',
                compact ? 'rounded-[14px] p-5' : 'rounded-3xl p-5 lg:p-7',
            )
        "
    >
        <FmFilePreview
            :url="file.url"
            :mime-type="file.mime_type"
            :name="file.name"
            fill
        />
        <span class="text-center text-sm break-all text-ink-soft">
            <span>{{ file.name }}</span>
            <template v-if="changeUrl">
                ·
                <button
                    type="button"
                    class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-action underline hover:text-action-dark disabled:cursor-wait"
                    :disabled="newFile.processing"
                    @click="picker?.click()"
                >
                    {{ $t('Change the file') }}
                </button>
                <input
                    ref="picker"
                    type="file"
                    :accept="DOCUMENT_FILE_TYPES"
                    :aria-label="$t('Change the file')"
                    class="sr-only"
                    @change="chosen"
                />
            </template>
        </span>
        <span
            v-if="newFile.errors.file"
            role="alert"
            class="text-[13px] text-overdue"
            >{{ newFile.errors.file }}</span
        >
    </div>
</template>
