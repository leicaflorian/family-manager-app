<script setup lang="ts">
/**
 * The form of a bank filter, new or changed (*From the bank → Filters → New filter* or *Edit*), as the boards
 * *Dalla-banca-filtro* (phone: a page with *Back* to *From the bank*), *-tablet* and *-pc* (the form on the left, the
 * movements it takes on the right) draw it: the name, the sections *Matches*, *Commission* and *Action*, and beside
 * them the movements of the last 90 days the filter written so far takes, asked again half a second after the last
 * change (the last answer wins). Under the list the sentence that says what saving does, *Delete*, *Cancel* and
 * *Save the filter*, which stays off while a movement is taken by another filter. A new filter can start from a
 * proposal (`draft`): from a movement, from a group or from a planned item; the AI buttons of the words sit under
 * the amounts (`BankFilterAi`).
 */
import { Head, router } from '@inertiajs/vue3';
import { TriangleAlert, X } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import BankFilterAi from '@/components/budget/BankFilterAi.vue';
import BankFilterActionFields from '@/components/budget/BankFilterActionFields.vue';
import BankFilterCriteriaFields from '@/components/budget/BankFilterCriteriaFields.vue';
import BankFilterFeeFields from '@/components/budget/BankFilterFeeFields.vue';
import BankFilterPreviewList from '@/components/budget/BankFilterPreviewList.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmDeleteButton from '@/components/fm/FmDeleteButton.vue';
import FmTitle from '@/components/fm/FmTitle.vue';
import { usePageMeta } from '@/composables/usePageMeta';
import { useSaveShortcut } from '@/composables/useSaveShortcut';
import { useUnsavedChanges } from '@/composables/useUnsavedChanges';
import type {
    BankFilterFormOptions,
    BankFilterPayload,
} from '@/lib/bankFilters';
import {
    BankFilterPreviewError,
    formPayload,
    formValues,
    previewFilter,
    summaryLine,
} from '@/lib/bankFilters';
import { errors } from '@/lib/errors';
import { index as bankIndex } from '@/routes/budget/bank';
import { destroy, store, update } from '@/routes/budget/bank/filters';

const props = defineProps<{
    /** The filter being changed, null for a new one. */
    filter: App.Data.BankFilterData | null;
    /** The proposal a new filter starts from (a movement, a planned item), null for an empty one. */
    draft: App.Data.BankFilterData | null;
    /** The movements the filter takes and what saving it would do, for the fields the page starts with. */
    preview: App.Data.BankFilterPreviewData;
    /** The choices of the form. */
    options: BankFilterFormOptions;
    /** Whether who writes can make a filter that pays a due date (an administrator). */
    can_pay: boolean;
    /** Whether the AI can read: without it the AI buttons of the words are hidden. */
    ai?: boolean;
    /** The movements a new filter starts from (the movement or the group), which the AI reads while the filter takes none. */
    example_ids?: number[];
    /** The address asked the AI to fill this new filter (`ai=1`): once, and the address loses it at once. */
    ask_ai?: boolean;
}>();

/** How long to wait after the last change before asking the preview, in milliseconds. */
const PREVIEW_DELAY_MS = 500;

/** The fields of the form. */
const values = reactive(formValues(props.filter ?? props.draft));

/** The messages of the server, by field. */
const problems = ref<Record<string, string>>({});

/** The form is being sent. */
const processing = ref(false);

/** The movements the filter written so far takes. */
const shownPreview = ref<App.Data.BankFilterPreviewData>(props.preview);

/** Why the preview could not be asked, null when it could. */
const previewError = ref<string | null>(null);

/** A preview is on its way. */
const previewLoading = ref(false);

/** The address of the tab *Filters*, where *Cancel*, *Close* and the trail lead. */
const filtersUrl = bankIndex({ query: { show: 'filters' } }).url;

/** The movements the AI reads: the ones the filter written so far takes (the first fifty are read). */
const aiKeep = computed((): number[] => {
    const taken = shownPreview.value.rows.map((row) => row.transaction.id);

    // Without a word the filter takes nothing: the example it started from is what the AI reads
    return taken.length > 0 ? taken : (props.example_ids ?? []);
});

/** The AI is writing: the words cannot change meanwhile. */
const aiBusy = ref(false);

/**
 * The address asked the AI (`ai=1`, from *Create the filter* on a group without a proposal): it fills the title,
 * category and organization once. Read at the start, so what follows never asks again.
 */
const askAtOpen = props.ask_ai === true && props.filter === null;

/** Who looks can change this filter: always for a new one. */
const canSave = computed(
    (): boolean => props.filter === null || props.filter.can.update,
);

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;
let latest = 0;

/** Stops the waiting timer and the request on its way. */
function cancelPreview(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }

    controller?.abort();
    controller = null;
}

/**
 * Asks the preview for the fields of now; only the answer to the last request is kept.
 *
 * @param body - the fields, as the save sends them
 */
async function loadPreview(body: BankFilterPayload): Promise<void> {
    const current = new AbortController();
    const mine = ++latest;

    controller = current;
    previewLoading.value = true;

    try {
        const answer = await previewFilter(
            body,
            props.filter?.id ?? null,
            current.signal,
        );

        if (mine === latest) {
            shownPreview.value = answer;
            previewError.value = null;
        }
    } catch (failure) {
        if (mine === latest && !current.signal.aborted) {
            previewError.value =
                failure instanceof BankFilterPreviewError
                    ? failure.message
                    : trans('This did not work. Try again in a while.');
        }
    } finally {
        if (mine === latest) {
            previewLoading.value = false;
        }
    }
}

// The planned items, the categories and the cards are of one direction: another direction starts without them
watch(
    () => values.direction,
    (direction) => {
        values.planned_id = '';

        if (direction === 'income') {
            values.category_id = '';
            values.card = null;
        }
    },
);

// Every change asks the preview again, half a second after the last one; meanwhile the list waits and Save is off
watch(
    () => JSON.stringify(formPayload(values)),
    (body) => {
        cancelPreview();
        // An answer still on its way is for fields that changed since: it no longer counts
        latest++;
        previewLoading.value = true;
        timer = setTimeout(() => {
            timer = null;
            void loadPreview(JSON.parse(body) as BankFilterPayload);
        }, PREVIEW_DELAY_MS);
    },
);

onBeforeUnmount(() => {
    cancelPreview();
    latest++;
});

// The request of the AI comes once: the address loses `ai=1` at once, so a reload or Back never asks (and pays) again
onMounted(() => {
    if (!askAtOpen) {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.delete('ai');
    router.replace({
        url: `${url.pathname}${url.search}${url.hash}`,
        props: (current) => ({ ...current, ask_ai: false }),
        preserveState: true,
        preserveScroll: true,
    });
});

/** While the form has changes not saved, leaving the page asks first. */
const changes = useUnsavedChanges(() => formPayload(values));

/** The names of the other filters that take a movement of the list. */
const takenBy = computed((): string[] => [
    ...new Set(
        shownPreview.value.rows
            .map((row) => row.taken_by)
            .filter((name): name is string => name !== null),
    ),
]);

/** The warning under the summary while another filter takes a movement. */
const conflictLine = computed((): string | null =>
    shownPreview.value.conflicts > 0
        ? transChoice(
              '{1} Before saving: a movement is already taken by the filter :names.|[2,*] Before saving: :count movements are already taken by the filters :names.',
              shownPreview.value.conflicts,
              { names: takenBy.value.join(', ') },
          )
        : null,
);

/** Save stays off while the form is sent, while the preview of a change is awaited and while a movement is taken by another filter. */
const saveOff = computed(
    (): boolean =>
        processing.value ||
        previewLoading.value ||
        shownPreview.value.conflicts > 0,
);

/** Sends the filter: the server saves it and sorts the movements it takes. */
function save(): void {
    if (saveOff.value || !canSave.value) {
        return;
    }

    const options = {
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
            problems.value = {};
        },
        onError: (messages: Record<string, string>) => {
            problems.value = messages;
        },
        onSuccess: () => {
            changes.reset();
        },
        onFinish: () => {
            processing.value = false;
        },
    };

    if (props.filter?.id) {
        router.put(update(props.filter.id).url, formPayload(values), options);
    } else {
        router.post(store().url, formPayload(values), options);
    }
}

useSaveShortcut(save, () => canSave.value && !saveOff.value);

const { title, head } = usePageMeta({
    info: 'bank-filter',
    title: () =>
        props.filter
            ? trans('Edit the filter :name', { name: props.filter.name })
            : trans('New filter'),
    // The trail names the filter, as the board *Dalla-banca-filtro-pc*: «Filter Satispay»
    crumb: () =>
        props.filter
            ? trans('Filter :name', { name: props.filter.name })
            : trans('New filter'),
    parent: () => [
        'budget',
        { label: trans('From the bank'), href: filtersUrl },
    ],
});
</script>

<template>
    <Head :title="head" />

    <div class="flex max-w-[1120px] flex-col gap-4 wide:gap-6">
        <FmTitle :title="title">
            <template #actions>
                <FmButton
                    variant="neutral"
                    :icon="X"
                    :label="$t('Close')"
                    :href="filtersUrl"
                />
            </template>
        </FmTitle>

        <div
            class="flex flex-col gap-4 wide:grid wide:grid-cols-[minmax(0,520px)_minmax(0,1fr)] wide:items-start wide:gap-6"
        >
            <form
                class="m-0 flex flex-col gap-7 rounded-sheet bg-card p-5 shadow-card"
                novalidate
                :aria-label="title"
                @submit.prevent="save"
            >
                <fieldset
                    :disabled="!canSave"
                    class="m-0 flex flex-col gap-7 border-0 p-0"
                >
                    <FormKit
                        v-model="values.name"
                        type="text"
                        :label="$t('Filter name')"
                        name="name"
                        autocomplete="off"
                        :errors="errors(problems.name)"
                    />
                    <BankFilterCriteriaFields
                        v-model="values"
                        :options="options"
                        :problems="problems"
                        :words-locked="aiBusy"
                    >
                        <BankFilterAi
                            v-model="values"
                            :ai="ai"
                            :keep="aiKeep"
                            :auto="askAtOpen"
                            @busy="aiBusy = $event"
                            @filled="changes.reset()"
                        />
                    </BankFilterCriteriaFields>
                    <BankFilterFeeFields
                        v-if="values.direction !== 'income'"
                        v-model="values"
                        :problems="problems"
                    />
                    <BankFilterActionFields
                        v-model="values"
                        :options="options"
                        :can-pay="can_pay"
                        :problems="problems"
                    />
                </fieldset>
            </form>

            <div
                class="flex min-w-0 flex-col gap-4 wide:sticky wide:top-[calc(var(--app-header,0px)+--spacing(6))]"
            >
                <BankFilterPreviewList
                    :preview="shownPreview"
                    :fee-mode="values.fee_mode"
                    :error="previewError"
                    :loading="previewLoading"
                />
                <div
                    class="flex flex-col gap-3.5 rounded-card bg-card px-4 py-4 shadow-card wide:flex-row wide:flex-wrap wide:items-center wide:px-5"
                >
                    <div class="flex flex-[1_1_260px] flex-col gap-1">
                        <span class="text-row text-ink">{{
                            summaryLine(shownPreview.summary, values.direction)
                        }}</span>
                        <span
                            v-if="conflictLine"
                            class="flex items-start gap-1.5 text-meta text-warning"
                        >
                            <TriangleAlert
                                aria-hidden="true"
                                class="mt-px size-4 shrink-0"
                            />
                            <span class="text-ink">{{ conflictLine }}</span>
                        </span>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-2.5 wide:ml-auto"
                    >
                        <FmDeleteButton
                            v-if="filter?.id && filter.can.delete"
                            :url="destroy(filter.id).url"
                            :question="
                                $t('Delete the filter :name?', {
                                    name: filter.name,
                                })
                            "
                            :text="$t('What it sorted stays as it is.')"
                            :confirm-label="$t('Delete the filter')"
                        />
                        <FmButton
                            variant="text"
                            :href="filtersUrl"
                            data-discard-changes
                            >{{ $t('Cancel') }}</FmButton
                        >
                        <FmButton
                            v-if="canSave"
                            class="grow wide:grow-0"
                            :disabled="saveOff"
                            @click="save"
                            >{{ $t('Save the filter') }}</FmButton
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
