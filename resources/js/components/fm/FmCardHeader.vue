<script setup lang="ts">
/**
 * The coloured top of a card page, as on the board *Scheda*: «Area · Type»,
 * the name and the line of data. On the right, the `actions` slot holds
 * *Edit* and the ⋯ menu. With the key the page gives to `usePageMeta`, the
 * «i» of `FmPageInfo` stands after the name, white on the colour.
 *
 * @group sections
 */
import { computed, inject, ref } from 'vue';
import { provideOnColor } from '@/composables/useOnColor';
import { areaHeader, areaOf } from '@/lib/areas';
import { cardIcon } from '@/lib/cards';
import { PAGE_INFO } from '@/lib/titles';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';
import FmPageInfo from './FmPageInfo.vue';

const { card } = defineProps<{
    /** The card. */
    card: App.Data.CardData;
    /** The line of data below the name, for example «Plate GH 482 TK · Marco». */
    heading: string;
}>();

defineSlots<{
    /** On the right: *Edit* and the ⋯ menu, translucent on the colour. */
    actions?: () => unknown;
}>();

provideOnColor();

/** The key of the explanation of the page, if it gives one. */
const infoKey = inject(PAGE_INFO, ref(undefined));

/** The background: a fade from the dark colour of the area to a lighter one. */
const background = computed((): string =>
    cn(
        'flex flex-wrap items-end justify-between gap-4 rounded-sheet bg-linear-160 p-5 text-on-hero computer:p-7',
        areaHeader[areaOf(card.area) ?? 'general'],
    ),
);
</script>

<template>
    <div :class="background">
        <div class="flex min-w-0 flex-col gap-1.5">
            <span class="flex items-center gap-1.5 text-label">
                <FmIcon :icon="cardIcon(card.type)" :size="18" />{{
                    `${card.area_label} · ${card.type_label}`
                }}
            </span>
            <div class="flex min-w-0 items-center gap-1.5">
                <h1 class="m-0 text-title-mobile computer:text-title">
                    {{ card.label }}
                </h1>
                <FmPageInfo v-if="infoKey" :info-key="infoKey" />
            </div>
            <span class="text-body">{{ heading }}</span>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2.5">
            <slot name="actions" />
        </div>
    </div>
</template>
