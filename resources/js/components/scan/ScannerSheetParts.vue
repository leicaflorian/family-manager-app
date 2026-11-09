<script setup lang="ts">
/**
 * The cards of a composed page of *Scan the sheets*, from the top: each with its size and the keys to correct its
 * cut, turn it, move it up or down or take it off the page. Under them whether they are at their real size or
 * reduced to fit, *Rounded corners* and *Separate*, which puts the sides back in the strip as they arrived.
 */
import { ArrowDown, ArrowUp, Crop, RotateCw, Split, X } from '@lucide/vue';
import FmButton from '@/components/fm/FmButton.vue';
import type { Size } from '@/lib/scan/card';

const {
    sizes,
    shrink,
    round,
    disabled = false,
} = defineProps<{
    /** How large each card is on the page, in millimetres, from the top. */
    sizes: Size[];
    /** By how much the cards were reduced to fit: 1 at their real size. */
    shrink: number;
    /** The corners of the cards are rounded. */
    round: boolean;
    /** Nothing can be changed (during the upload). */
    disabled?: boolean;
}>();

const emit = defineEmits<{
    /** *Correct the cut* of a card. */
    crop: [index: number];
    /** A quarter of a turn to a card. */
    turn: [index: number];
    /** A card one place up or down. */
    move: [index: number, step: -1 | 1];
    /** A card off the page. */
    drop: [index: number];
    /** *Rounded corners*. */
    round: [on: boolean];
    /** *Separate*. */
    separate: [];
}>();

/**
 * A size in millimetres as the page writes it, in both languages: «85,6 × 54,0 mm».
 *
 * @param size - the size
 * @returns the text
 */
function sizeText(size: Size): string {
    const text = (value: number): string => value.toFixed(1).replace('.', ',');

    return `${text(size.width)} × ${text(size.height)} mm`;
}
</script>

<template>
    <fieldset
        class="m-0 flex w-full max-w-[420px] flex-col gap-3 rounded-card border border-edge-soft bg-card p-4"
    >
        <legend class="px-1 text-label">
            {{ $t('Composed page') }}
        </legend>

        <ol class="m-0 flex list-none flex-col gap-2 p-0">
            <li
                v-for="(size, index) in sizes"
                :key="index"
                class="flex items-center gap-1.5"
            >
                <span class="grow text-body-sm tabular-nums">
                    {{ index + 1 }} · {{ sizeText(size) }}
                </span>
                <FmButton
                    variant="outline"
                    xsmall
                    :icon="Crop"
                    :label="
                        $t('Correct the cut of side :number', {
                            number: String(index + 1),
                        })
                    "
                    :disabled="disabled"
                    @click="emit('crop', index)"
                />
                <FmButton
                    variant="outline"
                    xsmall
                    :icon="RotateCw"
                    :label="
                        $t('Turn side :number', { number: String(index + 1) })
                    "
                    :disabled="disabled"
                    @click="emit('turn', index)"
                />
                <FmButton
                    variant="outline"
                    xsmall
                    :icon="ArrowUp"
                    :label="
                        $t('Move side :number up', {
                            number: String(index + 1),
                        })
                    "
                    :disabled="disabled || index === 0"
                    @click="emit('move', index, -1)"
                />
                <FmButton
                    variant="outline"
                    xsmall
                    :icon="ArrowDown"
                    :label="
                        $t('Move side :number down', {
                            number: String(index + 1),
                        })
                    "
                    :disabled="disabled || index === sizes.length - 1"
                    @click="emit('move', index, 1)"
                />
                <FmButton
                    variant="outline"
                    xsmall
                    :icon="X"
                    :label="
                        $t('Take side :number off the page', {
                            number: String(index + 1),
                        })
                    "
                    :disabled="disabled"
                    @click="emit('drop', index)"
                />
            </li>
        </ol>

        <span class="text-body-sm text-ink-soft">{{
            shrink < 1
                ? $t(
                      'At their real size they do not fit: reduced to :percent %.',
                      { percent: String(Math.round(shrink * 100)) },
                  )
                : $t('At their real size: printed at 100 % it is a photocopy.')
        }}</span>

        <FormKit
            type="toggle"
            name="round"
            :model-value="round"
            :label="$t('Rounded corners')"
            :help="
                $t(
                    'Cuts the corners of each side round, as a real card has them.',
                )
            "
            :disabled="disabled"
            @update:model-value="
                (value: unknown) => emit('round', value === true)
            "
        />

        <FmButton
            variant="outline"
            :icon="Split"
            :disabled="disabled"
            @click="emit('separate')"
            >{{ $t('Separate') }}</FmButton
        >
    </fieldset>
</template>
