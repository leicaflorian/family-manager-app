<script setup lang="ts">
/**
 * The filters of the *Agenda*: the period (the months the server offers), the
 * person and the areas. On the computer (`toolbar`) they are a bar above the
 * list, as the board *Agenda-pc* draws it: the period as a segmented control,
 * the people as round buttons and, on the right, *All areas*, which opens the
 * menu of the areas. On the phone (`sheet`) three buttons say what is chosen
 * («1 month», «Everyone», «All areas») and each opens the panel from below with
 * all the filters, which ends with *Show 10 entries*. An area is a button that
 * turns it off: its name is struck through and its number stays, so the
 * hidden entries stay counted. The page keeps the filters: the component only
 * says what the person chose.
 *
 * *Reminders* has no period: in its place `views` chooses the list (*To do* and
 * *Done*), a segmented control on the computer and two buttons beside the
 * others on the phone, which choose without opening the panel; and with
 * `searchLabel` the toolbar has a search box before the areas.
 *
 * @group forms
 */
import { ChevronDown, UsersRound } from '@lucide/vue';
import { createReusableTemplate } from '@vueuse/core';
import { trans, transChoice } from 'laravel-vue-i18n';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, useId } from 'vue';
import type { Area } from '@/lib/areas';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { cn } from '@/lib/utils';
import FmAvatar from './FmAvatar.vue';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';
import FmSearchField from './FmSearchField.vue';

/** Where the filters are shown. */
export type AgendaFiltersVariant = 'sheet' | 'toolbar';

/** The filters in use: the period only where there is one. */
export type AgendaFilterState = Pick<
    App.Data.AgendaFiltersData,
    'person_id' | 'hidden_areas'
> & { months?: number };

/** The lists the person chooses between, in place of the period, and the name of the choice. */
export type AgendaFilterViews = {
    /** The name of the choice, for example «Status». */
    label: string;
    /** The lists: the value the page knows them by and the text of the button. */
    options: { value: string; label: string }[];
};

/** A person of the family the entries can be filtered by. */
export type AgendaFilterPerson = Pick<
    App.Data.PersonData,
    'id' | 'first_name' | 'initials' | 'photo'
>;

const {
    variant = 'toolbar',
    filters,
    people,
    periods = [],
    views,
    searchLabel,
} = defineProps<{
    /** A bar above the list on the computer, buttons and a panel from below on the phone. */
    variant?: AgendaFiltersVariant;
    /** The filters in use. */
    filters: AgendaFilterState;
    /** The people of the family. */
    people: AgendaFilterPerson[];
    /** The areas, in order, with the name the server gives them. */
    areas: App.Data.OptionData[];
    /** The periods the person can choose, in months, as the server offers them; none, no period. */
    periods?: number[];
    /** The lists to choose between in place of the period, chosen with `v-model:view`. */
    views?: AgendaFilterViews;
    /** What is searched: with it the toolbar has a search box, filled with `v-model:search`. */
    searchLabel?: string;
    /** How many entries each area has for the person chosen, hidden areas too. */
    counts: Record<string, number>;
    /** How many entries the list shows with these filters. */
    shown: number;
}>();

const emit = defineEmits<{
    /** The person chose a period: 1, 3, 6 or 12 months. */
    months: [months: number];
    /** The person chose someone, or everyone with `null`. */
    person: [id: number | null];
    /** The person turned an area off, or on again. */
    area: [area: string];
    /** The person took the filters off. */
    reset: [];
}>();

/** The panel is open (only for `sheet`). */
const open = defineModel<boolean>('open', { default: false });

/** The list chosen among `views`. */
const view = defineModel<string>('view', { default: '' });

/** The text typed in the search box. */
const search = defineModel<string>('search', { default: '' });

/** The period can be chosen. */
const hasPeriod = computed(
    () => periods.length > 0 && filters.months !== undefined,
);

/** Makes the radio groups of this instance its own: a toolbar and a sheet may both be on the page. */
const id = useId();

const [DefinePeriod, ReusePeriod] = createReusableTemplate();
const [DefineViews, ReuseViews] = createReusableTemplate();
const [DefinePeople, ReusePeople] = createReusableTemplate();
const [DefineAreas, ReuseAreas] = createReusableTemplate();

/** The toolbar is smaller than the panel of the phone. */
const compact = computed(() => variant === 'toolbar');

/**
 * The length of a period, as the person reads it.
 *
 * @param months - the months
 * @returns for example «1 month», «3 months»
 */
function periodText(months: number): string {
    return transChoice('{1} :count month|[2,*] :count months', months, {
        count: String(months),
    });
}

/** The person chosen, or undefined for everyone. */
const chosenPerson = computed(() =>
    people.find((person) => person.id === filters.person_id),
);

/** What the button of the areas says: every area, or how many are off. */
const areasText = computed((): string => {
    const off = filters.hidden_areas.length;

    return off === 0
        ? trans('All areas')
        : transChoice('{1} :count area off|[2,*] :count areas off', off, {
              count: String(off),
          });
});

/**
 * Whether an area is shown.
 *
 * @param area - the area
 * @returns false when the person turned it off
 */
function isOn(area: string): boolean {
    return !filters.hidden_areas.includes(area);
}

/**
 * The classes of the icon tile of an area.
 *
 * @param area - the area
 * @returns the soft background and the colour of the area
 */
function tile(area: string): string {
    return areaTile[areaOf(area) ?? ('general' as Area)];
}

/**
 * The classes of a segment of the period.
 *
 * @param chosen - whether it is the period in use
 * @returns white and bold when chosen
 */
function segment(chosen: boolean): string {
    return cn(
        'relative flex cursor-pointer items-center justify-center text-sm whitespace-nowrap has-focus-visible:outline-2 has-focus-visible:outline-action',
        compact.value
            ? 'h-8 rounded-tag px-3'
            : 'h-[38px] flex-1 rounded-[9px]',
        chosen
            ? 'bg-card font-bold shadow-raised'
            : 'font-medium text-ink-soft',
    );
}

/**
 * The border and the colours of a round button: of a person, or of a list on the phone.
 *
 * @param chosen - whether it is the one chosen
 * @returns ringed in blue when chosen
 */
function ring(chosen: boolean): string {
    return chosen
        ? cn(
              'border-[1.5px] border-action bg-action-soft font-bold text-action-dark',
          )
        : cn('border border-edge bg-card font-medium');
}

/**
 * The classes of the round button of a person.
 *
 * @param chosen - whether it is the person chosen
 * @returns ringed in blue when chosen
 */
function pill(chosen: boolean): string {
    return cn(
        'relative flex cursor-pointer items-center rounded-full text-sm whitespace-nowrap has-focus-visible:outline-2 has-focus-visible:outline-action',
        compact.value
            ? 'h-9 gap-[7px] pr-3.5 pl-[5px]'
            : 'h-10 gap-2 pr-3 pl-[7px]',
        ring(chosen),
    );
}

/**
 * The classes of a button of a list on the phone.
 *
 * @param chosen - whether it is the list shown
 * @returns ringed in blue when chosen, as the round button of a person
 */
function viewChip(chosen: boolean): string {
    return cn(
        'flex h-9 shrink-0 cursor-pointer items-center rounded-full pr-2.5 pl-3 text-sm whitespace-nowrap text-ink',
        ring(chosen),
    );
}

/** The classes of the buttons of the phone and of the button of the areas. */
const chip = cn(
    'flex h-9 shrink-0 cursor-pointer items-center gap-1.5 border border-edge bg-card text-sm font-medium whitespace-nowrap text-ink',
);
</script>

<template>
    <DefinePeriod>
        <div
            role="radiogroup"
            :aria-label="$t('Period')"
            class="flex rounded-lg bg-divider"
            :class="compact ? 'gap-0.5 p-[3px]' : 'gap-1 p-1'"
        >
            <label
                v-for="months in periods"
                :key="months"
                :class="segment(filters.months === months)"
            >
                <input
                    type="radio"
                    :name="`period-${id}`"
                    class="absolute size-px opacity-0"
                    :checked="filters.months === months"
                    @change="emit('months', months)"
                />
                {{ periodText(months) }}
            </label>
        </div>
    </DefinePeriod>

    <DefineViews>
        <div
            v-if="views"
            role="radiogroup"
            :aria-label="views.label"
            class="flex gap-0.5 rounded-lg bg-divider p-[3px]"
        >
            <label
                v-for="option in views.options"
                :key="option.value"
                :class="segment(view === option.value)"
            >
                <input
                    type="radio"
                    :name="`view-${id}`"
                    class="absolute size-px opacity-0"
                    :checked="view === option.value"
                    @change="view = option.value"
                />
                {{ option.label }}
            </label>
        </div>
    </DefineViews>

    <DefinePeople>
        <div
            role="radiogroup"
            :aria-label="$t('Person')"
            class="flex flex-wrap"
            :class="compact ? 'gap-1.5' : 'gap-2'"
        >
            <label :class="pill(filters.person_id === null)">
                <input
                    type="radio"
                    :name="`person-${id}`"
                    class="absolute size-px opacity-0"
                    :checked="filters.person_id === null"
                    @change="emit('person', null)"
                />
                <span
                    class="flex size-[26px] items-center justify-center rounded-full bg-divider text-ink-soft"
                    :title="$t('Whole family')"
                >
                    <FmIcon :icon="UsersRound" :size="15" />
                </span>
                {{ $t('Everyone') }}
            </label>
            <label
                v-for="person in people"
                :key="person.id"
                :class="pill(filters.person_id === person.id)"
            >
                <input
                    type="radio"
                    :name="`person-${id}`"
                    class="absolute size-px opacity-0"
                    :checked="filters.person_id === person.id"
                    @change="emit('person', person.id)"
                />
                <FmAvatar
                    :initials="person.initials"
                    :photo="person.photo"
                    :size="26"
                />
                {{ person.first_name }}
            </label>
        </div>
    </DefinePeople>

    <DefineAreas>
        <div class="flex flex-col gap-1">
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-bold text-ink-soft">{{
                    $t('Areas')
                }}</span>
                <button
                    v-if="compact"
                    type="button"
                    class="cursor-pointer border-0 bg-transparent p-0 text-sm font-semibold text-action"
                    @click="emit('reset')"
                >
                    {{ $t('Clear filters') }}
                </button>
            </div>
            <span class="text-[13px] text-ink-soft">{{
                $t('Tap an area to hide it; the number stays.')
            }}</span>
            <div
                class="-mx-1.5 grid gap-x-1.5"
                :class="compact ? 'grid-cols-1' : 'grid-cols-2'"
            >
                <button
                    v-for="area in areas"
                    :key="area.value"
                    type="button"
                    :aria-pressed="isOn(area.value)"
                    class="flex min-h-11 min-w-0 cursor-pointer items-center gap-2.5 rounded-md border-0 bg-transparent px-1.5 text-left hover:bg-action-soft"
                    @click="emit('area', area.value)"
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-tag"
                        :class="tile(area.value)"
                    >
                        <FmIcon :icon="areaIcon(area.value)" :size="16" />
                    </span>
                    <span
                        class="min-w-0 grow text-sm leading-[1.2] font-medium"
                        :class="
                            isOn(area.value)
                                ? 'text-ink'
                                : 'text-ink-faint line-through'
                        "
                        >{{ area.label }}</span
                    >
                    <span
                        class="text-[13px] font-semibold"
                        :class="
                            isOn(area.value)
                                ? 'text-ink-soft'
                                : 'text-ink-faint'
                        "
                        >{{ counts[area.value] ?? 0 }}</span
                    >
                </button>
            </div>
        </div>
    </DefineAreas>

    <div
        v-if="variant === 'toolbar'"
        class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-card bg-card px-3 py-2.5"
    >
        <ReusePeriod v-if="hasPeriod" />
        <ReuseViews v-else />
        <span
            v-if="hasPeriod || views"
            aria-hidden="true"
            class="h-6 w-px bg-edge-soft"
        />
        <ReusePeople />
        <span class="grow" />
        <FmSearchField
            v-if="searchLabel"
            v-model="search"
            small
            :label="searchLabel"
            class="min-w-40 shrink basis-[220px]"
        />
        <PopoverRoot>
            <PopoverTrigger :class="cn(chip, 'gap-2 rounded-md px-3')">
                {{ areasText }}
                <FmIcon class="text-ink-soft" :icon="ChevronDown" :size="18" />
            </PopoverTrigger>
            <PopoverPortal>
                <PopoverContent
                    align="end"
                    :side-offset="6"
                    class="z-30 w-80 rounded-xl bg-card p-4 text-ink shadow-menu duration-150 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:animate-none"
                >
                    <ReuseAreas />
                </PopoverContent>
            </PopoverPortal>
        </PopoverRoot>
    </div>

    <template v-else>
        <div
            role="group"
            :aria-label="$t('Filters')"
            class="-mx-4 flex gap-2 overflow-x-auto px-4"
        >
            <template v-if="views">
                <button
                    v-for="option in views.options"
                    :key="option.value"
                    type="button"
                    :aria-pressed="view === option.value"
                    :class="viewChip(view === option.value)"
                    @click="view = option.value"
                >
                    {{ option.label }}
                </button>
            </template>
            <button
                v-if="hasPeriod"
                type="button"
                aria-haspopup="dialog"
                :class="cn(chip, 'rounded-full pr-2.5 pl-3')"
                @click="open = true"
            >
                {{ periodText(filters.months ?? 0)
                }}<FmIcon
                    class="text-ink-soft"
                    :icon="ChevronDown"
                    :size="18"
                />
            </button>
            <button
                type="button"
                aria-haspopup="dialog"
                :class="cn(chip, 'rounded-full pr-2.5 pl-1')"
                @click="open = true"
            >
                <FmAvatar
                    v-if="chosenPerson"
                    :initials="chosenPerson.initials"
                    :photo="chosenPerson.photo"
                    :size="28"
                />
                <span
                    v-else
                    class="flex size-7 items-center justify-center rounded-full bg-divider text-ink-soft"
                    :title="$t('Whole family')"
                >
                    <FmIcon :icon="UsersRound" :size="16" />
                </span>
                {{ chosenPerson?.first_name ?? $t('Everyone')
                }}<FmIcon
                    class="text-ink-soft"
                    :icon="ChevronDown"
                    :size="18"
                />
            </button>
            <button
                type="button"
                aria-haspopup="dialog"
                :class="cn(chip, 'rounded-full pr-2.5 pl-3')"
                @click="open = true"
            >
                {{ areasText
                }}<FmIcon
                    class="text-ink-soft"
                    :icon="ChevronDown"
                    :size="18"
                />
            </button>
        </div>

        <FmBottomSheet v-model:open="open" :title="$t('Filter the agenda')">
            <div class="flex flex-col gap-4">
                <div class="flex justify-end">
                    <button
                        type="button"
                        class="cursor-pointer border-0 bg-transparent p-0 text-[15px] font-semibold text-action"
                        @click="emit('reset')"
                    >
                        {{ $t('Clear filters') }}
                    </button>
                </div>
                <fieldset
                    v-if="hasPeriod"
                    class="m-0 flex flex-col gap-2 border-0 p-0"
                >
                    <legend class="mb-2 p-0 text-sm font-bold text-ink-soft">
                        {{ $t('Period') }}
                    </legend>
                    <ReusePeriod />
                </fieldset>
                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="mb-2 p-0 text-sm font-bold text-ink-soft">
                        {{ $t('Person') }}
                    </legend>
                    <ReusePeople />
                </fieldset>
                <ReuseAreas />
                <FmButton wide @click="open = false">
                    {{
                        transChoice(
                            '[0,1] Show :count entry|[2,*] Show :count entries',
                            shown,
                            { count: String(shown) },
                        )
                    }}
                </FmButton>
            </div>
        </FmBottomSheet>
    </template>
</template>
