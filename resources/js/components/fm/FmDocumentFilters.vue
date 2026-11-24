<script setup lang="ts">
/**
 * The filters of *Documents*: category, person, organization, status and the
 * expiring ones. Every change is sent at once; the page reloads the list. The
 * category, the person and the organization are found by typing.
 *
 * @group forms
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { categoryOptions } from '@/lib/categories';
import { cn } from '@/lib/utils';
import type { DocumentFilterOptions, DocumentFilters } from '@/lib/documents';
import FmCategoryOption from './FmCategoryOption.vue';

const {
    filters,
    options,
    columns = false,
} = defineProps<{
    /** The filters in use. */
    filters: DocumentFilters;
    /** The choices of the menus. */
    options: DocumentFilterOptions;
    /** Lay the fields out as a grid, for the box under the search; without it they stack, for the panel from below. */
    columns?: boolean;
}>();

const emit = defineEmits<{
    /** The filters changed: the new ones, whole. */
    change: [filters: DocumentFilters];
}>();

/** The choice at the top of a menu, that filters nothing. */
const all = computed(() => ({ value: '', label: trans('All') }));

const menus = computed(() => [
    {
        name: 'category' as const,
        label: trans('Category'),
        options: [all.value, ...categoryOptions(options.categories)],
    },
    {
        name: 'person' as const,
        label: trans('Person'),
        options: [all.value, ...options.people],
    },
    {
        name: 'organization' as const,
        label: trans('Organization'),
        options: [all.value, ...options.organizations],
    },
]);

/** The classes of the form: a grid of cells in the box, a stack in the panel. */
const rootClass = computed(() =>
    columns
        ? cn('grid grid-cols-2 gap-x-3.5 gap-y-3 computer:grid-cols-4')
        : cn('flex flex-col gap-3.5'),
);

/** Whether any filter is on, the search included. */
const anyOn = computed(
    () =>
        filters.category !== null ||
        filters.person !== null ||
        filters.organization !== null ||
        filters.status !== null ||
        filters.expiring ||
        filters.search !== null,
);

/** Takes every filter away, the search too. */
function remove(): void {
    emit('change', {
        category: null,
        person: null,
        organization: null,
        status: null,
        expiring: false,
        search: null,
        // The card the list is limited to has its own chip: this panel does not take it away
        card: filters.card,
    });
}

const statuses = computed(() => [all.value, ...options.statuses]);

/**
 * Sends the filters with one of them changed.
 *
 * @param changed - the filter that changed, with its new value
 */
function change(changed: Partial<DocumentFilters>): void {
    emit('change', { ...filters, ...changed });
}

/**
 * The id chosen in a menu.
 *
 * @param value - the value of the menu
 * @returns the id, or null for *All*
 */
function id(value: unknown): number | null {
    return value === '' || value === null || value === undefined
        ? null
        : Number(value);
}
</script>

<template>
    <div :class="rootClass">
        <FormKit
            v-for="menu in menus"
            :key="menu.name"
            type="autocomplete"
            :label="menu.label"
            :name="menu.name"
            :options="menu.options"
            open-on-click
            :model-value="
                filters[menu.name] === null ? '' : String(filters[menu.name])
            "
            @update:model-value="
                (value: unknown) => change({ [menu.name]: id(value) })
            "
        >
            <template v-if="menu.name === 'category'" #option="{ option }">
                <FmCategoryOption :option="option" />
            </template>
        </FormKit>
        <FormKit
            type="dropdown"
            :label="$t('Status')"
            name="status"
            :options="statuses"
            :model-value="filters.status ?? ''"
            @update:model-value="
                (value: unknown) =>
                    change({ status: value ? String(value) : null })
            "
        />
        <div :class="cn(columns && 'col-span-full flex justify-between')">
            <FormKit
                type="toggle"
                :label="$t('Expiring within 60 days')"
                name="expiring"
                :model-value="filters.expiring"
                @update:model-value="
                    (value: unknown) => change({ expiring: value === true })
                "
            />
            <button
                v-if="columns && anyOn"
                type="button"
                class="cursor-pointer self-center border-0 bg-transparent p-0 text-row text-action"
                @click="remove"
            >
                {{ $t('Remove the filters') }}
            </button>
        </div>
    </div>
</template>
