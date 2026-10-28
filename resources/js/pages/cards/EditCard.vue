<script setup lang="ts">
/**
 * *New card* of a type and *Edit the card* (only an `admin`): the form built
 * from the description of the fields the server gives. A new card is sent with
 * POST and opens; a change is sent with PUT. A new card can bring the files of
 * its documents: they are sent with it, and *Upload* opens to file them.
 */
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCardForm from '@/components/fm/FmCardForm.vue';
import FmFileDrop from '@/components/fm/FmFileDrop.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmNotice from '@/components/fm/FmNotice.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { listErrors } from '@/lib/errors';
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

/** The files of the documents of a new card, sent with it. */
const files = ref<File[]>([]);

/** The messages of the files refused (`files.0`, …), all in one notice. */
const fileErrors = computed(() => listErrors(serverErrors.value, 'files'));

/**
 * Adds the chosen files to the ones already there.
 *
 * @param chosen - the files given by the zone
 */
function add(chosen: File[]): void {
    files.value = [...files.value, ...chosen];
}

/**
 * Takes a file off the list.
 *
 * @param index - its place in the list
 */
function remove(index: number): void {
    files.value = files.value.filter((_, at) => at !== index);
}

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
    const payload = (
        files.value.length > 0 ? { ...data, files: files.value } : data
    ) as RequestPayload;

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
        <div class="max-w-[720px] rounded-3xl bg-white p-5 lg:p-7">
            <FmCardForm
                :fields="fields"
                :values="values"
                :server-errors="serverErrors"
                :processing="processing"
                @submit="save"
            >
                <template v-if="!card" #end>
                    <div class="flex flex-col gap-2.5">
                        <FmNotice v-if="fileErrors.length > 0" tone="warning">
                            <p
                                v-for="message in fileErrors"
                                :key="message"
                                class="m-0"
                            >
                                {{ message }}
                            </p>
                        </FmNotice>
                        <ul
                            v-if="files.length > 0"
                            v-auto-animate
                            class="m-0 flex list-none flex-col gap-1.5 p-0"
                        >
                            <li
                                v-for="(file, index) in files"
                                :key="`${index}-${file.name}`"
                                class="flex items-center gap-2 rounded-xl bg-canvas px-3 py-2"
                            >
                                <FmIcon :icon="FileText" :size="18" />
                                <span
                                    class="min-w-0 flex-1 text-sm break-all"
                                    >{{ file.name }}</span
                                >
                                <button
                                    type="button"
                                    :aria-label="
                                        $t('Remove :name', { name: file.name })
                                    "
                                    class="flex size-8 items-center justify-center rounded-lg text-ink-soft"
                                    @click="remove(index)"
                                >
                                    <FmIcon :icon="X" :size="16" />
                                </button>
                            </li>
                        </ul>
                        <FmFileDrop
                            compact
                            :label="
                                $t(
                                    'Its documents? Drag them here, or choose them',
                                )
                            "
                            @files="add"
                        />
                    </div>
                </template>
                <template #start>
                    <FmButton variant="text" :href="back">{{
                        $t('Cancel')
                    }}</FmButton>
                </template>
            </FmCardForm>
        </div>
    </div>
</template>
