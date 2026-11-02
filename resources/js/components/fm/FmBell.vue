<script setup lang="ts">
/**
 * The bell in the header: it leads to *Notices* and, when there are notices
 * not yet seen, shows how many in a red dot (up to 99).
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { notices } from '@/routes';
import FmIcon from './FmIcon.vue';

const { count, href = notices().url } = defineProps<{
    /** How many notices the person has not seen yet. */
    count: number;
    /** Where the bell leads. */
    href?: string;
}>();

/** What the bell says to those who cannot see the dot. */
const label = computed((): string =>
    count > 0
        ? transChoice(
              '{1} Notices: :count new|[2,*] Notices: :count new',
              count,
              {
                  count: String(count),
              },
          )
        : trans('Notices'),
);
</script>

<template>
    <Link
        :href="href"
        :aria-label="label"
        class="relative flex size-11 items-center justify-center text-inherit"
    >
        <FmIcon :icon="Bell" :size="24" />
        <span
            v-if="count > 0"
            aria-hidden="true"
            class="absolute top-1 right-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-overdue px-1 text-[11px] font-bold text-white"
            >{{ count > 99 ? '99+' : count }}</span
        >
    </Link>
</template>
