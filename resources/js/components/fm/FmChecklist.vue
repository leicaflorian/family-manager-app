<script setup lang="ts">
/**
 * *To begin*, on the Home of a new family: the first steps, each a link to
 * where it is done. A step done has the green tick and its text struck through.
 * The page decides which steps there are and when each is done.
 *
 * @group sections
 */
import { Check } from '@lucide/vue';
import FmCard from './FmCard.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

/** A step of the list. */
export type ChecklistStep = {
    /** What to do. */
    title: string;
    /** A line more about it. */
    subtitle?: string;
    /** Where it is done. */
    href: string;
    /** It is already done. */
    done: boolean;
};

defineProps<{
    /** The steps, in order. */
    steps: ChecklistStep[];
}>();
</script>

<template>
    <FmCard :title="$t('To begin')" list>
        <FmRow
            v-for="step in steps"
            :key="step.href"
            :title="step.title"
            :subtitle="step.subtitle"
            :href="step.href"
            :done="step.done"
        >
            <template #before>
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-full"
                    :class="
                        step.done
                            ? 'bg-paid text-white'
                            : 'border-2 border-edge bg-white'
                    "
                >
                    <template v-if="step.done">
                        <FmIcon :icon="Check" :size="16" :stroke="3" />
                        <span class="sr-only">{{ $t('Done') }}</span>
                    </template>
                </span>
            </template>
        </FmRow>
    </FmCard>
</template>
