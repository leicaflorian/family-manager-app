<script setup lang="ts">
/**
 * The top of *People* and *Organizations*: the title *Family* with the name of
 * the family, the actions of the page and the two views, each with how many.
 */
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { index as organizations } from '@/routes/organizations';
import { index as people } from '@/routes/people';
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

const views = computed(() => [
    {
        key: 'people',
        label: trans('People'),
        count: family.people,
        href: people().url,
    },
    {
        key: 'organizations',
        label: trans('Organizations'),
        count: family.organizations,
        href: organizations().url,
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
        <nav
            :aria-label="$t('Family')"
            class="flex max-w-[420px] gap-1.5 rounded-xl bg-[#E6EAF0] p-1"
        >
            <Link
                v-for="view in views"
                :key="view.key"
                :href="view.href"
                :aria-current="view.key === current ? 'page' : undefined"
                class="flex h-9 flex-1 items-center justify-center gap-1 rounded-[9px] text-[15px] whitespace-nowrap no-underline"
                :class="
                    view.key === current
                        ? 'bg-white text-ink shadow-[0_1px_3px_rgba(24,33,47,0.12)]'
                        : 'text-[#4A5565]'
                "
            >
                {{ view.label }}
                <span class="font-semibold text-ink-soft">{{
                    view.count
                }}</span>
            </Link>
        </nav>
    </div>
</template>
