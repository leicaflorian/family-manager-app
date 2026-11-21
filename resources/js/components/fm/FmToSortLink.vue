<script setup lang="ts">
/**
 * The link to *To sort* that sits beside the title of *Documents* and
 * *Upload*. With none waiting it is not shown. On the tablet and the computer
 * it is a soft button that says how many files wait. On the phone, where the
 * actions beside the title are icon only, it is a round 44 px button like the
 * search and the bell beside it (`useLayoutMode()` says 'phone'): an inbox
 * icon with the number in the red badge of the bell (up to 99+), and the full
 * sentence for screen readers.
 *
 * @group navigation
 */
import { Link } from '@inertiajs/vue3';
import { Inbox } from '@lucide/vue';
import { useLayoutMode } from '@/composables/useLayoutMode';
import { COUNT_BADGE_CLASS, countBadge } from '@/lib/badge';
import { toSort } from '@/routes';
import FmButton from './FmButton.vue';
import FmIcon from './FmIcon.vue';

const { count } = defineProps<{
    /** How many files wait, copies left out. */
    count: number;
}>();

const mode = useLayoutMode();
</script>

<template>
    <template v-if="count > 0">
        <Link
            v-if="mode === 'phone'"
            :href="toSort().url"
            :aria-label="
                $tChoice(
                    '{1} :count file to sort|[2,*] :count files to sort',
                    count,
                    { count: String(count) },
                )
            "
            class="relative flex size-11 shrink-0 items-center justify-center rounded-full bg-card text-ink"
        >
            <FmIcon :icon="Inbox" :size="22" />
            <span aria-hidden="true" :class="COUNT_BADGE_CLASS">{{
                countBadge(count)
            }}</span>
        </Link>
        <FmButton v-else variant="soft" :href="toSort().url">
            {{
                $tChoice(
                    '{1} :count file to sort|[2,*] :count files to sort',
                    count,
                    { count: String(count) },
                )
            }}
        </FmButton>
    </template>
</template>
