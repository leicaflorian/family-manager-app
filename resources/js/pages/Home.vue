<script setup lang="ts">
/**
 * The *Home*, for now with three sections: the files waiting for their
 * document (the first five, with *Finish* that opens *Upload*), the documents
 * expiring (within 60 days or already expired, the most urgent first) and the
 * last five uploaded. Without documents it invites to upload the first one; the
 * files waiting show anyway. The payments and the next deadline come with stage 3.
 */
import { Head } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
import { getActiveLanguage } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCard from '@/components/fm/FmCard.vue';
import FmDocumentRow from '@/components/fm/FmDocumentRow.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFileTile from '@/components/fm/FmFileTile.vue';
import FmRow from '@/components/fm/FmRow.vue';
import { formatLongDate, formatSize } from '@/lib/format';
import { upload } from '@/routes';
import { show } from '@/routes/documents';

const {
    firstName,
    today,
    expiring,
    recent,
    hasDocuments,
    pending,
    pendingCount,
} = defineProps<{
    /** The first name of whoever signed in. */
    firstName: string;
    /** The date of today on the server, ISO: tells the expired documents. */
    today: string;
    /** The documents expiring or expired, the most urgent first. */
    expiring: App.Data.DocumentData[];
    /** The last documents uploaded, the most recent first. */
    recent: App.Data.DocumentData[];
    /** The family has at least one document. */
    hasDocuments: boolean;
    /** The first files waiting for their document, the oldest first. */
    pending: App.Data.PendingFileData[];
    /** How many files wait in all, also beyond the ones in `pending`. */
    pendingCount: number;
}>();

const day = computed(() => formatLongDate(today, getActiveLanguage()));

/** The files waiting that the section does not show. */
const morePending = computed(() => pendingCount - pending.length);
</script>

<template>
    <Head :title="$t('Home')" />

    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-1">
            <span class="text-sm text-ink-soft lg:text-[15px]">{{ day }}</span>
            <h1 class="m-0 text-[28px] font-extrabold lg:text-[32px]">
                {{ $t('Hi, :name', { name: firstName }) }}
            </h1>
        </div>

        <FmCard v-if="pendingCount > 0" :title="$t('Waiting')" list>
            <template #actions>
                <FmButton variant="text" small :href="upload().url">{{
                    $t('Finish')
                }}</FmButton>
            </template>
            <FmRow
                v-for="file in pending"
                :key="file.id"
                :title="file.title"
                :subtitle="formatSize(file.size)"
                :arrow="false"
            >
                <template #before>
                    <FmFileTile />
                </template>
            </FmRow>
            <p
                v-if="morePending > 0"
                class="m-0 px-4 py-3.5 text-[15px] text-ink-soft"
            >
                {{
                    $tChoice(
                        '{1} +:count more|[2,*] +:count more',
                        morePending,
                        {
                            count: String(morePending),
                        },
                    )
                }}
            </p>
        </FmCard>

        <FmEmpty
            v-if="!hasDocuments"
            :icon="FileText"
            :title="$t('No documents, for now')"
            :text="
                $t(
                    'Upload a bill, a payslip or the identity card: you will find them here, with their expiry.',
                )
            "
        >
            <FmButton :href="upload().url">{{
                $t('Upload the first document')
            }}</FmButton>
        </FmEmpty>

        <div v-else class="grid gap-5 lg:grid-cols-2 lg:items-start">
            <FmCard :title="$t('Expiring')" :list="expiring.length > 0">
                <FmDocumentRow
                    v-for="document in expiring"
                    :key="document.id"
                    :document="document"
                    :href="show.url(document.id)"
                    :today="today"
                    expiry
                />
                <p
                    v-if="expiring.length === 0"
                    class="m-0 text-[15px] text-ink-soft"
                >
                    {{ $t('Nothing expires in the next 60 days.') }}
                </p>
            </FmCard>
            <FmCard :title="$t('Recently uploaded')" list>
                <FmDocumentRow
                    v-for="document in recent"
                    :key="document.id"
                    :document="document"
                    :href="show.url(document.id)"
                />
            </FmCard>
        </div>
    </div>
</template>
