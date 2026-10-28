<script setup lang="ts">
/**
 * *Edit a document*, as the board *Modifica documento*: the file on grey and,
 * beside it, the form in a white card, with *Delete the document* at the
 * bottom for an `admin`. *Cancel* goes back to the document.
 */
import { Head, router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmConfirmDelete from '@/components/fm/FmConfirmDelete.vue';
import FmDocumentForm from '@/components/fm/FmDocumentForm.vue';
import FmFilePanel from '@/components/fm/FmFilePanel.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import type { DocumentFormOptions } from '@/lib/documents';
import { documentValues } from '@/lib/documents';
import { destroy, file, show, update } from '@/routes/documents';

const { document, options } = defineProps<{
    /** The document as it is. */
    document: App.Data.DocumentDetailData;
    /** The choices of the form. */
    options: DocumentFormOptions;
}>();

/** The panel that asks before deleting. */
const confirmingDelete = ref(false);
</script>

<template>
    <Head :title="$t('Edit the document')" />

    <div class="flex max-w-[1120px] flex-col gap-6">
        <FmTitle
            :title="$t('Edit the document')"
            :back="show.url(document.id)"
            :back-label="document.title"
        />
        <div
            class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] lg:items-start"
        >
            <FmFilePanel
                :file="document.file"
                :change-url="file.url(document.id)"
            />
            <section class="rounded-3xl bg-white p-5 lg:p-7">
                <FmDocumentForm
                    :action="update(document.id)"
                    :initial="documentValues(document)"
                    :options="options"
                    :submit-label="$t('Save')"
                    :cancel-label="$t('Cancel')"
                    @cancel="router.visit(show.url(document.id))"
                >
                    <template v-if="document.can_delete" #start>
                        <FmButton
                            variant="danger"
                            :icon="Trash2"
                            @click="confirmingDelete = true"
                        >
                            {{ $t('Delete the document') }}
                        </FmButton>
                    </template>
                </FmDocumentForm>
            </section>
        </div>
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
