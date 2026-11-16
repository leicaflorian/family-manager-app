<script setup lang="ts">
/**
 * *New card* of a type and *Edit the card* (only an `admin`): the form built
 * from the description of the fields the server gives. A new card is sent with
 * POST and opens; a change is sent with PUT. On the left, the box of its
 * documents: the ones linked and the files to add, sent with the card, after
 * which *Upload* opens to file them; the documents chosen in *Link* and those
 * marked to unlink go with the card too, and change only when it is saved.
 * On the computer, below them, the planned expenses and the payments of the
 * card (`FmCardPayments`), as the board *Modifica-pc*.
 */
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmCardFiles from '@/components/fm/FmCardFiles.vue';
import FmCardPayments from '@/components/fm/FmCardPayments.vue';
import FmCardForm from '@/components/fm/FmCardForm.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { usePageTrail } from '@/composables/usePageTrail';
import { listErrors } from '@/lib/errors';
import { withMethod } from '@/lib/forms';
import { replaceableFor, replaceOptions } from '@/lib/policies';
import { areas } from '@/routes';
import { show as showArea } from '@/routes/areas';
import { show, store, update } from '@/routes/cards';
import { useLayoutMode } from '@/composables/useLayoutMode';

const {
    type,
    fields,
    values,
    card,
    documents = [],
    canDeleteDocuments = false,
    linkable = [],
    linkableNext = null,
    linkableSearch = '',
    hasLinkable = false,
    budget = null,
    loanFigures = null,
    linked = [],
    replaceable = [],
    replaces = [],
    payment = null,
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
    /** Whether whoever edits may delete the linked documents. */
    canDeleteDocuments?: boolean;
    /** The pages loaded so far of the documents that can be linked, for *Link*. */
    linkable?: App.Data.DocumentData[];
    /** The number of the next page of `linkable`, null at the last one. */
    linkableNext?: number | null;
    /** The search that `linkable` answers. */
    linkableSearch?: string;
    /** At least one document of the family can be linked. */
    hasLinkable?: boolean;
    /** The planned expenses and the payments of the card to change; none for a new one. */
    budget?: App.Data.CardBudgetData | null;
    /** The total to pay and the cost of a loan, asked for while the person types; only a loan gets them. */
    loanFigures?: App.Data.LoanFiguresData | null;
    /** The documents a new card starts linked to (`?document=`, after an import with *Create the card*). */
    linked?: App.Data.DocumentData[];
    /** The old policies a new policy can replace (a new policy only). */
    replaceable?: App.Data.ReplaceablePolicyData[];
    /** The ids of the old policies ticked from the start (a renewal). */
    replaces?: number[];
    /** The payment of the old policy that the new one takes over, sent with the policies to replace. */
    payment?: number | null;
}>();

/** The box *Cancel the policy* exists only for a new policy. */
const canReplace = computed(
    () => card === null && type.type === 'insurance_policy',
);

/** The ids of the old policies ticked. */
const ticked = ref<number[]>([...replaces]);

/** The type the form started with: a renewal keeps its old policy only while the type is the same. */
const startType = values.type;

/**
 * The old policies offered with the values of the form.
 *
 * @param live - the values of the form
 * @returns the policies to show, each one a box
 */
function offered(
    live: Record<string, unknown>,
): App.Data.ReplaceablePolicyData[] {
    return replaceableFor(
        replaceable,
        live,
        live.type === startType ? replaces : [],
    );
}

/** The form is being sent. */
const processing = ref(false);

/** The errors of the server, by column. */
const serverErrors = ref<Partial<Record<string, string>>>({});

/** The files of new documents of the card, sent with it. */
const files = ref<File[]>([]);

/** The documents chosen in *Link*, linked when the card is saved; a new card starts from those it was opened with. */
const link = ref<App.Data.DocumentData[]>([...linked]);

/** The ids of the linked documents to unlink when the card is saved. */
const unlink = ref<number[]>([]);

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

usePageTrail(() => [
    { label: trans('Areas'), href: areas().url },
    { label: type.area_label, href: showArea.url(type.area) },
    ...(card
        ? [
              {
                  label: card.label,
                  href: show.url({ type: card.type, id: card.id }),
              },
          ]
        : []),
    { label: title.value },
]);

/**
 * The policies to replace and their payment, only the ids still offered with
 * the values sent (a policy ticked before the type changed is dropped).
 *
 * @param data - the values of the form
 * @returns the fields to add to the request, none when nothing is replaced
 */
function replacing(data: Record<string, unknown>): {
    replace_policies?: number[];
    replace_payment?: number;
} {
    const ids = offered(data)
        .map((policy) => policy.id)
        .filter((id) => ticked.value.includes(id));

    return ids.length === 0
        ? {}
        : {
              replace_policies: ids,
              ...(payment !== null && { replace_payment: payment }),
          };
}

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
    const payload = {
        ...data,
        ...(files.value.length > 0 && { files: files.value }),
        ...(link.value.length > 0 && {
            link_documents: link.value.map((document) => document.id),
        }),
        ...(unlink.value.length > 0 && { unlink_documents: unlink.value }),
        ...(canReplace.value && replacing(data)),
    } as RequestPayload;

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

const layout = useLayoutMode();
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
                    v-if="layout !== 'computer'"
                    :href="back"
                    :aria-label="$t('Close')"
                    class="flex size-11 items-center justify-center rounded-lg border border-edge-soft bg-card text-ink"
                >
                    <FmIcon :icon="X" :size="20" />
                </Link>
            </template>
        </FmTitle>
        <div
            class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)] lg:items-start"
        >
            <div class="flex min-w-0 flex-col gap-6">
                <FmCardFiles
                    v-model:files="files"
                    v-model:link="link"
                    v-model:unlink="unlink"
                    :documents="documents"
                    :card="card ? { type: card.type, id: card.id } : undefined"
                    :can-delete="canDeleteDocuments"
                    :errors="fileErrors"
                    :linkable="linkable"
                    :linkable-next="linkableNext"
                    :linkable-search="linkableSearch"
                    :has-linkable="hasLinkable"
                />
                <div
                    v-if="card && budget"
                    data-testid="card-payments"
                    class="hidden flex-col gap-5 lg:flex"
                >
                    <FmCardPayments
                        :budget="budget"
                        :card="{ type: card.type, id: card.id }"
                    />
                </div>
            </div>
            <div class="rounded-sheet bg-card p-5 shadow-card lg:p-7">
                <FmCardForm
                    :fields="fields"
                    :values="values"
                    :server-errors="serverErrors"
                    :processing="processing"
                    :loan-figures="
                        type.type === 'loan' ? loanFigures : undefined
                    "
                    @submit="save"
                >
                    <template v-if="canReplace" #after="{ values: live }">
                        <FormKit
                            v-if="offered(live).length > 0"
                            v-model="ticked"
                            type="checkbox"
                            name="replace_policies"
                            :options="
                                replaceOptions(offered(live), {
                                    premiumMoves: true,
                                })
                            "
                        />
                    </template>
                    <template #start>
                        <FmButton
                            variant="text"
                            :href="back"
                            data-discard-changes
                            >{{ $t('Cancel') }}</FmButton
                        >
                    </template>
                </FmCardForm>
            </div>
        </div>
    </div>
</template>
