<script setup lang="ts">
/**
 * The buttons of a document. On its own page they are `FmActions`: *Download*,
 * *Edit* (light blue) and *Delete* (red, for an `admin`, after asking) as
 * buttons while they fit, and *Open in a new tab* and *Share* in the ⋯ menu.
 * In the panel beside the list *Download* is an icon and the rest is in the
 * menu; in the column beside the list (`menuOnly`) every action, *Download*
 * and *Open the page of the document* too, is in a ⋮ menu beside the ×.
 *
 * @group files
 */
import {
    Download,
    EllipsisVertical,
    ExternalLink,
    FileText,
    Pencil,
    Pin,
    PinOff,
    Share2,
    Trash2,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { useDocumentPin } from '@/composables/useDocumentPin';
import { useShare } from '@/composables/useShare';
import type { Action } from '@/lib/actions';
import { destroy, edit, show } from '@/routes/documents';
import FmActions from './FmActions.vue';
import FmButton from './FmButton.vue';
import FmConfirmDelete from './FmConfirmDelete.vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

const {
    document,
    compact = false,
    menuOnly = false,
} = defineProps<{
    /** The document. */
    document: App.Data.DocumentDetailData;
    /** The panel beside the list: *Download* as an icon, *Edit* in the menu. */
    compact?: boolean;
    /** With `compact`: only a ⋮ menu with every action, *Download* first, for the column beside the list. */
    menuOnly?: boolean;
}>();

const { canShare, shareFile } = useShare();

/** The panel that asks before deleting, opened from the ⋯ menu. */
const confirmingDelete = ref(false);

const { togglePin, pinLabel } = useDocumentPin();

/** The actions of the document on its own page, the most frequent first. */
const actions = computed((): Action[] => [
    {
        label: trans('Download'),
        icon: Download,
        variant: 'outline',
        href: document.file.download_url,
        download: true,
    },
    ...(document.can_edit
        ? [
              {
                  label: trans('Edit'),
                  icon: Pencil,
                  variant: 'soft',
                  href: edit.url(document.id),
              } satisfies Action,
          ]
        : []),
    ...(document.can_delete
        ? [
              {
                  label: trans('Delete'),
                  icon: Trash2,
                  variant: 'danger',
                  confirm: {
                      url: destroy.url(document.id),
                      question: trans('Delete this document?'),
                      text: trans(
                          'The file goes too, and it cannot be recovered.',
                      ),
                      confirmLabel: trans('Delete the document'),
                  },
              } satisfies Action,
          ]
        : []),
    {
        label: trans('Open in a new tab'),
        icon: ExternalLink,
        href: document.file.url,
        external: true,
        menuOnly: true,
    },
    ...(canShare
        ? [
              {
                  label: trans('Share'),
                  icon: Share2,
                  onSelect: () => void shareFile(document.file),
                  menuOnly: true,
              } satisfies Action,
          ]
        : []),
    ...(document.can_pin
        ? [
              {
                  label: pinLabel(document.pinned),
                  icon: document.pinned ? PinOff : Pin,
                  onSelect: () => togglePin(document),
                  menuOnly: true,
              } satisfies Action,
          ]
        : []),
]);
</script>

<template>
    <FmActions v-if="!compact" :actions="actions" />
    <div v-else class="flex shrink-0 items-center gap-1.5">
        <FmButton
            v-if="!menuOnly"
            variant="soft"
            :icon="Download"
            :label="$t('Download')"
            small
            :href="document.file.download_url"
            download
        />
        <FmMenu
            :small="!menuOnly"
            :plain="menuOnly"
            :icon="menuOnly ? EllipsisVertical : undefined"
        >
            <template v-if="menuOnly">
                <FmMenuItem
                    :icon="Download"
                    :href="document.file.download_url"
                    download
                >
                    {{ $t('Download') }}
                </FmMenuItem>
                <FmMenuItem
                    v-if="document.can_edit"
                    :icon="Pencil"
                    :href="edit.url(document.id)"
                >
                    {{ $t('Edit') }}
                </FmMenuItem>
            </template>
            <FmMenuItem :icon="ExternalLink" :href="document.file.url" external>
                {{ $t('Open in a new tab') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="menuOnly"
                :icon="FileText"
                :href="show.url(document.id)"
            >
                {{ $t('Open the page of the document') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="canShare"
                :icon="Share2"
                @select="shareFile(document.file)"
            >
                {{ $t('Share') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="document.can_edit && !menuOnly"
                :icon="Pencil"
                :href="edit.url(document.id)"
            >
                {{ $t('Edit') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="document.can_pin"
                :icon="document.pinned ? PinOff : Pin"
                @select="togglePin(document)"
            >
                {{ pinLabel(document.pinned) }}
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
