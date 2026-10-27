<script setup lang="ts">
/**
 * The filters of *Documents*: category, person, organization, status and the
 * expiring ones. Every change is sent at once; the page reloads the list.
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { categoryOptions } from '@/lib/categories';
import type { DocumentFilterOptions, DocumentFilters } from '@/lib/documents';

const { filters, options } = defineProps<{
    /** The filters in use. */
    filters: DocumentFilters;
    /** The choices of the menus. */
    options: DocumentFilterOptions;
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
    <div class="flex flex-col gap-3.5">
        <FormKit
            v-for="menu in menus"
            :key="menu.name"
            type="select"
            :label="menu.label"
            :name="menu.name"
            :options="menu.options"
            :model-value="
                filters[menu.name] === null ? '' : String(filters[menu.name])
            "
            @update:model-value="
                (value: unknown) => change({ [menu.name]: id(value) })
            "
        />
        <FormKit
            type="select"
            :label="$t('Status')"
            name="status"
            :options="statuses"
            :model-value="filters.status ?? ''"
            @update:model-value="
                (value: unknown) =>
                    change({ status: value ? String(value) : null })
            "
        />
        <FormKit
            type="checkbox"
            :label="$t('Expiring within 60 days')"
            name="expiring"
            :model-value="filters.expiring"
            @update:model-value="
                (value: unknown) => change({ expiring: value === true })
            "
        />
    </div>
</template>
