<script setup lang="ts">
/**
 * What the scan has to say: why some files were not taken or why the edges are set by hand, the progress of the
 * upload, why it stopped with *Try again*, and «Uploaded: 3 pages, 1,5 MB» at the end. Under the pages on the
 * phone, above *Upload* in the column of the computer.
 */
import { CircleCheck } from '@lucide/vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmNotice from '@/components/fm/FmNotice.vue';

defineProps<{
    /** A warning of the page, null without one. */
    notice: string | null;
    /** Why the upload stopped, null without a problem. */
    error: string | null;
    /** *Try again* can send the rest. */
    canRetry: boolean;
    /** What arrived, after the last file. */
    done: string | null;
    /** The files are leaving. */
    sending: boolean;
    /** How much has left, from 0 to 100. */
    progress: number;
    /** «Uploading…», or «Uploading file 2 of 3…». */
    progressText: string;
}>();

const emit = defineEmits<{
    /** *Try again*. */
    retry: [];
}>();
</script>

<template>
    <div
        v-if="notice || error || done || sending"
        class="flex flex-col gap-2 self-stretch"
    >
        <FmNotice v-if="notice" tone="warning">{{ notice }}</FmNotice>
        <FmNotice v-if="error" tone="danger">
            {{ error }}
            <template v-if="canRetry && !sending" #action>
                <FmButton variant="text" @click="emit('retry')">{{
                    $t('Try again')
                }}</FmButton>
            </template>
        </FmNotice>
        <FmNotice v-if="done" tone="ok" :icon="CircleCheck">{{
            done
        }}</FmNotice>
        <div v-if="sending" role="status" class="flex flex-col gap-1.5">
            <span class="text-sm text-ink-soft">{{ progressText }}</span>
            <progress
                class="h-2 w-full accent-action"
                max="100"
                :value="progress"
            />
        </div>
    </div>
</template>
