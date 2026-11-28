<script setup lang="ts">
/**
 * The AI buttons of the section *Matches* of the form of a bank filter, as the boards *Dalla-banca-filtro* draw them:
 * *Write it with the AI* and *Improve the filter*, with the line that says the AI reads only the text of the
 * movements, never the amounts. The AI is asked only when a button is pressed (or, with `auto`, once when the form of a
 * group without a proposal opens, which is what *Create the filter* asked for): it reads the movements the filter
 * takes now and proposes the words; in the fill mode (`auto`) also the title, the category and the organization. Its
 * sentence stays under the buttons with *Undo the change*, which puts back what it replaced. An organization the
 * family does not have is only named: the filter saves without it and the organization is added later. Hidden without
 * the AI and on an advanced filter (an expression, not words). `busy` tells the form to lock the words while a call
 * is on its way; `filled` says the automatic fill ended, so the form takes the filled values as its start.
 * *Improve the filter* also sends the movements taken out with *It is not of this group* (`drop`), and the form asks
 * it by itself (`improve()`) when it found no way to leave one out without the AI.
 */
import { LoaderCircle, Sparkles, Undo2, WandSparkles } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed, onMounted, ref, watch } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import type { BankFilterFormValues } from '@/lib/bankFilters';
import { BankFilterSuggestError, suggestFilter } from '@/lib/bankFilters';

const {
    ai = false,
    keep,
    auto = false,
    drop = [],
} = defineProps<{
    /** Whether the AI can read: without it nothing is shown. */
    ai?: boolean;
    /** The ids of the movements the filter takes now, which the AI reads (the most it can read is the first 50). */
    keep: number[];
    /** Asks the AI once, in the fill mode, when the form opens: a group without a proposal. */
    auto?: boolean;
    /** The ids of the movements taken out with *It is not of this group*, which the filter must leave. */
    drop?: number[];
}>();

const emit = defineEmits<{
    /** A call starts (true) or ends (false): the words of the form are locked meanwhile. */
    busy: [busy: boolean];
    /** The automatic fill of a form that was just opened ended and wrote its proposal. */
    filled: [];
}>();

/** The fields of the form, changed in place. */
const values = defineModel<BankFilterFormValues>({ required: true });

/** What the AI replaced, to put it back with *Undo the change*. */
type Before = Pick<
    BankFilterFormValues,
    'contains' | 'excludes' | 'title' | 'category_id' | 'organization_id'
>;

/** The call that is on its way, null when none. */
const busy = ref<'words' | 'fill' | null>(null);

/** The sentence of the AI for the last change, null before any. */
const note = ref<string | null>(null);

/** The organization the AI named that the family does not have, null when none. */
const newOrganization = ref<string | null>(null);

/** Why the AI could not write the filter, null when it could. */
const failure = ref<string | null>(null);

/** What was there before the last change, null when nothing can be undone. */
const before = ref<Before | null>(null);

/** The movements to read: the AI takes at most fifty. */
const movements = computed((): number[] => keep.slice(0, 50));

/** Whether a call can start: the AI reads movements, so there must be some. */
const canAsk = computed(
    (): boolean => busy.value === null && movements.value.length > 0,
);

/**
 * Asks the AI and writes its proposal in the form, keeping what it replaced.
 *
 * @param mode - `words` for the words only, `fill` for the title, category and organization too
 * @param opening - the automatic fill of a form just opened
 * @param leave - the movements the filter must leave (*Improve the filter*)
 */
async function ask(
    mode: 'words' | 'fill',
    opening = false,
    leave: number[] = [],
): Promise<void> {
    if (!canAsk.value) {
        return;
    }

    busy.value = mode;
    failure.value = null;

    try {
        const suggestion = await suggestFilter(
            movements.value,
            leave.slice(0, 50),
            mode,
        );
        const snapshot: Before = {
            contains: [...values.value.contains],
            excludes: [...values.value.excludes],
            title: values.value.title,
            category_id: values.value.category_id,
            organization_id: values.value.organization_id,
        };

        values.value.contains = [...suggestion.contains];
        values.value.excludes = [...suggestion.excludes];

        if (mode === 'fill') {
            if (suggestion.title) {
                values.value.title = suggestion.title;
            }

            if (
                suggestion.category_id !== null &&
                values.value.direction !== 'income'
            ) {
                values.value.category_id = String(suggestion.category_id);
            }

            if (suggestion.organization_id !== null) {
                values.value.organization_id = String(
                    suggestion.organization_id,
                );
            }
        }

        before.value = snapshot;

        if (opening) {
            emit('filled');
        }

        note.value = suggestion.explanation || trans('I changed the filter.');
        newOrganization.value = suggestion.organization_is_new
            ? suggestion.organization
            : null;
    } catch (error) {
        failure.value =
            error instanceof BankFilterSuggestError
                ? error.message
                : trans('This did not work. Try again in a while.');
    } finally {
        busy.value = null;
    }
}

/** Puts back the words and fields the AI replaced. */
function undo(): void {
    if (before.value === null) {
        return;
    }

    Object.assign(values.value, {
        contains: [...before.value.contains],
        excludes: [...before.value.excludes],
        title: before.value.title,
        category_id: before.value.category_id,
        organization_id: before.value.organization_id,
    });
    before.value = null;
    note.value = null;
    newOrganization.value = null;
}

/**
 * *Improve the filter*: the words, with the movements taken out that the filter must leave.
 *
 * @returns done when the proposal is written (or the failure said)
 */
function improve(): Promise<void> {
    return ask('words', false, drop);
}

defineExpose({ improve });

watch(busy, (call) => emit('busy', call !== null));

onMounted(() => {
    if (auto && ai && values.value.pattern === null) {
        void ask('fill', true);
    }
});
</script>

<template>
    <div
        v-if="ai && values.pattern === null"
        class="mb-3.5 flex flex-col gap-2"
    >
        <div class="flex flex-wrap gap-2">
            <FmButton
                variant="neutral"
                row-action
                :icon="busy === 'words' ? LoaderCircle : Sparkles"
                :disabled="!canAsk"
                :aria-busy="busy === 'words'"
                @click="ask('words')"
                >{{ $t('Write it with the AI') }}</FmButton
            >
            <FmButton
                variant="neutral"
                row-action
                :icon="WandSparkles"
                :disabled="!canAsk"
                @click="improve"
                >{{ $t('Improve the filter') }}</FmButton
            >
        </div>
        <span class="text-meta text-ink-soft">{{
            movements.length === 0
                ? $t(
                      'The filter takes no movement yet, so the AI has nothing to read.',
                  )
                : $t(
                      'The AI reads only the text of the movements, never the amounts.',
                  )
        }}</span>
        <p v-if="busy" role="status" class="m-0 text-body-sm text-ink-soft">
            {{ $t('The AI is writing…') }}
        </p>
        <div
            v-else-if="note"
            role="status"
            class="flex flex-wrap items-center gap-2 rounded-card bg-action-soft px-3 py-2.5"
        >
            <span class="flex min-w-0 flex-[1_1_200px] flex-col gap-1">
                <span class="text-body-sm text-ink">{{ note }}</span>
                <span v-if="newOrganization" class="text-body-sm text-ink">{{
                    $t(
                        'The AI suggests a new organization: :name. You can save the filter without it and add the organization later.',
                        { name: newOrganization },
                    )
                }}</span>
            </span>
            <FmButton
                variant="neutral"
                row-action
                :icon="Undo2"
                @click="undo"
                >{{ $t('Undo the change') }}</FmButton
            >
        </div>
        <p v-if="failure" role="alert" class="m-0 text-body-sm text-overdue">
            {{ failure }}
        </p>
    </div>
</template>
