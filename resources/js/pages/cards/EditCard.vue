<script setup lang="ts">
/**
 * *New card* of a type and *Edit the card* (only an `admin`): the form built
 * from the description of the fields the server gives. A new card is sent with
 * POST and opens; a change is sent with PUT.
 */
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCardForm from '@/components/fm/FmCardForm.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { show as showArea } from '@/routes/areas';
import { show, store, update } from '@/routes/cards';

const { type, fields, values, card } = defineProps<{
    /** The card type. */
    type: App.Data.CardTypeData;
    /** The description of its fields. */
    fields: App.Data.CardFieldData[];
    /** The values the form starts from (the card's, or the presets of a new one). */
    values: Record<string, unknown>;
    /** The card to change, or null for a new one. */
    card: App.Data.CardData | null;
}>();

/** The form is being sent. */
const processing = ref(false);

/** The errors of the server, by column. */
const serverErrors = ref<Partial<Record<string, string>>>({});

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
 * Sends the values: PUT on the card, POST for a new one.
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
    const payload = data as RequestPayload;

    if (card) {
        router.put(
            update.url({ type: card.type, id: card.id }),
            payload,
            visit,
        );
    } else {
        router.post(store.url(type.type), payload, visit);
    }
}
</script>

<template>
    <Head :title="title" />

    <div class="flex max-w-[1120px] flex-col gap-4">
        <FmTitle
            :title="title"
            :back="back"
            :back-label="card ? card.label : $t('Areas')"
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
        <div class="max-w-[720px] rounded-3xl bg-white p-5 lg:p-7">
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
</template>
