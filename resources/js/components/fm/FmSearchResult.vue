<script setup lang="ts">
/**
 * One result of the global search: the tile with the icon, the title with the
 * words searched marked, a line below (what it is, its date or the days left,
 * a flag such as «Archived») and on the right the amount. The whole row is a
 * link to the page of the result.
 *
 * Rules: the tile is 40 px with radius 12, and `compact` (inside the menu
 * under the box) 32 px with radius 8. A result of an area takes the icon and
 * colour of the area; the others take one of a fixed set of icons and a tone
 * (`neutral` has a `canvas` background and an `edge` border). `active` is the
 * row chosen with the arrows: a `canvas` background.
 *
 * @group rows
 */
import {
    ArrowDownLeft,
    Building2,
    CalendarClock,
    FileText,
    Receipt,
    User,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { areaIcon, areaOf, areaTile } from '@/lib/areas';
import { relativeDays } from '@/lib/format';
import { highlightParts } from '@/lib/highlight';
import { cn } from '@/lib/utils';
import FmAmount from './FmAmount.vue';
import FmIcon from './FmIcon.vue';

const {
    result,
    query,
    compact = false,
    active = false,
} = defineProps<{
    /** The result, as the server sends it. */
    result: App.Data.Search.SearchResultData;
    /** What the person wrote, to mark it in the title. */
    query: string;
    /** The small version, for the menu under the box. */
    compact?: boolean;
    /** The row is the one chosen with the arrows. */
    active?: boolean;
}>();

const { date, euro } = useFormat();

/** The icons that are not an area's, by the name the server sends. */
const icons: Record<string, LucideIcon> = {
    'file-text': FileText,
    receipt: Receipt,
    'arrow-down-left': ArrowDownLeft,
    'calendar-clock': CalendarClock,
    'building-2': Building2,
    user: User,
};

/** The colours of a tile by tone. */
const tones: Record<string, string> = {
    neutral: cn('border border-edge bg-canvas text-ink-soft'),
    expenses: cn('bg-expenses-soft text-expenses-text'),
    income: cn('bg-income-soft text-income-text'),
    action: cn('bg-action-soft text-action-dark'),
};

/** The icon of the tile. */
const icon = computed(() =>
    result.area !== null
        ? areaIcon(result.area)
        : (icons[result.icon ?? ''] ?? FileText),
);

/** The classes of the tile: size, radius and colours. */
const tile = computed(() =>
    cn(
        'flex shrink-0 items-center justify-center',
        compact ? 'size-8 rounded-tag' : 'size-10 rounded-lg',
        result.area !== null
            ? areaTile[areaOf(result.area) ?? 'general']
            : (tones[result.tone ?? 'neutral'] ?? tones.neutral),
    ),
);

/** The title in pieces, the ones searched marked. */
const parts = computed(() => highlightParts(result.title, query));

/** The line below the title: subtitle, date or days left, flag. */
const line = computed(() =>
    [
        result.subtitle,
        result.due_in !== null ? relativeDays(result.due_in) : null,
        result.due_in === null && result.date !== null
            ? date(result.date)
            : null,
        result.flag,
    ]
        .filter((part): part is string => part !== null && part !== '')
        .join(' · '),
);
</script>

<template>
    <Link
        :href="result.href"
        class="flex min-h-11 items-center gap-3 text-ink no-underline"
        :class="
            cn(
                compact ? 'rounded-md px-3 py-1.5' : 'px-4 py-3',
                active ? 'bg-canvas' : undefined,
            )
        "
    >
        <span :class="tile"
            ><FmIcon :icon="icon" :size="compact ? 16 : 20"
        /></span>
        <span class="flex min-w-0 grow flex-col gap-0.5">
            <span
                class="truncate"
                :class="compact ? 'text-row' : 'text-body-strong'"
                ><template v-for="(part, i) in parts" :key="i"
                    ><mark
                        v-if="part.match"
                        class="rounded-xs bg-action-soft px-0.5 text-action-dark"
                        >{{ part.text }}</mark
                    ><template v-else>{{ part.text }}</template></template
                ></span
            >
            <span v-if="line" class="text-meta text-ink-soft">{{ line }}</span>
        </span>
        <span v-if="result.amount !== null" class="shrink-0 text-row-amount"
            ><FmAmount :amount="result.amount"
        /></span>
    </Link>
</template>
