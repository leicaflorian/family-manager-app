<script setup lang="ts">
/**
 * The filters of the *Agenda*: the period (the months the server offers), the person
 * and the areas. An area is a button that turns it off: its name is struck
 * through and its number stays, so the hidden entries stay counted. On the
 * phone (`sheet`) they are a panel from below that ends with *Show 10
 * entries*; on the computer (`column`) a column beside the list. The page
 * keeps the filters: the component only says what the person chose.
 *
 * @group forms
 */
import { UsersRound } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, useId } from 'vue';
import type { Area } from '@/lib/areas';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { cn } from '@/lib/utils';
import FmAvatar from './FmAvatar.vue';
import FmBottomSheet from './FmBottomSheet.vue';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';

/** Where the filters are shown. */
export type AgendaFiltersVariant = 'sheet' | 'column';

/** A person of the family the entries can be filtered by. */
export type AgendaFilterPerson = Pick<
    App.Data.PersonData,
    'id' | 'first_name' | 'initials' | 'photo'
>;

const { variant = 'column', filters } = defineProps<{
    /** A panel from below on the phone, a column on the computer. */
    variant?: AgendaFiltersVariant;
    /** The filters in use. */
    filters: App.Data.AgendaFiltersData;
    /** The people of the family. */
    people: AgendaFilterPerson[];
    /** The areas, in order, with the name the server gives them. */
    areas: App.Data.OptionData[];
    /** The periods the person can choose, in months, as the server offers them. */
    periods: number[];
    /** How many entries each area has for the person chosen, hidden areas too. */
    counts: Record<string, number>;
    /** How many entries the list shows with these filters. */
    shown: number;
}>();

const emit = defineEmits<{
    /** The person chose a period: 1, 3, 6 or 12 months. */
    months: [months: number];
    /** The person chose someone, or the whole family with `null`. */
    person: [id: number | null];
    /** The person turned an area off, or on again. */
    area: [area: string];
    /** The person took the filters off. */
    reset: [];
}>();

/** The panel is open (only for `sheet`). */
const open = defineModel<boolean>('open', { default: false });

/** Makes the radio groups of this instance its own: a column and a sheet may both be on the page. */
const id = useId();

/** The panel on the phone, a plain box on the computer, with what each needs. */
const wrapper = computed(() =>
    variant === 'sheet'
        ? {
              component: FmBottomSheet,
              props: {
                  title: trans('Filter the agenda'),
                  open: open.value,
                  'onUpdate:open': (value: boolean) => (open.value = value),
              },
          }
        : { component: 'div', props: {} },
);

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

/** The chip of the period or of the person: ringed in blue when chosen. */
const chip = (chosen: boolean): string =>
    chosen
        ? cn('border-2 border-action bg-action-soft font-bold')
        : cn('border-[1.5px] border-edge bg-white font-medium');
</script>

<template>
    <component :is="wrapper.component" v-bind="wrapper.props">
        <div
            class="flex flex-col gap-4"
            :class="variant === 'column' ? 'rounded-[20px] bg-white p-5' : ''"
        >
            <div class="flex items-center justify-between gap-3">
                <h2
                    v-if="variant === 'column'"
                    class="m-0 text-[17px] font-bold"
                >
                    {{ $t('Filter the agenda') }}
                </h2>
                <span v-else />
                <button
                    type="button"
                    class="cursor-pointer border-0 bg-transparent p-0 text-[15px] font-semibold text-action"
                    @click="emit('reset')"
                >
                    {{ $t('Clear filters') }}
                </button>
            </div>

            <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                <legend class="mb-2 p-0 text-sm font-bold text-ink-soft">
                    {{ $t('Period') }}
                </legend>
                <div
                    role="radiogroup"
                    :aria-label="$t('Period')"
                    class="flex gap-1 rounded-xl bg-divider p-1"
                >
                    <label
                        v-for="months in periods"
                        :key="months"
                        class="relative flex h-[38px] flex-1 cursor-pointer items-center justify-center rounded-[9px] text-sm whitespace-nowrap has-focus-visible:outline-2 has-focus-visible:outline-action"
                        :class="
                            filters.months === months
                                ? 'bg-white font-bold shadow-[0_1px_3px_rgba(24,33,47,0.12)]'
                                : 'font-medium text-ink-soft'
                        "
                    >
                        <input
                            type="radio"
                            :name="`period-${id}`"
                            class="absolute size-px opacity-0"
                            :checked="filters.months === months"
                            @change="emit('months', months)"
                        />
                        {{
                            transChoice(
                                '{1} :count month|[2,*] :count months',
                                months,
                                { count: String(months) },
                            )
                        }}
                    </label>
                </div>
            </fieldset>

            <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                <legend class="mb-2 p-0 text-sm font-bold text-ink-soft">
                    {{ $t('Person') }}
                </legend>
                <div
                    role="radiogroup"
                    :aria-label="$t('Person')"
                    class="flex flex-wrap gap-2"
                >
                    <label
                        class="relative flex h-10 cursor-pointer items-center gap-2 rounded-full pr-3 pl-[7px] text-sm has-focus-visible:outline-2 has-focus-visible:outline-action"
                        :class="chip(filters.person_id === null)"
                    >
                        <input
                            type="radio"
                            :name="`person-${id}`"
                            class="absolute size-px opacity-0"
                            :checked="filters.person_id === null"
                            @change="emit('person', null)"
                        />
                        <span
                            class="flex size-[26px] items-center justify-center rounded-full bg-divider text-ink-soft"
                        >
                            <FmIcon :icon="UsersRound" :size="15" />
                        </span>
                        {{ $t('Whole family') }}
                    </label>
                    <label
                        v-for="person in people"
                        :key="person.id"
                        class="relative flex h-10 cursor-pointer items-center gap-2 rounded-full pr-3 pl-[7px] text-sm has-focus-visible:outline-2 has-focus-visible:outline-action"
                        :class="chip(filters.person_id === person.id)"
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
            </fieldset>

            <div class="flex flex-col gap-1">
                <span class="text-sm font-bold text-ink-soft">{{
                    $t('Areas')
                }}</span>
                <span class="text-[13px] text-ink-soft">{{
                    $t('Tap an area to hide it; the number stays.')
                }}</span>
                <div
                    class="-mx-1.5 grid gap-x-1.5"
                    :class="variant === 'sheet' ? 'grid-cols-2' : 'grid-cols-1'"
                >
                    <button
                        v-for="area in areas"
                        :key="area.value"
                        type="button"
                        :aria-pressed="isOn(area.value)"
                        class="flex min-h-11 min-w-0 cursor-pointer items-center gap-2.5 rounded-[10px] border-0 bg-transparent px-1.5 text-left"
                        @click="emit('area', area.value)"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg"
                            :class="tile(area.value)"
                        >
                            <FmIcon :icon="areaIcon(area.value)" :size="16" />
                        </span>
                        <span
                            class="min-w-0 grow text-sm leading-[1.2] font-medium"
                            :class="
                                isOn(area.value)
                                    ? 'text-ink'
                                    : 'text-[#8A94A3] line-through'
                            "
                            >{{ area.label }}</span
                        >
                        <span
                            class="text-[13px] font-semibold"
                            :class="
                                isOn(area.value)
                                    ? 'text-ink-soft'
                                    : 'text-[#8A94A3]'
                            "
                            >{{ counts[area.value] ?? 0 }}</span
                        >
                    </button>
                </div>
            </div>

            <FmButton v-if="variant === 'sheet'" wide @click="open = false">
                {{
                    transChoice(
                        '[0,1] Show :count entry|[2,*] Show :count entries',
                        shown,
                        { count: String(shown) },
                    )
                }}
            </FmButton>
        </div>
    </component>
</template>
