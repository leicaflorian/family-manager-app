<script setup lang="ts">
/**
 * *What I do too* of one file in *Import*, as the boards *Importa* and
 * *Importa (pc)*: the boxes of the expense or income (with *Already paid* /
 * *To pay* and its deadline), of the health event, of the missing cards and of
 * the policy. A ticked box shows the fields the server still needs (the
 * deadline, the title and type of a new event, the required fields of a new
 * card) and the actions *Create a new …*, *Choose another…* and *Back to the
 * proposal*. A part of the page `documents/Import`, not a component of the
 * catalogue: it writes in the choices of the file (`useImportChoices`).
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Bell,
    Building2,
    Car,
    HeartPulse,
    Package,
    Plug,
    Shield,
    Wallet,
    Wrench,
} from '@lucide/vue';
import { createReusableTemplate } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';
import FmAlsoDo from '@/components/fm/FmAlsoDo.vue';
import FmAlsoDoItem from '@/components/fm/FmAlsoDoItem.vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import type {
    ImportChoices,
    OverrideValue,
} from '@/composables/useImportChoices';
import type { AlsoSide } from '@/lib/alsoDo';
import {
    actionTexts,
    candidateOptions,
    dueChoices,
    dueInEffect,
    eventHint,
    eventLabel,
    eventSubtitle,
    eventWarnings,
    movementHint,
    movementLabel,
    movementSubtitle,
    movementWarnings,
    policyBox,
    recordHint,
    recordLabel,
    recordWarnings,
} from '@/lib/alsoDo';
import { fieldInput, isMenuField } from '@/lib/cards';

const props = defineProps<{
    /** The file, to name its fields. */
    fileId: number;
    /** What the file does too, as the server proposed it last. */
    proposal: App.Data.Reading.ProposalData;
    /** The choices of the file. */
    choices: ImportChoices;
    /** The errors of the last import about the file, by field (`also.movement`, `document.title`…). */
    errors: Record<string, string>;
    /** The people, to name the person of the expense and of the event. */
    people: App.Data.OptionData[];
    /** The expense categories, with their path. */
    expenseCategories: App.Data.OptionData[];
    /** The accounts, to name the one the expense was paid with. */
    accounts: App.Data.OptionData[];
    /** The types of a health event. */
    healthTypes: App.Data.OptionData[];
}>();

/** The icon of the box of each card. */
const RECORD_ICONS: Record<App.Enums.RecordKind, LucideIcon> = {
    organization: Building2,
    vehicle: Car,
    utility: Plug,
    policy: Shield,
    belonging: Package,
    maintenance: Wrench,
};

/** The actions of a box while it is ticked: create a new one, choose another, back to the proposal. */
const [DefineActions, ReuseActions] = createReusableTemplate<{
    side: AlsoSide;
    existing: boolean;
    candidates: App.Data.OptionData[];
}>();

/** The box whose menu to choose another is open. */
const picking = ref<AlsoSide | undefined>();

/** What the file sends as `also`: the boxes ticked. */
const ticked = computed(() => props.choices.also.value);

/** Whether the expense is to pay. */
const toPay = computed((): boolean =>
    props.proposal.movement
        ? dueInEffect(props.proposal.movement, props.choices.picks.due)
        : false,
);

/** Whether the movement is an income. */
const income = computed(
    () => props.proposal.movement?.values.kind === 'income',
);

/** The box of the policy, when the paper takes one. */
const policy = computed(() =>
    policyBox(props.proposal.policy, props.choices.picks.policy),
);

/**
 * The fields of the cards seen so far, by kind and column: once the person
 * writes in one the proposal no longer lists it, and it must stay on screen.
 */
const seenFields = reactive(new Map<string, App.Data.CardFieldData>());

/**
 * The errors of the last import about a box.
 *
 * @param keys - where they sit under `also` (`movement`, `targets.movement`)
 * @returns the messages
 */
function alsoErrors(...keys: string[]): string[] {
    return keys.flatMap((key) => props.errors[`also.${key}`] ?? []);
}

/**
 * The warnings of a box, with the errors of the server first and each line once.
 *
 * @param groups - the errors and the warnings
 * @returns the lines
 */
function lines(...groups: string[][]): string[] {
    return [...new Set(groups.flat())];
}

/**
 * Links a box to something else and closes its menu.
 *
 * @param side - the box
 * @param target - `auto`, `new` or the id chosen
 */
function setTarget(side: AlsoSide, target: 'auto' | 'new' | number): void {
    props.choices.setTarget(side, target);
    picking.value = undefined;
}

/**
 * Chooses *Already paid* or *To pay*.
 *
 * @param due - true for *To pay*
 */
function setDue(due: boolean): void {
    props.choices.picks.due = due;
}

/**
 * Takes the policy proposed, or leaves it.
 *
 * @param linked - whether the box is ticked
 */
function setPolicy(linked: boolean): void {
    props.choices.picks.policy = linked ? 'auto' : 'none';
}

/**
 * Takes the one chosen in the menu of a box.
 *
 * @param side - the box
 * @param value - the id chosen, as the menu gives it
 */
function pick(side: AlsoSide, value: unknown): void {
    const id = Number(value);

    if (Number.isInteger(id) && id > 0) {
        setTarget(side, id);
    }
}

/**
 * What a field of a box shows: what the person wrote, otherwise what was read.
 *
 * @param side - the box
 * @param field - the column
 * @param read - the value of the proposal
 * @returns the value of the input
 */
function shown(side: AlsoSide, field: string, read: unknown): OverrideValue {
    const written = props.choices.overrideOf(side, field);

    if (written !== undefined) {
        return written;
    }

    return typeof read === 'string' || typeof read === 'number' ? read : '';
}

/**
 * Writes a field of a box, when the input really changed: FormKit also tells
 * the value an input starts with, which is not something the person wrote.
 *
 * @param side - the box
 * @param field - the column
 * @param read - the value of the proposal
 * @param value - the value of the input
 */
function write(
    side: AlsoSide,
    field: string,
    read: unknown,
    value: unknown,
): void {
    const text =
        typeof value === 'string' || typeof value === 'number' ? value : '';

    if (text !== shown(side, field, read)) {
        props.choices.setOverride(side, field, text === '' ? null : text);
    }
}

/**
 * Whether a field of the event is shown: when the reading has no value, or the
 * person already wrote in it.
 *
 * @param field - `title` or `type`
 * @returns true to show it
 */
function eventFieldShown(field: 'title' | 'type'): boolean {
    const value = props.proposal.event?.values[field];

    return (
        value === null ||
        value === '' ||
        props.choices.overrideOf('event', field) !== undefined
    );
}

/**
 * The fields to write in the box of a new card: the ones it misses, and the
 * ones the person already wrote in.
 *
 * @param draft - the card of the proposal
 * @returns the fields, as the card form draws them
 */
function recordFields(
    draft: App.Data.Reading.RecordDraftData,
): App.Data.CardFieldData[] {
    for (const field of draft.fields) {
        seenFields.set(`${draft.kind}.${field.column}`, field);
    }

    const written = [...seenFields.entries()]
        .filter(
            ([key, field]) =>
                key.startsWith(`${draft.kind}.`) &&
                !draft.fields.some((own) => own.column === field.column) &&
                props.choices.overrideOf(draft.kind, field.column) !==
                    undefined,
        )
        .map(([, field]) => field);

    return [...draft.fields, ...written];
}
</script>

<template>
    <DefineActions v-slot="{ side, existing, candidates }">
        <FormKit
            v-if="picking === side"
            type="autocomplete"
            open-on-click
            :label="actionTexts(side, existing, income).menu"
            :name="`pick-${side}`"
            :options="candidates"
            @input="pick(side, $event)"
        />
        <FmButton
            v-if="existing && choices.targetOf(side) !== 'new'"
            variant="text"
            @click="setTarget(side, 'new')"
        >
            {{ actionTexts(side, existing, income).create }}
        </FmButton>
        <FmButton
            v-if="candidates.length > 0 && picking !== side"
            variant="text"
            @click="picking = side"
        >
            {{ actionTexts(side, existing, income).choose }}
        </FmButton>
        <FmButton
            v-if="choices.targetOf(side) !== 'auto'"
            variant="text"
            @click="setTarget(side, 'auto')"
        >
            {{ $t('Back to the proposal') }}
        </FmButton>
    </DefineActions>

    <FmAlsoDo v-if="ticked">
        <FmAlsoDoItem
            v-if="proposal.movement"
            :model-value="ticked.movement"
            :label="movementLabel(proposal.movement, toPay)"
            :hint="
                movementHint(
                    proposal.movement,
                    {
                        ticked: ticked.movement,
                        due: toPay,
                        target: choices.targetOf('movement'),
                    },
                    movementSubtitle(proposal.movement, {
                        categories: expenseCategories,
                        people,
                    }),
                )
            "
            :warnings="
                lines(
                    alsoErrors('movement', 'targets.movement'),
                    movementWarnings(proposal.movement, {
                        ticked: ticked.movement,
                        due: toPay,
                    }),
                )
            "
            :icon="Wallet"
            @update:model-value="choices.setTicked('movement', $event)"
        >
            <template
                v-if="proposal.movement.due && !proposal.movement.duplicate"
            >
                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="sr-only">
                        {{ $t('When it is paid') }}
                    </legend>
                    <label
                        v-for="choice in dueChoices(
                            proposal.movement,
                            toPay,
                            accounts,
                        )"
                        :key="String(choice.due)"
                        class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-[10px] bg-white px-3 text-[15px]"
                        :class="
                            choice.checked
                                ? 'border-2 border-action'
                                : 'border-[1.5px] border-edge'
                        "
                    >
                        <input
                            type="radio"
                            :name="`due-${fileId}`"
                            class="m-0 size-[18px] shrink-0 accent-action"
                            :checked="choice.checked"
                            @change="setDue(choice.due)"
                        />
                        {{ choice.label }}
                    </label>
                </fieldset>
                <FormKit
                    v-if="toPay"
                    :id="`also-due-date-${fileId}`"
                    type="datepicker"
                    name="due_date"
                    :label="$t('Deadline')"
                    :model-value="
                        shown(
                            'movement',
                            'due_date',
                            proposal.movement.due.date,
                        )
                    "
                    @update:model-value="
                        write(
                            'movement',
                            'due_date',
                            proposal.movement.due.date,
                            $event,
                        )
                    "
                />
            </template>
            <template #actions>
                <ReuseActions
                    side="movement"
                    :existing="proposal.movement.duplicate !== null"
                    :candidates="candidateOptions(proposal.movement.candidates)"
                />
            </template>
        </FmAlsoDoItem>

        <FmAlsoDoItem
            v-if="proposal.event"
            :model-value="ticked.event"
            :label="eventLabel(proposal.event)"
            :hint="
                eventHint(
                    proposal.event,
                    {
                        ticked: ticked.event,
                        target: choices.targetOf('event'),
                    },
                    eventSubtitle(proposal.event, people),
                )
            "
            :warnings="
                lines(
                    alsoErrors('event', 'targets.event'),
                    eventWarnings(proposal.event, ticked.event),
                )
            "
            :icon="HeartPulse"
            @update:model-value="choices.setTicked('event', $event)"
        >
            <template v-if="!proposal.event.existing">
                <FormKit
                    v-if="eventFieldShown('title')"
                    type="text"
                    name="event_title"
                    :label="$t('Title of the event')"
                    :model-value="
                        shown('event', 'title', proposal.event.values.title)
                    "
                    @update:model-value="
                        write(
                            'event',
                            'title',
                            proposal.event.values.title,
                            $event,
                        )
                    "
                />
                <FormKit
                    v-if="eventFieldShown('type')"
                    type="dropdown"
                    name="event_type"
                    :label="$t('Type of the event')"
                    :options="healthTypes"
                    :model-value="
                        shown('event', 'type', proposal.event.values.type)
                    "
                    @update:model-value="
                        write(
                            'event',
                            'type',
                            proposal.event.values.type,
                            $event,
                        )
                    "
                />
                <p
                    v-if="proposal.event.reminder"
                    class="m-0 flex items-center gap-1.5 text-[13px] text-ink-soft"
                >
                    <FmIcon :icon="Bell" :size="14" />
                    {{ proposal.event.reminder }}
                </p>
            </template>
            <template #actions>
                <ReuseActions
                    side="event"
                    :existing="proposal.event.existing !== null"
                    :candidates="candidateOptions(proposal.event.candidates)"
                />
            </template>
        </FmAlsoDoItem>

        <FmAlsoDoItem
            v-for="draft in proposal.records"
            :key="draft.kind"
            :model-value="ticked.records.includes(draft.kind)"
            :label="recordLabel(draft)"
            :hint="
                recordHint(draft, {
                    ticked: ticked.records.includes(draft.kind),
                    target: choices.targetOf(draft.kind),
                })
            "
            :warnings="
                lines(
                    alsoErrors(
                        `records.${draft.kind}`,
                        `targets.records.${draft.kind}`,
                    ),
                    recordWarnings(draft, ticked.records.includes(draft.kind)),
                )
            "
            :icon="RECORD_ICONS[draft.kind]"
            @update:model-value="choices.setTicked(draft.kind, $event)"
        >
            <template v-if="!draft.existing">
                <FormKit
                    v-for="field in recordFields(draft)"
                    :key="field.column"
                    :type="fieldInput(field.kind)"
                    :name="`${draft.kind}_${field.column}`"
                    :label="$t(field.label)"
                    :options="
                        field.kind === 'choice' || isMenuField(field.kind)
                            ? field.options
                            : undefined
                    "
                    :open-on-click="isMenuField(field.kind) ? true : undefined"
                    :model-value="
                        shown(
                            draft.kind,
                            field.column,
                            draft.values[field.column],
                        )
                    "
                    @update:model-value="
                        write(
                            draft.kind,
                            field.column,
                            draft.values[field.column],
                            $event,
                        )
                    "
                />
                <p
                    v-if="draft.reminder"
                    class="m-0 flex items-center gap-1.5 text-[13px] text-ink-soft"
                >
                    <FmIcon :icon="Bell" :size="14" />
                    {{ draft.reminder }}
                </p>
            </template>
            <template #actions>
                <ReuseActions
                    :side="draft.kind"
                    :existing="draft.existing !== null"
                    :candidates="candidateOptions(draft.candidates)"
                />
            </template>
        </FmAlsoDoItem>

        <FmAlsoDoItem
            v-if="policy"
            :model-value="policy.ticked"
            :label="policy.label"
            :hint="policy.hint"
            :warnings="lines(alsoErrors('policy'), policy.warnings)"
            :disabled="policy.disabled"
            :icon="Shield"
            @update:model-value="setPolicy($event)"
        />
    </FmAlsoDo>
</template>
