<script setup lang="ts">
/**
 * The coloured top of a card page, as on the board *Scheda*: «Area · Type»,
 * the name and the line of data, where the person and the organization open
 * in a new tab. On the right, the `actions` slot holds
 * *Edit* and the ⋯ menu.
 */
import { computed } from 'vue';
import { provideOnColor } from '@/composables/useOnColor';
import { areaHeader, areaOf } from '@/lib/areas';
import { cardIcon } from '@/lib/cards';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';
import FmNewTabLink from './FmNewTabLink.vue';

const { card } = defineProps<{
    /** The card. */
    card: App.Data.CardData;
    /**
     * The pieces of the line below the name, for example «Plate GH 482 TK» and
     * «Marco»: the ones with an address open it in a new tab.
     */
    heading: App.Data.CardLinkData[];
}>();

defineSlots<{
    /** On the right: *Edit* and the ⋯ menu, translucent on the colour. */
    actions?: () => unknown;
}>();

provideOnColor();

/** The background: the colour of the area. */
const background = computed((): string => {
    const area = areaOf(card.area);

    return cn(
        'flex flex-wrap items-end justify-between gap-4 rounded-3xl p-5 text-white lg:p-7',
        area === undefined ? 'bg-general-dark' : areaHeader[area],
    );
});
</script>

<template>
    <div :class="background">
        <div class="flex min-w-0 flex-col gap-1.5">
            <span class="flex items-center gap-1.5 text-sm font-semibold">
                <FmIcon :icon="cardIcon(card.type)" :size="18" />{{
                    `${card.area_label} · ${card.type_label}`
                }}
            </span>
            <h1 class="m-0 text-[28px] font-extrabold lg:text-4xl">
                {{ card.label }}
            </h1>
            <span class="text-base">
                <template v-for="(part, index) in heading" :key="index"
                    ><template v-if="index > 0"> · </template
                    ><FmNewTabLink
                        v-if="part.href"
                        :href="part.href"
                        :text="part.text"
                    /><span v-else>{{ part.text }}</span></template
                >
            </span>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2.5">
            <slot name="actions" />
        </div>
    </div>
</template>
