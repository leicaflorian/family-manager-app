<script setup lang="ts">
/**
 * The preview of a file: the image the server made of it (a PDF, a HEIC
 * photo), an image as it is, and the placeholder for the files without a
 * preview or whose image does not load. The image and the placeholder open
 * the file in another tab.
 */
import { computed, ref, watch } from 'vue';
import { previewKind } from '@/lib/files';
import { cn } from '@/lib/utils';
import placeholderUrl from '../../../images/document-placeholder.png';

const {
    url,
    mimeType,
    name,
    previewUrl,
    fill = false,
} = defineProps<{
    /** The address that opens the file. */
    url: string;
    /** The real type of the file. */
    mimeType: string;
    /** The original name. */
    name: string;
    /** The address of the preview image made by the server, `null` when the file has none. */
    previewUrl: string | null;
    /** Takes the height of the box around it instead of a fixed one. */
    fill?: boolean;
}>();

/** Whether the image did not load: the placeholder takes its place. */
const failed = ref(false);

/** Another file has its own image: a failure of the previous one does not count. */
watch(
    () => [url, previewUrl],
    () => {
        failed.value = false;
    },
);

const kind = computed(() => previewKind(mimeType, previewUrl));

/** The classes of the image, the same for the preview and the placeholder. */
const imageClass = computed(() =>
    cn('w-full object-contain', fill ? 'max-h-full' : 'max-h-[560px]'),
);

/** Remembers that the image did not load. */
function onError(): void {
    failed.value = true;
}
</script>

<template>
    <div
        :class="
            cn(
                'flex w-full items-center justify-center overflow-hidden rounded-[14px] border border-edge bg-white',
                fill ? 'min-h-0 flex-1' : 'min-h-[420px]',
            )
        "
    >
        <a
            v-if="kind === 'preview' && !failed"
            :href="url"
            target="_blank"
            rel="noopener"
            class="contents"
        >
            <img
                :src="previewUrl ?? undefined"
                :alt="name"
                :class="imageClass"
                @error="onError"
            />
        </a>
        <img
            v-else-if="kind === 'image' && !failed"
            :src="url"
            :alt="name"
            :class="imageClass"
            @error="onError"
        />
        <a
            v-else
            :href="url"
            target="_blank"
            rel="noopener"
            :aria-label="$t('Open the file')"
            class="contents"
        >
            <img :src="placeholderUrl" :alt="name" :class="imageClass" />
        </a>
    </div>
</template>
