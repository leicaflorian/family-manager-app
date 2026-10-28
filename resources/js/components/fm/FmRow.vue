<script setup lang="ts">
/**
 * A list row of the canvas: in front the dot of the area or an icon, then the
 * title and the subtitle, on the right an amount or a date. With `href` the whole
 * row is a link, and with nothing on the right it shows the arrow. With `button`
 * it is a button, for the rows that open a panel.
 */
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import type { Area } from '@/lib/areas';
import { areaFill } from '@/lib/areas';
import { cn } from '@/lib/utils';
import FmIcon from './FmIcon.vue';

/** The colour of the subtitle: normal, red if overdue, orange if it needs attention. */
export type RowTone = 'normal' | 'overdue' | 'warning' | 'paid';

const {
    title,
    subtitle,
    href,
    area,
    tone = 'normal',
    button = false,
    current = false,
} = defineProps<{
    /** The main text. */
    title: string;
    /** The second line: when, or why it needs attention. */
    subtitle?: string;
    /** The address the row opens. */
    href?: string;
    /** The area, for the coloured dot in front. */
    area?: Area;
    /** The colour of the subtitle. */
    tone?: RowTone;
    /** The row is a button: it sends `click`. */
    button?: boolean;
    /** The row is the one open now, as the document shown beside the list. */
    current?: boolean;
}>();

defineEmits<{
    /** The row was tapped, when it is a button. */
    click: [];
}>();

defineSlots<{
    /** Instead of the dot: an icon, an avatar, the thumbnail of a document. */
    before?: () => unknown;
    /** On the right: an amount, a date. */
    after?: () => unknown;
}>();

const toneColor: Record<RowTone, string> = {
    normal: cn('text-ink-soft'),
    overdue: cn('font-semibold text-overdue'),
    warning: cn('font-semibold text-warning'),
    paid: cn('font-semibold text-paid'),
};
</script>

<template>
    <component
        :is="href ? Link : button ? 'button' : 'div'"
        :href="href"
        :type="button ? 'button' : undefined"
        :aria-current="current ? 'page' : undefined"
        :class="
            cn(
                'flex w-full items-center gap-3 border-0 bg-transparent px-4 py-3.5 text-left text-inherit no-underline',
                (href || button) &&
                    'cursor-pointer hover:bg-action-soft focus-visible:bg-action-soft focus-visible:outline-none',
                current && 'bg-action-soft',
            )
        "
        @click="button && $emit('click')"
    >
        <slot name="before">
            <span
                v-if="area"
                data-dot
                class="size-2.5 shrink-0 rounded-full"
                :class="areaFill[area]"
            />
        </slot>
        <span class="flex min-w-0 grow flex-col gap-0.5">
            <span class="text-base font-semibold text-ink">{{ title }}</span>
            <span
                v-if="subtitle"
                class="text-[13px]"
                :class="toneColor[tone]"
                >{{ subtitle }}</span
            >
        </span>
        <span
            v-if="$slots.after"
            class="shrink-0 text-base font-bold whitespace-nowrap text-ink"
        >
            <slot name="after" />
        </span>
        <FmIcon
            v-else-if="href || button"
            :icon="ChevronRight"
            :size="20"
            class="text-[#9AA6B6]"
        />
    </component>
</template>
