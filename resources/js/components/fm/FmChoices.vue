<script setup lang="ts" generic="T extends string">
/**
 * A choice among a few items, one next to the other («To pay» or «Already paid»).
 * They are real radio buttons: they can be chosen with the arrow keys too.
 */
import { useId } from 'vue';

const { label, options } = defineProps<{
    /** What is being chosen, for screen reader users. */
    label: string;
    /** The items, with the value and the text. */
    options: { value: T; label: string }[];
}>();

/** The chosen item. */
const chosen = defineModel<T>();
const name = useId();
</script>

<template>
    <div
        role="radiogroup"
        :aria-label="label"
        class="grid auto-cols-fr grid-flow-col gap-1 rounded-[14px] bg-canvas p-1"
    >
        <label
            v-for="option in options"
            :key="option.value"
            class="relative flex h-11 cursor-pointer items-center justify-center rounded-[11px] text-[15px] has-[:checked]:bg-white has-[:checked]:font-bold has-[:checked]:text-ink has-[:checked]:shadow-[0_1px_3px_rgba(24,33,47,0.12)] has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-action"
            :class="
                option.value === chosen ? '' : 'font-semibold text-ink-soft'
            "
        >
            <input
                v-model="chosen"
                type="radio"
                :name="name"
                :value="option.value"
                class="absolute size-px opacity-0"
            />
            {{ option.label }}
        </label>
    </div>
</template>
