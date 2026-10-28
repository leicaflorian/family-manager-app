<script setup lang="ts">
/**
 * The buttons of a document: *Download*, *Edit* and the ⋯ menu with *Open in a
 * new tab*, *Share* and, for an `admin`, *Delete* (after asking). On its own
 * page *Download* and *Edit* have their text, as the board *Un documento*; in
 * the panel beside the list *Download* is an icon and *Edit* is in the menu.
 */
import { Download, ExternalLink, Pencil, Share2, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useShare } from '@/composables/useShare';
import { cn } from '@/lib/utils';
import { destroy, edit } from '@/routes/documents';
import FmButton from './FmButton.vue';
import FmConfirmDelete from './FmConfirmDelete.vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

const { document, compact = false } = defineProps<{
    /** The document. */
    document: App.Data.DocumentDetailData;
    /** The panel beside the list: *Download* as an icon, *Edit* in the menu. */
    compact?: boolean;
}>();

const { canShare, shareFile } = useShare();

/** The panel that asks before deleting, opened from the ⋯ menu. */
const confirmingDelete = ref(false);
</script>

<template>
    <div :class="cn('flex shrink-0 flex-wrap gap-2.5', compact && 'gap-1.5')">
        <FmButton
            :variant="compact ? 'soft' : 'outline'"
            :icon="Download"
            :label="compact ? $t('Download') : undefined"
            :small="compact"
            :href="document.file.download_url"
            download
        >
            {{ $t('Download') }}
        </FmButton>
        <FmButton
            v-if="!compact && document.can_edit"
            :icon="Pencil"
            :href="edit.url(document.id)"
        >
            {{ $t('Edit') }}
        </FmButton>
        <FmMenu :small="compact">
            <FmMenuItem :icon="ExternalLink" :href="document.file.url" external>
                {{ $t('Open in a new tab') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="canShare"
                :icon="Share2"
                @select="shareFile(document.file)"
            >
                {{ $t('Share') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="compact && document.can_edit"
                :icon="Pencil"
                :href="edit.url(document.id)"
            >
                {{ $t('Edit') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="document.can_delete"
                :icon="Trash2"
                danger
                @select="confirmingDelete = true"
            >
                {{ $t('Delete') }}
            </FmMenuItem>
        </FmMenu>
        <FmConfirmDelete
            v-if="document.can_delete"
            v-model:open="confirmingDelete"
            :url="destroy.url(document.id)"
            :question="$t('Delete this document?')"
            :text="$t('The file goes too, and it cannot be recovered.')"
            :confirm-label="$t('Delete the document')"
        />
    </div>
</template>
