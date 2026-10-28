<script setup lang="ts">
/**
 * *Organizations*: all of them by name, with the search in the name and the
 * filter by type (only the types someone has). Both work in the page, without
 * asking the server: a family has tens of organizations, not thousands.
 */
import { Head } from '@inertiajs/vue3';
import { Building, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import FmEmpty from '@/components/fm/FmEmpty.vue';
import FmFamilyViews from '@/components/fm/FmFamilyViews.vue';
import FmIcon from '@/components/fm/FmIcon.vue';
import FmRow from '@/components/fm/FmRow.vue';
import {
    filterOrganizations,
    organizationIcon,
    organizationSubtitle,
    typeFilters,
} from '@/lib/family';
import { create, show } from '@/routes/organizations';

const { organizations, types, family, canEdit } = defineProps<{
    /** The organizations, by name. */
    organizations: App.Data.OrganizationData[];
    /** Every type of organization, in the order of the filter. */
    types: App.Data.OptionData[];
    /** The family, with how many people and organizations. */
    family: App.Data.FamilySummaryData;
    /** Whoever is looking adds organizations (an `admin`). */
    canEdit: boolean;
}>();

/** The text searched in the names. */
const search = ref('');

/** The chosen type, or null for all. */
const type = ref<string | null>(null);

const filters = computed(() => [
    { value: null, label: '', count: organizations.length },
    ...typeFilters(organizations, types),
]);

const shown = computed(() =>
    filterOrganizations(organizations, type.value, search.value),
);
</script>

<template>
    <Head :title="$t('Organizations')" />

    <div class="flex flex-col gap-4">
        <FmFamilyViews :family="family" current="organizations">
            <template v-if="canEdit" #actions>
                <FmButton :icon="Plus" :href="create().url">{{
                    $t('New organization')
                }}</FmButton>
            </template>
        </FmFamilyViews>

        <FmEmpty
            v-if="organizations.length === 0"
            :icon="Building"
            :title="$t('No organizations, for now')"
            :text="
                $t(
                    'Add the bank, the school, the doctor: here you will find their records and documents.',
                )
            "
        >
            <FmButton v-if="canEdit" :href="create().url">{{
                $t('Add the first organization')
            }}</FmButton>
        </FmEmpty>

        <template v-else>
            <label
                class="flex h-11.5 items-center gap-2.5 rounded-[10px] border border-edge bg-white px-3.5 text-ink-soft"
            >
                <FmIcon :icon="Search" :size="20" />
                <input
                    v-model="search"
                    type="search"
                    :placeholder="$t('Search an organization')"
                    :aria-label="$t('Search an organization')"
                    class="min-w-0 grow border-0 bg-transparent text-base text-ink outline-none"
                />
            </label>

            <div
                role="group"
                :aria-label="$t('Kind of organization')"
                class="flex gap-2 overflow-x-auto"
            >
                <button
                    v-for="filter in filters"
                    :key="filter.value ?? 'all'"
                    type="button"
                    :aria-pressed="type === filter.value"
                    class="flex h-[38px] shrink-0 cursor-pointer items-center gap-1.5 rounded-full border px-3.5 text-sm font-semibold whitespace-nowrap"
                    :class="
                        type === filter.value
                            ? 'border-action bg-action text-white'
                            : 'border-edge bg-white text-ink'
                    "
                    @click="type = filter.value"
                >
                    {{ filter.value === null ? $t('All') : filter.label }}
                    <span class="font-medium opacity-80">{{
                        filter.count
                    }}</span>
                </button>
            </div>

            <div
                v-if="shown.length > 0"
                class="overflow-hidden rounded-2xl bg-white lg:grid lg:grid-cols-[repeat(auto-fill,minmax(420px,1fr))]"
            >
                <FmRow
                    v-for="organization in shown"
                    :key="organization.id"
                    :title="organization.name"
                    :subtitle="organizationSubtitle(organization) || undefined"
                    :href="show.url(organization.id)"
                    class="border-b border-divider"
                >
                    <template #before>
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-canvas text-[#4A5565]"
                        >
                            <FmIcon
                                :icon="organizationIcon(organization.types)"
                                :size="20"
                            />
                        </span>
                    </template>
                </FmRow>
            </div>
            <p v-else class="m-0 px-1 text-[15px] text-ink-soft">
                {{ $t('No organization matches.') }}
            </p>
        </template>
    </div>
</template>
