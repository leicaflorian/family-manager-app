<script setup lang="ts">
/**
 * One thing the reading does besides the document, inside `FmAlsoDo`, as the
 * board *Importa* draws it: a box with a tick, an optional icon, the text, a
 * line of explanation and the warnings in orange. The whole row is a label, so
 * a tap on the text ticks it. While it is ticked the box is blue and shows,
 * under the row, the fields to correct and the actions (*Choose another…*,
 * *Create a new …*, *Back to the proposal*). Its height follows its content
 * with a short animation (the fields that open, a text that changes), and its
 * colours change softly.
 *
 * @group sections
 */
import type { LucideIcon } from '@lucide/vue';
import { Check } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useHeightTransition } from '@/composables/useHeightTransition';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

const {
    label,
    hint = undefined,
    warnings = [],
    icon = undefined,
    disabled = false,
} = defineProps<{
    /** The text of the checkbox, already translated. */
    label: string;
    /** A line of explanation under the text. */
    hint?: string;
    /** What the person should know before leaving it ticked, one line each. */
    warnings?: string[];
    /** The icon before the text, from `@lucide/vue`. */
    icon?: LucideIcon;
    /** It cannot be ticked or unticked. */
    disabled?: boolean;
}>();

/** Whether the item is ticked. */
const ticked = defineModel<boolean>({ default: false });

defineSlots<{
    /** The fields to correct, shown while the item is ticked. */
    default?: () => unknown;
    /** The buttons of the item, shown while it is ticked. */
    actions?: () => unknown;
}>();

/** The box, whose height is animated. */
const box = ref<HTMLElement>();
/** Its content, whose size is watched. */
const content = ref<HTMLElement>();
useHeightTransition(box, content);

/** The classes of the box: blue and thicker when ticked, faded when disabled. */
const boxClass = computed(() =>
    cn(
        'rounded-xl px-3.5 py-3 transition-[background-color,border-color,opacity] duration-200 ease-out motion-reduce:transition-none',
        ticked.value
            ? 'border-2 border-action bg-action-soft'
            : 'border-[1.5px] border-edge bg-card',
        disabled && 'opacity-60',
    ),
);

/** The classes of the square tick. */
const tickClass = computed(() =>
    cn(
        'flex size-[22px] shrink-0 items-center justify-center rounded-sm peer-focus-visible:ring-2 peer-focus-visible:ring-action peer-focus-visible:ring-offset-2',
        ticked.value
            ? 'bg-action text-on-action'
            : 'border-2 border-ink-faint bg-card',
    ),
);

/** The colour of the icon. */
const iconClass = computed(() =>
    cn('shrink-0', ticked.value ? 'text-action-dark' : 'text-ink-soft'),
);
</script>

<template>
    <div ref="box" :class="boxClass">
        <div ref="content" class="flex flex-col gap-3">
            <label
                :class="
                    cn(
                        'flex items-center gap-3',
                        disabled ? 'cursor-not-allowed' : 'cursor-pointer',
                    )
                "
            >
                <input
                    v-model="ticked"
                    type="checkbox"
                    :disabled="disabled"
                    class="peer sr-only"
                />
                <span :class="tickClass" aria-hidden="true">
                    <FmIcon
                        v-if="ticked"
                        :icon="Check"
                        :size="15"
                        :stroke="3"
                    />
                </span>
                <FmIcon
                    v-if="icon"
                    :icon="icon"
                    :size="20"
                    :class="iconClass"
                />
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-body-strong">{{ label }}</span>
                    <span v-if="hint" class="text-meta text-ink-soft">{{
                        hint
                    }}</span>
                    <span
                        v-for="warning in warnings"
                        :key="warning"
                        class="text-meta text-warning"
                        >{{ warning }}</span
                    >
                </span>
            </label>
            <div
                v-if="ticked && ($slots.default || $slots.actions)"
                class="flex animate-in flex-col gap-2 pl-[34px] duration-200 fade-in-0 motion-reduce:animate-none"
            >
                <slot />
                <div v-if="$slots.actions" class="flex flex-wrap gap-3">
                    <slot name="actions" />
                </div>
            </div>
        </div>
    </div>
</template>
