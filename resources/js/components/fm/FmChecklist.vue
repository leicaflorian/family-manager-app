<script setup lang="ts">
/**
 * *To begin*, on the Home of a new family: inside one card the title with *Hide*, a line about the list, how many
 * steps are done with a green bar, then the first steps, each a link to where it is done. A step done has the
 * green tick and its text struck through; a step that a family may not need (`skippable`) has *Not needed* while it
 * is open. A line can close the card (`footer`). The page decides which steps there are, when each is done and
 * what *Hide* and *Not needed* do.
 *
 * @group sections
 */
import { Link } from '@inertiajs/vue3';
import { Check, ChevronRight } from '@lucide/vue';
import { computed, useId } from 'vue';
import FmCard from './FmCard.vue';
import FmIcon from './FmIcon.vue';
import FmRow from './FmRow.vue';

/** A step of the list. */
export type ChecklistStep = {
    /** What the step is, unique in the list: sent back by `skip`. */
    key: string;
    /** What to do. */
    title: string;
    /** A line more about it. */
    subtitle?: string;
    /** Where it is done. */
    href: string;
    /** It is already done. */
    done: boolean;
    /** The family may not need it: while it is open it has *Not needed*. */
    skippable?: boolean;
};

const { steps, intro } = defineProps<{
    /** The steps, in order. */
    steps: ChecklistStep[];
    /** The line under the title, about the list. */
    intro?: string;
}>();

defineEmits<{
    /** *Hide* was pressed. */
    hide: [];
    /** *Not needed* was pressed on a step, with its key. */
    skip: [key: string];
}>();

defineSlots<{
    /** A line at the end of the card, under the steps. */
    footer?: () => unknown;
}>();

/** The id of the title, which names the card and the bar. */
const titleId = useId();

/** How many steps are done. */
const done = computed(() => steps.filter((step) => step.done).length);

/** How much of the bar is green. */
const progressWidth = computed(() =>
    steps.length === 0 ? '0%' : `${(done.value / steps.length) * 100}%`,
);
</script>

<template>
    <FmCard list :aria-labelledby="titleId">
        <div class="flex flex-col gap-2.5 px-4 pt-4 pb-3.5">
            <div class="flex items-center justify-between gap-3">
                <h2 :id="titleId" class="m-0 text-section text-ink">
                    {{ $t('To begin') }}
                </h2>
                <button
                    type="button"
                    class="-mr-2 h-8 cursor-pointer rounded-tag border-0 bg-transparent px-2 text-label text-action hover:text-action-dark"
                    @click="$emit('hide')"
                >
                    {{ $t('Hide') }}
                </button>
            </div>
            <p v-if="intro" class="m-0 text-body-sm text-ink-soft">
                {{ intro }}
            </p>
            <div class="flex items-center gap-2.5">
                <span
                    class="text-meta-strong whitespace-nowrap text-ink-soft"
                    >{{
                        $t(':done of :total', {
                            done: String(done),
                            total: String(steps.length),
                        })
                    }}</span
                >
                <span
                    role="progressbar"
                    :aria-labelledby="titleId"
                    aria-valuemin="0"
                    :aria-valuemax="steps.length"
                    :aria-valuenow="done"
                    class="flex h-1.5 grow overflow-hidden rounded-full bg-divider"
                >
                    <span
                        class="h-1.5 rounded-full bg-paid"
                        :style="{ width: progressWidth }"
                    />
                </span>
            </div>
        </div>
        <div
            v-for="step in steps"
            :key="step.key"
            class="flex items-center"
            :class="step.skippable && !step.done && 'gap-1 pr-2'"
        >
            <FmRow
                :title="step.title"
                :subtitle="step.subtitle"
                :href="step.href"
                :done="step.done"
                :arrow="!step.skippable || step.done"
                :class="step.skippable && !step.done && 'min-w-0 pr-0'"
            >
                <template #before>
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-full"
                        :class="
                            step.done
                                ? 'bg-paid text-on-action'
                                : 'border-2 border-edge bg-card'
                        "
                    >
                        <template v-if="step.done">
                            <FmIcon :icon="Check" :size="16" :stroke="3" />
                            <span class="sr-only">{{ $t('Done') }}</span>
                        </template>
                    </span>
                </template>
            </FmRow>
            <template v-if="step.skippable && !step.done">
                <button
                    type="button"
                    :aria-label="
                        $t('Remove the step :title', { title: step.title })
                    "
                    class="h-11 shrink-0 cursor-pointer rounded-tag border-0 bg-transparent px-2 text-meta-strong text-ink-soft hover:text-ink"
                    @click="$emit('skip', step.key)"
                >
                    {{ $t('Not needed') }}
                </button>
                <Link
                    :href="step.href"
                    aria-hidden="true"
                    tabindex="-1"
                    class="flex h-11 items-center pr-2 pl-1 text-ink-faint"
                >
                    <FmIcon :icon="ChevronRight" :size="20" />
                </Link>
            </template>
        </div>
        <div
            v-if="$slots.footer"
            class="px-4 pt-3 pb-3.5 text-meta text-ink-soft"
        >
            <slot name="footer" />
        </div>
    </FmCard>
</template>
