<script setup lang="ts">
/**
 * *New card* of a type and *Edit the card* (only an `admin`): the form built
 * from the description of the fields the server gives. A new card is sent with
 * POST and opens; a change is sent with PUT. On the left, the box of its
 * documents: the ones linked and the files to add, sent with the card, after
 * which *Upload* opens to file them.
 */
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCardFiles from '@/components/fm/FmCardFiles.vue';
import FmCardForm from '@/components/fm/FmCardForm.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { listErrors } from '@/lib/errors';
import { withMethod } from '@/lib/forms';
import { show as showArea } from '@/routes/areas';
import { show, store, update } from '@/routes/cards';

const {
    type,
    fields,
    values,
    card,
    documents = [],
} = defineProps<{
    /** The card type. */
    type: App.Data.CardTypeData;
    /** The description of its fields. */
    fields: App.Data.CardFieldData[];
    /** The values the form starts from (the card's, or the presets of a new one). */
    values: Record<string, unknown>;
    /** The card to change, or null for a new one. */
    card: App.Data.CardData | null;
    /** The documents linked to the card; none for a new one. */
    documents?: App.Data.DocumentData[];
}>();

/** The form is being sent. */
const processing = ref(false);

/** The errors of the server, by column. */
const serverErrors = ref<Partial<Record<string, string>>>({});

/** The files of new documents of the card, sent with it. */
const files = ref<File[]>([]);

/** The messages of the files refused (`files.0`, …), all in one notice. */
const fileErrors = computed(() => listErrors(serverErrors.value, 'files'));

/** The title of the page. */
const title = computed(() =>
    card
        ? trans('Edit the :type', { type: type.label.toLowerCase() })
        : trans('New :type', { type: type.label.toLowerCase() }),
);

/** Where *Cancel* and the × go: the card, or the area of a new one. */
const back = computed(() =>
    card ? show.url({ type: card.type, id: card.id }) : showArea.url(type.area),
);

/**
 * Sends the values: a change of the card (PUT), or a new one (POST).
 *
 * @param data - the values of the form
 */
function save(data: Record<string, unknown>): void {
    const visit = {
        onStart: () => (processing.value = true),
        onError: (errors: Record<string, string>) =>
            (serverErrors.value = errors),
        onFinish: () => (processing.value = false),
    };
    const payload = (
        files.value.length > 0 ? { ...data, files: files.value } : data
    ) as RequestPayload;

    // Always a POST: PHP does not read the files of a PUT, so a change goes
    // with the real method in _method
    router.post(
        card
            ? update.url({ type: card.type, id: card.id })
            : store.url(type.type),
        card ? withMethod(payload, 'put') : payload,
        visit,
    );
}
</script>

<template>
    <Head :title="title" />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle
            :title="title"
            :back="back"
            :back-label="card ? card.label : type.area_label"
        >
            <template #actions>
                <Link
                    :href="back"
                    :aria-label="$t('Close')"
                    class="flex size-11 items-center justify-center rounded-xl border border-edge bg-white text-ink"
                >
                    <FmIcon :icon="X" :size="20" />
                </Link>
            </template>
        </FmTitle>
        <div
            class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] lg:items-start"
        >
            <FmCardFiles
                v-model:files="files"
                :documents="documents"
                :errors="fileErrors"
            />
            <div class="rounded-3xl bg-white p-5 lg:p-7">
                <FmCardForm
                    :fields="fields"
                    :values="values"
                    :server-errors="serverErrors"
                    :processing="processing"
                    @submit="save"
                >
                    <template #start>
                        <FmButton variant="text" :href="back">{{
                            $t('Cancel')
                        }}</FmButton>
                    </template>
                </FmCardForm>
            </div>
        </div>
    </div>
</template>
