<script setup lang="ts">
/**
 * *Upload* of a card: the panel with the zone where the files of documents
 * are dragged or chosen. They are sent at once, linked to the card, and
 * *Upload* opens with the form of the first one; a file refused leaves the
 * panel open, with the reason.
 */
import type { DocumentLink } from '@/lib/documents';
import { useFileUpload } from '@/composables/useFileUpload';
import FmBottomSheet from './FmBottomSheet.vue';
import FmFileDrop from './FmFileDrop.vue';
import FmNotice from './FmNotice.vue';

const { link } = defineProps<{
    /** The card the files are linked to. */
    link: DocumentLink;
}>();

/** The panel is open. */
const open = defineModel<boolean>('open', { default: false });

const { send, processing, errors } = useFileUpload(() => link);
</script>

<template>
    <FmBottomSheet v-model:open="open" :title="$t('Upload a document')">
        <FmNotice v-if="errors.length > 0" tone="warning">
            <p v-for="message in errors" :key="message" class="m-0">
                {{ message }}
            </p>
        </FmNotice>
        <FmFileDrop :processing="processing" @files="send" />
    </FmBottomSheet>
</template>
