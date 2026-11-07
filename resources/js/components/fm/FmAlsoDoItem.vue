<script setup lang="ts">
/**
 * One thing the reading does besides the document, inside `FmAlsoDo`: a
 * checkbox with its text, a hint under it and the warnings in orange. While
 * it is ticked the fields to correct show below, and the actions (*Choose
 * another…*, *Create a new …*, *Back to the proposal*) sit under the fields.
 *
 * @group sections
 */
import { useId } from 'vue';
import { cn } from '@/lib/utils';

const {
    label,
    hint = undefined,
    warnings = [],
    disabled = false,
} = defineProps<{
    /** The text of the checkbox, already translated. */
    label: string;
    /** A line of explanation under the text. */
    hint?: string;
    /** What the person should know before leaving it ticked, one line each. */
    warnings?: string[];
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

const id = useId();

/** The classes of the text of the checkbox. */
const labelClass = cn('text-[15px] font-semibold', disabled && 'opacity-60');
</script>

<template>
    <div class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0">
        <div class="flex items-start gap-3">
            <input
                :id="id"
                v-model="ticked"
                type="checkbox"
                :disabled="disabled"
                class="mt-0.5 size-5 shrink-0 accent-action"
            />
            <div class="flex min-w-0 flex-col gap-0.5">
                <label :for="id" :class="labelClass">{{ label }}</label>
                <p v-if="hint" class="m-0 text-sm text-ink-soft">{{ hint }}</p>
                <p
                    v-for="warning in warnings"
                    :key="warning"
                    class="m-0 text-sm text-warning"
                >
                    {{ warning }}
                </p>
            </div>
        </div>
        <div
            v-if="ticked && ($slots.default || $slots.actions)"
            class="flex flex-col gap-3 pl-8"
        >
            <slot />
            <div v-if="$slots.actions" class="flex flex-wrap gap-3">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
