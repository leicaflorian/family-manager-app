<script setup lang="ts">
/**
 * *Record payment* (or *Record income*) of a card: it opens *A due date* with
 * the panel of *I paid it* on the oldest open due date. With several planned
 * items it first asks which one, in a menu; without an open due date it is
 * not there.
 */
import { Plus } from '@lucide/vue';
import { deadlineUrls } from '@/lib/budget';
import FmMenu from './FmMenu.vue';
import FmMenuItem from './FmMenuItem.vue';
import FmSectionLink from './FmSectionLink.vue';

const { deadlines, label } = defineProps<{
    /** The oldest open due date of each planned item. */
    deadlines: App.Data.DeadlineData[];
    /** The text of the button, for example «Record payment». */
    label: string;
}>();

/**
 * Where a due date takes the payment: its page, with the panel open.
 *
 * @param deadline - the due date
 * @returns the address of the page with `?pay=1`
 */
function payUrl(deadline: App.Data.DeadlineData): string {
    return `${deadlineUrls(deadline).show}?pay=1`;
}
</script>

<template>
    <FmSectionLink
        v-if="deadlines.length === 1"
        :icon="Plus"
        :href="payUrl(deadlines[0])"
        >{{ label }}</FmSectionLink
    >
    <FmMenu v-else-if="deadlines.length > 1" :label="label" :icon="Plus" text>
        <FmMenuItem
            v-for="deadline in deadlines"
            :key="`${deadline.kind}-${deadline.planned_id}`"
            :href="payUrl(deadline)"
            >{{ deadline.planned_title }}</FmMenuItem
        >
    </FmMenu>
</template>
