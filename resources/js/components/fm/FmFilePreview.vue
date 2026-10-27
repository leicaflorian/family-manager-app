<script setup lang="ts">
/**
 * The preview of a file: a PDF in the page, an image as it is, and for the
 * other types (HEIC, which browsers do not open) the name with a link.
 */
import { FileText } from '@lucide/vue';
import { computed } from 'vue';
import { previewKind } from '@/lib/files';
import FmIcon from './FmIcon.vue';

const { url, mimeType, name } = defineProps<{
    /** The address that opens the file. */
    url: string;
    /** The real type of the file. */
    mimeType: string;
    /** The original name. */
    name: string;
}>();

const kind = computed(() => previewKind(mimeType));
</script>

<template>
    <div
        class="flex min-h-[420px] w-full items-center justify-center overflow-hidden rounded-[14px] border border-edge bg-white"
    >
        <iframe
            v-if="kind === 'pdf'"
            :src="url"
            :title="name"
            class="h-[560px] w-full border-0"
        />
        <img
            v-else-if="kind === 'image'"
            :src="url"
            :alt="name"
            class="max-h-[560px] w-full object-contain"
        />
        <div v-else class="flex flex-col items-center gap-3 p-8 text-center">
            <FmIcon :icon="FileText" :size="48" class="text-ink-soft" />
            <span class="text-[15px] font-semibold break-all">{{ name }}</span>
            <a :href="url" target="_blank" rel="noopener" class="font-semibold">
                {{ $t('Open the file') }}
            </a>
        </div>
    </div>
</template>
