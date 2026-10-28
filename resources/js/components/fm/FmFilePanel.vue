<script setup lang="ts">
/**
 * The file of a document on grey, as the boards *Un documento* and *Modifica
 * documento* show it, with the proportions of an A4 sheet: the preview and,
 * under it, the name of the file. Used by the page of a document, by its panel
 * beside the list and by *Edit*.
 */
import { cn } from '@/lib/utils';
import FmFilePreview from './FmFilePreview.vue';

const { file, compact = false } = defineProps<{
    /** The file. */
    file: App.Data.FileData;
    /** Smaller corners and padding, inside the card of the panel. */
    compact?: boolean;
}>();
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
        <span class="text-sm break-all text-ink-soft">{{ file.name }}</span>
    </div>
</template>
