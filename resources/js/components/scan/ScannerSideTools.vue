<script setup lang="ts">
/**
 * *Improve* of the side in large of *Scan the sheets*, in the tab *This page* of the choices, as the board
 * `Scanner-pc` draws it: *Straighten* by a few degrees (with *Reset* beside the value and *Find it by itself* after how
 * crooked it looks), then the strength of *Improve* and *Black and white*, each part under a line. A composed page
 * (`composed`) has no *Straighten*: its cards are straightened in their cut.
 */
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import ScannerSlider from '@/components/scan/ScannerSlider.vue';
import type { Improvement } from '@/lib/scan/improve';
import { MOST_ANGLE, SKEW_WORTH, angleText } from '@/lib/scan/improve';

const {
    improvement,
    skew,
    composed = false,
    disabled = false,
} = defineProps<{
    /** What is asked for the side. */
    improvement: Improvement;
    /** By how much the sheet went in crooked, in degrees; null when the page cannot tell. */
    skew: number | null;
    /** The side is a composed page. */
    composed?: boolean;
    /** Nothing can be changed (during the upload). */
    disabled?: boolean;
}>();

const emit = defineEmits<{
    /** The side turned to so many degrees. */
    angle: [degrees: number];
    /** *Find it by itself*: turned back by as much as it went in crooked. */
    find: [];
    /** The strength of *Improve*, from 0 to 100. */
    enhance: [value: number];
    /** *Black and white*. */
    grey: [on: boolean];
}>();

/** How crooked the side looks, as the page says it. */
const skewText = computed((): string => {
    if (skew === null) {
        return trans(
            'If it is crooked the page cannot tell by itself: turn it by hand.',
        );
    }

    return Math.abs(skew) < SKEW_WORTH
        ? trans('It looks straight.')
        : trans('It looks crooked by :degrees.', {
              degrees: angleText(Math.abs(skew)).replace('+', ''),
          });
});
</script>

<template>
    <fieldset
        aria-labelledby="improve-title"
        class="m-0 flex w-full flex-col gap-6 border-0 p-0"
    >
        <h2 id="improve-title" class="m-0 -mb-2.5 text-amount">
            {{ $t('Improve') }}
        </h2>

        <div v-if="!composed" class="flex flex-col gap-2">
            <ScannerSlider
                :model-value="improvement.angle"
                :label="$t('Straighten')"
                :value-text="angleText(improvement.angle)"
                :min="-MOST_ANGLE"
                :max="MOST_ANGLE"
                :step="0.1"
                :nudge="0.1"
                :disabled="disabled"
                @update:model-value="emit('angle', $event)"
            >
                <template #actions>
                    <button
                        type="button"
                        class="cursor-pointer border-0 bg-transparent p-0 text-label text-action disabled:cursor-not-allowed disabled:text-ink-soft disabled:opacity-50"
                        :disabled="disabled || !improvement.angle"
                        @click="emit('angle', 0)"
                    >
                        {{ $t('Reset') }}
                    </button>
                </template>
            </ScannerSlider>
            <span
                class="flex flex-wrap items-baseline gap-x-2.5 gap-y-1 text-body-sm text-ink-soft"
            >
                {{ skewText }}
                <button
                    v-if="skew !== null"
                    type="button"
                    class="cursor-pointer border-0 bg-transparent p-0 text-label text-action disabled:cursor-not-allowed disabled:text-ink-soft"
                    :disabled="disabled"
                    @click="emit('find')"
                >
                    {{ $t('Find it by itself') }}
                </button>
            </span>
        </div>

        <div
            :class="[
                'flex flex-col gap-2',
                !composed && 'border-t border-edge-soft pt-6',
            ]"
        >
            <ScannerSlider
                :model-value="improvement.enhance"
                :label="$t('Improve the light and the ink')"
                :value-text="`${improvement.enhance} %`"
                :min="0"
                :max="100"
                :step="5"
                :disabled="disabled"
                @update:model-value="emit('enhance', $event)"
            />
            <span class="text-body-sm text-ink-soft">{{
                $t(
                    'Lightens the paper, darkens the text. Halfway it is as on the phone; beyond, more contrast.',
                )
            }}</span>
        </div>

        <div class="border-t border-edge-soft pt-6">
            <FormKit
                :classes="{ outer: 'mb-0!' }"
                type="toggle"
                name="grey"
                :model-value="improvement.grey"
                :label="$t('Black and white')"
                :disabled="disabled"
                @update:model-value="
                    (value: unknown) => emit('grey', value === true)
                "
            />
        </div>
    </fieldset>
</template>
