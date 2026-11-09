<script setup lang="ts">
/**
 * What can be done to the side in large of *Scan the sheets*: *Straighten* by a few degrees (with *Find it by
 * itself*, *Reset* and the reference lines), *Improve* with its strength and *Black and white*, and giving the same
 * *Improve* to all the pages. A composed page (`composed`) has no *Straighten*: its cards are straightened in their
 * cut.
 */
import { Grid3x3 } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import FmButton from '@/components/fm/FmButton.vue';
import ScannerSlider from '@/components/scan/ScannerSlider.vue';
import type { Improvement } from '@/lib/scan/improve';
import { MOST_ANGLE, SKEW_WORTH, angleText } from '@/lib/scan/improve';

const {
    improvement,
    skew,
    composed = false,
    many = false,
    disabled = false,
} = defineProps<{
    /** What is asked for the side. */
    improvement: Improvement;
    /** By how much the sheet went in crooked, in degrees; null when the page cannot tell. */
    skew: number | null;
    /** The side is a composed page. */
    composed?: boolean;
    /** There are other pages to give *Improve* to. */
    many?: boolean;
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
    /** *Improve* and *Black and white* to all the pages. */
    all: [];
}>();

/** The reference lines over the side in large. */
const grid = defineModel<boolean>('grid', { default: false });

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
        class="m-0 flex w-full max-w-[420px] flex-col gap-3 rounded-card border border-edge-soft bg-card p-4"
    >
        <legend class="px-1 text-sm font-bold">
            {{ $t('Improve') }}
        </legend>

        <template v-if="!composed">
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
            />
            <span class="text-sm text-ink-soft">{{ skewText }}</span>
            <div class="flex flex-wrap items-center gap-2">
                <FmButton
                    variant="outline"
                    :disabled="disabled || skew === null"
                    @click="emit('find')"
                    >{{ $t('Find it by itself') }}</FmButton
                >
                <FmButton
                    variant="outline"
                    :disabled="disabled || !improvement.angle"
                    @click="emit('angle', 0)"
                    >{{ $t('Reset') }}</FmButton
                >
                <span class="grow" />
                <FmButton
                    :variant="grid ? 'soft' : 'outline'"
                    small
                    :icon="Grid3x3"
                    :label="$t('Reference lines')"
                    :aria-pressed="grid"
                    @click="grid = !grid"
                />
            </div>
        </template>

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
        <span class="text-sm text-ink-soft">{{
            $t(
                'Lightens the paper, darkens the text. Halfway it is as on the phone; beyond, more contrast.',
            )
        }}</span>

        <FormKit
            type="toggle"
            name="grey"
            :model-value="improvement.grey"
            :label="$t('Black and white')"
            :disabled="disabled"
            @update:model-value="
                (value: unknown) => emit('grey', value === true)
            "
        />

        <FmButton
            v-if="many"
            variant="text"
            :disabled="disabled"
            @click="emit('all')"
            >{{ $t('Do the same to all the pages') }}</FmButton
        >
    </fieldset>
</template>
