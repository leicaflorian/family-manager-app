<script setup lang="ts">
/**
 * A document as the board *Un documento* shows it: the preview of the file,
 * its data, the linked records and the notes, with *Download*, *Share*,
 * *Edit* and, for an `admin`, *Delete*.
 * Used by *A document* and, on the computer, on the right of *Documents*.
 */
import { Download, Pencil, Share2 } from '@lucide/vue';
import { useFormat } from '@/composables/useFormat';
import { useShare } from '@/composables/useShare';
import { destroy, edit } from '@/routes/documents';
import FmButton from './FmButton.vue';
import FmCard from './FmCard.vue';
import FmDeleteButton from './FmDeleteButton.vue';
import FmFilePreview from './FmFilePreview.vue';

const { document } = defineProps<{
    /** The document. */
    document: App.Data.DocumentDetailData;
}>();

const { date } = useFormat();
const { canShare, shareFile } = useShare();
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap gap-2.5">
            <FmButton
                variant="soft"
                :icon="Download"
                :href="document.file.download_url"
                download
            >
                {{ $t('Download') }}
            </FmButton>
            <FmButton
                v-if="canShare"
                variant="soft"
                :icon="Share2"
                @click="shareFile(document.file)"
            >
                {{ $t('Share') }}
            </FmButton>
            <FmButton
                v-if="document.can_edit"
                variant="soft"
                :icon="Pencil"
                :href="edit.url(document.id)"
            >
                {{ $t('Edit') }}
            </FmButton>
            <FmDeleteButton
                v-if="document.can_delete"
                :url="destroy.url(document.id)"
                :question="$t('Delete this document?')"
                :text="$t('The file goes too, and it cannot be recovered.')"
                :confirm-label="$t('Delete the document')"
            />
        </div>

        <div
            class="flex flex-col items-center gap-3 rounded-3xl bg-divider p-4 lg:p-6"
        >
            <FmFilePreview
                :url="document.file.url"
                :mime-type="document.file.mime_type"
                :name="document.file.name"
            />
            <span class="text-sm break-all text-ink-soft">{{
                document.file.name
            }}</span>
        </div>

        <div class="rounded-[20px] bg-white p-5">
            <dl
                class="m-0 grid grid-cols-[max-content_minmax(0,1fr)] items-baseline gap-x-4 gap-y-2.5 text-[15px]"
            >
                <dt class="text-ink-soft">{{ $t('What kind it is') }}</dt>
                <dd class="m-0 font-semibold">
                    {{ document.category ?? '—' }}
                </dd>
                <dt class="text-ink-soft">{{ $t('Whose it is') }}</dt>
                <dd class="m-0 font-semibold">{{ document.person ?? '—' }}</dd>
                <dt class="text-ink-soft">{{ $t('Who it is from') }}</dt>
                <dd class="m-0 font-semibold">
                    {{ document.organization ?? '—' }}
                </dd>
                <dt class="text-ink-soft">{{ $t('Date') }}</dt>
                <dd class="m-0 font-semibold">
                    {{ date(document.date) || '—' }}
                </dd>
                <dt class="text-ink-soft">{{ $t('Expiry') }}</dt>
                <dd class="m-0 font-semibold">
                    <span v-if="document.expiry_date">{{
                        date(document.expiry_date)
                    }}</span>
                    <span v-else class="font-normal text-ink-soft">{{
                        $t('No expiry')
                    }}</span>
                </dd>
                <dt class="text-ink-soft">{{ $t('Status') }}</dt>
                <dd class="m-0 font-semibold">{{ document.status_label }}</dd>
                <dt class="text-ink-soft">{{ $t('Tags') }}</dt>
                <dd class="m-0 font-semibold">
                    {{ document.tags.join(', ') || '—' }}
                </dd>
            </dl>
        </div>

        <FmCard v-if="document.links.length > 0" :title="$t('Links')" list>
            <div
                v-for="link in document.links"
                :key="`${link.type}:${link.id}`"
                class="flex flex-col gap-0.5 px-4 py-3"
            >
                <span class="text-base font-semibold">{{ link.label }}</span>
                <span class="text-[13px] text-ink-soft">{{
                    link.type_label
                }}</span>
            </div>
        </FmCard>

        <FmCard v-if="document.notes" :title="$t('Notes')">
            <p
                class="m-0 text-[15px] leading-normal whitespace-pre-line text-ink-soft"
            >
                {{ document.notes }}
            </p>
        </FmCard>
    </div>
</template>
