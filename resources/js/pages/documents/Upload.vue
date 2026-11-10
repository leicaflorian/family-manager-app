<script setup lang="ts">
/**
 * *Upload a document*: one or more files (PDF, JPG, PNG, HEIC, up to 25 MB
 * each) are chosen or dragged here and sent. The server keeps them waiting,
 * starts their reading and opens *Import more files* with them, where each is
 * checked and filed as a document: this page only takes the files. The card
 * the upload starts from travels with them. The × goes back to that card, or
 * to the home page.
 */
import { Head, Link } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import FmToSortLink from '@/components/fm/FmToSortLink.vue';
import { useFileUpload } from '@/composables/useFileUpload';
import { useToSortCount } from '@/composables/useToSortCount';
import type { DocumentLink } from '@/lib/documents';
import { dashboard } from '@/routes';
import { show as showCard } from '@/routes/cards';
import { useLayoutMode } from '@/composables/useLayoutMode';

const props = defineProps<{
    /** The card the person comes from, kept for the files and the ×. */
    presetLinks: DocumentLink[];
}>();

/** The files waiting in *To sort*, for the link beside the title. */
const toSortCount = useToSortCount();

/** The card the person comes from. */
const preset = computed(() => props.presetLinks[0]);

/** Where the × goes: the card the person comes from, or the home page. */
const closeUrl = computed(() =>
    preset.value ? showCard.url(preset.value) : dashboard().url,
);

const {
    send,
    processing,
    errors: uploadErrors,
} = useFileUpload(() => preset.value);

const layout = useLayoutMode();
</script>

<template>
    <Head :title="$t('Upload a document')" />

    <div class="flex flex-col gap-6">
        <FmTitle :title="$t('Upload a document')">
            <template #actions>
                <FmToSortLink :count="toSortCount" />
                <Link
                    v-if="layout !== 'computer'"
                    :href="closeUrl"
                    :aria-label="$t('Close')"
                    class="flex size-11 items-center justify-center rounded-lg border border-edge-soft bg-card text-ink"
                >
                    <FmIcon :icon="X" :size="20" />
                </Link>
            </template>
        </FmTitle>

        <FmNotice v-if="uploadErrors.length > 0" tone="warning">
            <p v-for="message in uploadErrors" :key="message" class="m-0">
                {{ message }}
            </p>
        </FmNotice>

        <FmFileDrop :processing="processing" @files="send" />
    </div>
</template>
