<script setup lang="ts">
/**
 * The buttons beside a document on the page of a card: *Open in a new tab*,
 * always in sight, and the ⋮ menu with *Edit* (the page of the document, as
 * its row), *Unlink* and *Delete* (for an `admin`), each after asking. The
 * page keeps its state, so in the form of the card what is written stays.
 * In the form (`inForm`) only the menu is left, where the room is short, with
 * *Open the file* first, and the links open in a new tab, so the form stays.
 */
import { router } from '@inertiajs/vue3';
import {
    EllipsisVertical,
    ExternalLink,
    FileText,
    Pencil,
    Trash2,
    Unlink,
} from '@lucide/vue';
import { ref } from 'vue';
import { destroy as unlinkDocument } from '@/routes/cards/documents';
import { destroy, show } from '@/routes/documents';
import FmButton from './FmButton.vue';
import FmConfirm from './FmConfirm.vue';
import FmConfirmDelete from './FmConfirmDelete.vue';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';

const {
    document,
    card,
    canDelete,
    inForm = false,
} = defineProps<{
    /** The document linked to the card. */
    document: App.Data.DocumentData;
    /** The address of the card in the routes: its type and its number. */
    card: { type: string; id: number };
    /** Whether whoever looks may delete the document. */
    canDelete: boolean;
    /** In the form of the card: only the menu, and its links in a new tab. */
    inForm?: boolean;
}>();

/** The panel that asks before unlinking, opened from the menu. */
const confirmingUnlink = ref(false);

/** The panel that asks before deleting, opened from the menu. */
const confirmingDelete = ref(false);

/** Removes the link between the document and the card; the document stays. */
function unlink(): void {
    router.delete(unlinkDocument.url({ ...card, document: document.id }), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (confirmingUnlink.value = false),
    });
}
</script>

<template>
    <div class="mr-3 flex shrink-0 items-center gap-1">
        <FmButton
            v-if="!inForm"
            variant="text"
            :icon="ExternalLink"
            :label="$t('Open :title in a new tab', { title: document.title })"
            small
            :href="document.file_url"
            external
        />
        <FmMenu
            :icon="EllipsisVertical"
            :label="$t('More actions on :title', { title: document.title })"
            small
        >
            <FmMenuItem
                v-if="inForm"
                :icon="FileText"
                :href="document.file_url"
                external
            >
                {{ $t('Open the file') }}
            </FmMenuItem>
            <FmMenuItem
                :icon="Pencil"
                :href="show.url(document.id)"
                :external="inForm"
            >
                {{ $t('Edit') }}
            </FmMenuItem>
            <FmMenuItem :icon="Unlink" @select="confirmingUnlink = true">
                {{ $t('Unlink') }}
            </FmMenuItem>
            <FmMenuItem
                v-if="canDelete"
                :icon="Trash2"
                danger
                @select="confirmingDelete = true"
            >
                {{ $t('Delete') }}
            </FmMenuItem>
        </FmMenu>
        <FmConfirm
            v-model:open="confirmingUnlink"
            :question="$t('Unlink :title?', { title: document.title })"
            :text="$t('The document stays, only its link to this card goes.')"
            :confirm-label="$t('Unlink')"
            @confirm="unlink"
        />
        <FmConfirmDelete
            v-if="canDelete"
            v-model:open="confirmingDelete"
            :url="destroy.url(document.id, { query: { stay: 1 } })"
            :question="$t('Delete this document?')"
            :text="$t('The file goes too, and it cannot be recovered.')"
            :confirm-label="$t('Delete the document')"
            preserve-state
        />
    </div>
</template>
