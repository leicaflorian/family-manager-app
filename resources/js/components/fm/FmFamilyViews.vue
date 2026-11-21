<script setup lang="ts">
/**
 * The top of *People* and *Organizations*: the title *Family* with the name of
 * the family, the actions of the page and the two views as underlined tabs (as the budget), each with how many.
 *
 * @group navigation
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { index as organizations } from '@/routes/organizations';
import { index as people } from '@/routes/people';
import FmTabs, { type Tab } from './FmTabs.vue';
import FmTitle from './FmTitle.vue';

const { family, current } = defineProps<{
    /** The family, with how many people and organizations it has. */
    family: App.Data.FamilySummaryData;
    /** The view open. */
    current: 'people' | 'organizations';
}>();

defineSlots<{
    /** The buttons on the right of the title. */
    actions?: () => unknown;
}>();

const views = computed((): Tab[] => [
    {
        label: trans('People'),
        count: family.people,
        href: people().url,
        current: current === 'people',
    },
    {
        label: trans('Organizations'),
        count: family.organizations,
        href: organizations().url,
        current: current === 'organizations',
    },
]);
</script>

<template>
    <!-- One block: the views stay at the same distance from the title on both pages -->
    <div class="flex flex-col gap-4">
        <FmTitle
            :title="$t('Family')"
            :subtitle="$t(':family family', { family: family.name })"
        >
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>
        </FmTitle>
        <FmTabs :tabs="views" :label="$t('Family')" />
    </div>
</template>
