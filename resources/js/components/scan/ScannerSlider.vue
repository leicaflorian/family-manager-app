<script setup lang="ts">
/**
 * A slider of *Scan the sheets*, for what is moved by eye on the side in large: the few degrees of *Straighten*, the
 * strength of *Improve*, the angle of a cut. The name and the value on one line, the slider under them, and with
 * `nudge` two buttons beside it that move it by that much. A native range: it is a control of the page, not a field
 * of a form, and it is reached with the keyboard and read by the screen readers as a slider.
 */
import { RotateCcw, RotateCw } from '@lucide/vue';
import FmButton from '@/components/fm/FmButton.vue';

const {
    label,
    valueText,
    min,
    max,
    step,
    nudge,
    disabled = false,
} = defineProps<{
    /** The name of what is moved. */
    label: string;
    /** The value as the page writes it: «+1,3°», «50 %». */
    valueText: string;
    /** The least, the most and the step of the slider. */
    min: number;
    max: number;
    step: number;
    /** With it, the two buttons beside the slider, that move it by this much one way and the other. */
    nudge?: number;
    /** It cannot be moved. */
    disabled?: boolean;
}>();

/** The value. */
const value = defineModel<number>({ required: true });

/**
 * Moves the slider by some steps of `nudge`, within its ends.
 *
 * @param by - how much, with its sign
 */
function move(by: number): void {
    value.value = Math.min(
        max,
        Math.max(min, Math.round((value.value + by) * 10) / 10),
    );
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <div class="flex items-baseline justify-between gap-3 text-sm">
            <span class="font-bold">{{ label }}</span>
            <span class="text-ink-soft tabular-nums">{{ valueText }}</span>
        </div>
        <div class="flex items-center gap-2">
            <FmButton
                v-if="nudge"
                variant="outline"
                xsmall
                :icon="RotateCcw"
                :label="$t('A tenth of a degree anticlockwise')"
                :disabled="disabled"
                @click="move(-nudge)"
            />
            <input
                type="range"
                class="min-w-0 grow accent-action"
                :aria-label="label"
                :aria-valuetext="valueText"
                :min="min"
                :max="max"
                :step="step"
                :value="value"
                :disabled="disabled"
                @input="
                    value = Number(($event.target as HTMLInputElement).value)
                "
            />
            <FmButton
                v-if="nudge"
                variant="outline"
                xsmall
                :icon="RotateCw"
                :label="$t('A tenth of a degree clockwise')"
                :disabled="disabled"
                @click="move(nudge)"
            />
        </div>
    </div>
</template>
