<script setup lang="ts">
/**
 * A bar chart of months, drawn in SVG, with a legend, a title on each month
 * for the hover and *See as a table* for those who read the numbers.
 *
 * As *Expenses, the last 6 months* (the default, `marks: 'planned'`): for each
 * month a bar with what was spent and a dark mark where the plan reaches, the
 * month in progress named and its amount written above. The spent is told by
 * the bar and the planned by the mark, so the two are not told apart by colour
 * alone.
 *
 * For *Consumption* (`marks: 'previous'`): the bars in the colour of the home,
 * the quantity written with `format`, the mark on the same month of the year
 * before. A month without data has no bar, a partial month a light dashed bar,
 * an estimated one a striped bar, and the words say it too. With `selected`
 * each bar is a button: the chosen one has an outline and its number above,
 * the others get lighter, and the arrows go to the month next to it.
 *
 * @group figures
 */
import { trans } from 'laravel-vue-i18n';
import { computed, ref, useId } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { longMonth, monthName, shortMonth } from '@/lib/budget';
import { barAnchor, gridStep, toChartMonth } from '@/lib/charts';
import type { ChartMonth } from '@/lib/charts';
import { addMonths } from '@/lib/days';
import { formatEuro, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import FmTooltip from './FmTooltip.vue';

const {
    months,
    format = formatEuro,
    marks = 'planned',
    selected,
    title,
    valueLabel,
    short,
    embedded = false,
} = defineProps<{
    /** The months, oldest first: months of the chart, or of *Planned and spent* as the server sends them. */
    months: readonly (ChartMonth | App.Data.MonthBarData)[];
    /** Writes a value (euros by default, or a quantity with its unit). */
    format?: (value: number) => string;
    /** What the mark is: the planned, or the same month of the year before. */
    marks?: 'planned' | 'previous';
    /** The month chosen, `yyyy-mm`: given (even `null`), the bars become buttons. */
    selected?: string | null;
    /** The title of the chart. */
    title?: string;
    /**
     * It sits inside a card that already has the title (`FmCard`): no card of
     * its own, and the title only for screen readers.
     */
    embedded?: boolean;
    /** What the bars are, in the legend and the table (`Spent`, `kWh`). */
    valueLabel?: string;
    /** Writes the number above the chosen bar or the month in progress (default `format`); consumption in kWh passes `formatNumber`, so it reads `290` as on the board. */
    short?: (value: number) => string;
}>();

const { euro } = useFormat();

const emit = defineEmits<{
    /** The person chose the month of a bar: `yyyy-mm`. */
    select: [month: string];
}>();

/** Whether the numbers are shown as a table instead of the chart. */
const asTable = ref(false);

/** The id of the stripes of the estimated bars, unique in the page. */
const stripesId = `stripes-${useId()}`;

/** The size of the drawing and of its margins. */
const frame = {
    width: 600,
    height: 250,
    left: 48,
    right: 8,
    top: 22,
    bottom: 30,
};

/** The height of the part where the bars stand. */
const plotHeight = frame.height - frame.top - frame.bottom;

/** The months as the chart draws them. */
const list = computed(() => months.map(toChartMonth));

/** Whether the chart shows consumption: the colour of the home and last year's mark. */
const consumption = computed(() => marks === 'previous');

/** Whether the bars are buttons. */
const interactive = computed(() => selected !== undefined);

/** The colours of the bars: the action blue for the budget, the home orange for consumption. */
const tone = computed(() =>
    consumption.value
        ? {
              bar: cn('fill-home'),
              faded: cn('fill-home/45'),
              soft: cn('fill-home-soft'),
              edge: cn('stroke-home'),
              swatch: cn('bg-home'),
              partialSwatch: cn('border-home bg-home-soft'),
              stripesSwatch: cn(
                  'bg-[repeating-linear-gradient(135deg,var(--home)_0_3px,var(--home-soft)_3px_6px)]',
              ),
          }
        : {
              bar: cn('fill-action'),
              faded: cn('fill-action/45'),
              soft: cn('fill-action-soft'),
              edge: cn('stroke-action'),
              swatch: cn('bg-action'),
              partialSwatch: cn('border-action bg-action-soft'),
              stripesSwatch: cn(
                  'bg-[repeating-linear-gradient(135deg,var(--action)_0_3px,var(--action-soft)_3px_6px)]',
              ),
          },
);

/**
 * Whether a month has data, so a bar.
 *
 * @param month - a month of the chart
 * @returns false for a month without data
 */
function hasData(month: ChartMonth): month is ChartMonth & { value: number } {
    return !month.empty && month.value !== null;
}

/** The highest value of the chart, bars and marks. */
const highest = computed(() =>
    Math.max(
        ...list.value.flatMap((m) => [hasData(m) ? m.value : 0, m.mark ?? 0]),
        1,
    ),
);

/** The step between two lines: euros keep the steps of 100 and more they always had. */
const step = computed(() =>
    gridStep(highest.value, consumption.value ? 0 : 100),
);

/** The value at the top of the chart: the first line above the highest value. */
const top = computed(() => Math.ceil(highest.value / step.value) * step.value);

/** The lines of the grid, from the first above zero. */
const gridLines = computed(() =>
    Array.from({ length: Math.round(top.value / step.value) }, (_, i) => {
        // toPrecision keeps 0.75 from becoming 0.7500000000000001
        const value = Number(((i + 1) * step.value).toPrecision(12));

        return { value, y: y(value) };
    }),
);

/** The width of the slot of a month. */
const slot = computed(
    () => (frame.width - frame.left - frame.right) / list.value.length,
);

/** The width of a bar. */
const barWidth = computed(() => Math.min(56, slot.value * 0.62));

/** Half the width of a mark: wider than the bar, so it shows over it. */
const markHalf = computed(() =>
    Math.max(barWidth.value * 0.75, Math.min(22, slot.value * 0.48)),
);

/**
 * Where a value stands from the top of the drawing.
 *
 * @param value - an amount or a quantity
 * @returns the y of the drawing
 */
function y(value: number): number {
    return frame.top + plotHeight - (value / top.value) * plotHeight;
}

/**
 * What a month says, for the hover and for the screen readers: the month, the
 * value (and its euros), the mark, the note and whether it is partial or
 * estimated.
 *
 * @param month - a month with data
 * @returns the text on one line for the screen readers (`title`), and the
 *   text of the bubble (`tip`), with the year before on a line of its own
 */
function describe(month: ChartMonth & { value: number }): {
    title: string;
    tip: string;
} {
    const value = format(month.value);
    let base: string;
    let now = '';
    let before = '';

    if (!consumption.value) {
        base =
            month.mark === null
                ? trans(':month: :value', {
                      month: shortMonth(month.month),
                      value,
                  })
                : trans(':month: spent :spent, planned :planned', {
                      month: shortMonth(month.month),
                      spent: value,
                      planned: format(month.mark),
                  });
    } else {
        const name = monthName(month.month, false);
        const amount =
            month.amount === null || month.amount === undefined
                ? null
                : euro(month.amount);

        if (month.mark === null) {
            base =
                amount === null
                    ? trans(':month: :value', { month: name, value })
                    : trans(':month: :value, :amount', {
                          month: name,
                          value,
                          amount,
                      });
        } else {
            const lastYear = {
                last_year: monthName(
                    addMonths(`${month.month}-01`, -12),
                    false,
                ),
                previous: format(month.mark),
            };
            now =
                amount === null
                    ? trans(':month: :value', { month: name, value })
                    : trans(':month: :value, :amount', {
                          month: name,
                          value,
                          amount,
                      });
            before = trans(':month: :value', {
                month: lastYear.last_year,
                value: lastYear.previous,
            });
            base =
                amount === null
                    ? trans(':month: :value; :last_year: :previous', {
                          month: name,
                          value,
                          ...lastYear,
                      })
                    : trans(':month: :value, :amount; :last_year: :previous', {
                          month: name,
                          value,
                          amount,
                          ...lastYear,
                      });
        }
    }

    const words = extras(month);

    return {
        title: [base, ...words].join(', '),
        // In the bubble the year before goes on a line of its own
        tip: before
            ? [[now, ...words].join(', '), before].join('\n')
            : [base, ...words].join(', '),
    };
}

/**
 * The words that tell a month apart: its note, partial, estimated.
 *
 * @param month - a month of the chart
 * @returns the words, none for a plain month
 */
function extras(month: ChartMonth): string[] {
    return [
        month.note,
        month.partial ? trans('partial month') : undefined,
        month.estimated ? trans('estimated') : undefined,
    ].filter((word): word is string => word !== undefined && word !== '');
}

/**
 * The look of a bar.
 *
 * @param month - a month with data
 * @returns `partial`, `estimated` or `plain`
 */
function kindOf(month: ChartMonth): 'partial' | 'estimated' | 'plain' {
    if (month.partial) {
        return 'partial';
    }

    return month.estimated ? 'estimated' : 'plain';
}

/** What each month needs to be drawn. */
const bars = computed(() =>
    list.value.map((month, index) => {
        const centre = frame.left + slot.value * (index + 0.5);
        const x = centre - barWidth.value / 2;
        const data = hasData(month);
        // A negative month (a credit) does not go below the baseline
        const barTop = y(data ? Math.max(month.value, 0) : 0);
        const height = frame.top + plotHeight - barTop;
        const radius = Math.min(4, height / 2, barWidth.value / 2);
        const chosen = data && selected === month.month;
        const kind = kindOf(month);

        return {
            month,
            data,
            chosen,
            kind,
            centre,
            x,
            barTop,
            height,
            // A bar with the top corners rounded and the foot on the baseline
            path:
                !data || height <= 0
                    ? ''
                    : `M${x},${barTop + height} V${barTop + radius} Q${x},${barTop} ${x + radius},${barTop} H${x + barWidth.value - radius} Q${x + barWidth.value},${barTop} ${x + barWidth.value},${barTop + radius} V${barTop + height} Z`,
            fill:
                kind === 'partial'
                    ? cn(tone.value.soft, tone.value.edge)
                    : kind === 'estimated'
                      ? undefined
                      : selected && !chosen
                        ? tone.value.faded
                        : tone.value.bar,
            markY: month.mark === null ? null : y(Math.max(month.mark, 0)),
            above:
                data && (chosen || month.current)
                    ? {
                          y: barTop - (chosen ? 10 : 6),
                          text: (short ?? format)(month.value),
                      }
                    : null,
            label: month.current
                ? trans(':month (in progress)', {
                      month: shortMonth(month.month),
                  })
                : shortMonth(month.month),
            strong: month.current === true || chosen,
            ...(data ? describe(month) : { title: '', tip: '' }),
        };
    }),
);

/** The month that takes the focus with Tab: the chosen one, or the latest with data. */
const focusMonth = computed(
    () =>
        bars.value.find((bar) => bar.chosen)?.month.month ??
        [...bars.value].reverse().find((bar) => bar.data)?.month.month,
);

/** What the chart says to those who cannot see it. */
const description = computed(() =>
    consumption.value && title
        ? title
        : trans('Spent and planned from :from to :to', {
              from: longMonth(list.value[0]?.month ?? ''),
              to: monthName(
                  list.value[list.value.length - 1]?.month ?? '',
                  false,
              ),
          }),
);

/** The box of the drawing, where the bubbles of the bars are placed. */
const drawing = ref<HTMLElement | null>(null);

/**
 * Where the bubble of a bar opens: on the top of the bar, not on the top of
 * its hover target, which is as tall as the chart.
 *
 * @param bar - where the bar stands in the drawing
 * @param bar.x - its left edge
 * @param bar.barTop - its top
 * @returns the function the bubble reads when it opens
 */
function anchorOf(bar: { x: number; barTop: number }): () => DOMRect {
    return () =>
        barAnchor(
            drawing.value?.getBoundingClientRect() ?? new DOMRect(),
            { x: bar.x, width: barWidth.value, top: bar.barTop },
            frame,
        );
}

/** Where the hover targets sit: over the plot, between the side margins. */
const overlayStyle = computed(() => ({
    left: `${(frame.left / frame.width) * 100}%`,
    right: `${(frame.right / frame.width) * 100}%`,
}));

/** The title above the chart. */
const heading = computed(() => title ?? trans('Expenses, the last 6 months'));

/** What the bars are, in the legend and the table. */
const barsLabel = computed(() => valueLabel ?? trans('Spent'));

/** What the mark is, in the legend and the table; none when no month has one. */
const markLabel = computed(() =>
    !consumption.value
        ? trans('Planned')
        : list.value.some((m) => m.mark !== null)
          ? trans('Same month last year')
          : null,
);

/** Whether some month is partial: the legend says what its bar means. */
const anyPartial = computed(() => list.value.some((m) => m.partial));

/** Whether some month is estimated: the legend says what its bar means. */
const anyEstimated = computed(() => list.value.some((m) => m.estimated));

/**
 * Goes to the month with data next to the one of the bar, with the arrows,
 * and moves the focus to its bar.
 *
 * @param event - the key pressed on a bar
 * @param index - the place of the bar
 */
function move(event: KeyboardEvent, index: number): void {
    const direction =
        event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;

    if (direction === 0) {
        return;
    }

    event.preventDefault();

    for (
        let other = index + direction;
        other >= 0 && other < bars.value.length;
        other += direction
    ) {
        const bar = bars.value[other];

        if (bar.data) {
            emit('select', bar.month.month);
            (event.currentTarget as HTMLElement).parentElement
                ?.querySelector<HTMLElement>(
                    `[data-month="${bar.month.month}"]`,
                )
                ?.focus();

            return;
        }
    }
}
</script>

<template>
    <figure
        class="m-0 flex flex-col gap-2.5"
        :class="!embedded && 'rounded-card bg-card p-4 shadow-card'"
    >
        <figcaption
            class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
        >
            <span :class="embedded ? 'sr-only' : 'text-amount'">{{
                heading
            }}</span>
            <span
                class="flex flex-wrap text-meta text-ink-soft"
                :class="consumption ? 'gap-x-4 gap-y-1.5' : 'gap-4'"
            >
                <span class="flex items-center gap-1.5"
                    ><span class="size-3 rounded-xs" :class="tone.swatch" />{{
                        barsLabel
                    }}</span
                >
                <span v-if="markLabel" class="flex items-center gap-1.5"
                    ><span class="h-0.5 w-4 bg-ink" />{{ markLabel }}</span
                >
                <span v-if="anyPartial" class="flex items-center gap-1.5"
                    ><span
                        class="box-border size-3 rounded-xs border-[1.5px] border-dashed"
                        :class="tone.partialSwatch"
                    />{{ $t('Partial month') }}</span
                >
                <span v-if="anyEstimated" class="flex items-center gap-1.5"
                    ><span
                        class="size-3 rounded-xs"
                        :class="tone.stripesSwatch"
                    />{{ $t('Estimate') }}</span
                >
            </span>
        </figcaption>

        <div v-if="!asTable" ref="drawing" class="relative">
            <svg
                :role="interactive ? undefined : 'img'"
                :aria-label="interactive ? undefined : description"
                :aria-hidden="interactive ? 'true' : undefined"
                :viewBox="`0 0 ${frame.width} ${frame.height}`"
                class="block h-auto w-full"
            >
                <defs v-if="anyEstimated">
                    <pattern
                        :id="stripesId"
                        width="6"
                        height="6"
                        patternUnits="userSpaceOnUse"
                        patternTransform="rotate(45)"
                    >
                        <rect width="6" height="6" :class="tone.soft" />
                        <rect
                            width="3"
                            height="6"
                            :class="consumption ? 'fill-home' : 'fill-action'"
                        />
                    </pattern>
                </defs>
                <g v-for="line in gridLines" :key="line.value">
                    <line
                        :x1="frame.left"
                        :x2="frame.width - frame.right"
                        :y1="line.y"
                        :y2="line.y"
                        class="stroke-divider"
                    />
                    <text
                        :x="frame.left - 6"
                        :y="line.y + 4"
                        text-anchor="end"
                        class="fill-ink-soft text-tiny"
                    >
                        {{ formatNumber(line.value) }}
                    </text>
                </g>
                <line
                    :x1="frame.left"
                    :x2="frame.width - frame.right"
                    :y1="frame.top + plotHeight"
                    :y2="frame.top + plotHeight"
                    class="stroke-edge"
                />
                <g v-for="bar in bars" :key="bar.month.month">
                    <path
                        v-if="bar.path"
                        :d="bar.path"
                        :data-bar="bar.kind"
                        :class="bar.fill"
                        :fill="
                            bar.kind === 'estimated'
                                ? `url(#${stripesId})`
                                : undefined
                        "
                        :stroke-width="bar.kind === 'partial' ? 1.5 : undefined"
                        :stroke-dasharray="
                            bar.kind === 'partial' ? '4 3' : undefined
                        "
                    />
                    <rect
                        v-if="bar.chosen"
                        :x="bar.x - 4"
                        :y="bar.barTop - 4"
                        :width="barWidth + 8"
                        :height="bar.height + 4"
                        rx="6"
                        stroke-width="2"
                        class="fill-none stroke-ink"
                    />
                    <template v-if="bar.markY !== null">
                        <line
                            :x1="bar.centre - markHalf"
                            :x2="bar.centre + markHalf"
                            :y1="bar.markY"
                            :y2="bar.markY"
                            class="stroke-card"
                            stroke-width="6"
                        />
                        <line
                            :x1="bar.centre - markHalf"
                            :x2="bar.centre + markHalf"
                            :y1="bar.markY"
                            :y2="bar.markY"
                            stroke-width="2"
                            class="stroke-ink"
                        />
                    </template>
                    <text
                        v-if="bar.above"
                        :x="bar.centre"
                        :y="bar.above.y"
                        text-anchor="middle"
                        class="fill-ink"
                        :class="bar.chosen ? 'text-badge' : 'text-caption'"
                    >
                        {{ bar.above.text }}
                    </text>
                    <text
                        :x="bar.centre"
                        :y="frame.height - 10"
                        text-anchor="middle"
                        class="text-caption"
                        :class="
                            bar.strong ? 'fill-ink text-badge' : 'fill-ink-soft'
                        "
                    >
                        {{ bar.label }}
                    </text>
                </g>
            </svg>

            <div
                v-if="interactive"
                role="group"
                :aria-label="
                    $t(':title, one button per month', { title: heading })
                "
                class="absolute inset-y-0 flex"
                :style="overlayStyle"
            >
                <template v-for="(bar, index) in bars" :key="bar.month.month">
                    <FmTooltip
                        v-if="bar.data"
                        :text="bar.tip"
                        :anchor="anchorOf(bar)"
                        side="top"
                    >
                        <button
                            type="button"
                            class="flex-1 cursor-pointer rounded-tag border-0 bg-transparent p-0 focus-visible:outline-2 focus-visible:outline-action"
                            :data-month="bar.month.month"
                            :aria-pressed="bar.chosen ? 'true' : 'false'"
                            :aria-label="bar.title"
                            :tabindex="bar.month.month === focusMonth ? 0 : -1"
                            @click="emit('select', bar.month.month)"
                            @keydown="move($event, index)"
                        />
                    </FmTooltip>
                    <span v-else class="flex-1" aria-hidden="true" />
                </template>
            </div>
            <!-- Hover targets over the bars: the figure keeps its own label -->
            <div
                v-else
                aria-hidden="true"
                class="absolute inset-y-0 flex"
                :style="overlayStyle"
            >
                <template v-for="bar in bars" :key="bar.month.month">
                    <FmTooltip
                        v-if="bar.data"
                        :text="bar.tip"
                        :anchor="anchorOf(bar)"
                        side="top"
                    >
                        <span class="flex-1" data-bar-target />
                    </FmTooltip>
                    <span v-else class="flex-1" />
                </template>
            </div>
        </div>

        <table v-else class="w-full border-collapse text-left text-body-sm">
            <thead>
                <tr class="text-meta text-ink-soft">
                    <th class="py-1.5 font-semibold">{{ $t('Month') }}</th>
                    <th class="py-1.5 text-right font-semibold">
                        {{ barsLabel }}
                    </th>
                    <th
                        v-if="markLabel"
                        class="py-1.5 text-right font-semibold"
                    >
                        {{ markLabel }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="month in list"
                    :key="month.month"
                    class="border-t border-divider"
                >
                    <th class="py-2 font-normal">
                        {{ monthName(month.month) }}
                        <span
                            v-if="extras(month).length > 0"
                            class="block text-meta text-ink-soft"
                            >{{ extras(month).join(', ') }}</span
                        >
                    </th>
                    <td class="py-2 text-right font-semibold">
                        {{ hasData(month) ? format(month.value) : '–' }}
                    </td>
                    <td v-if="markLabel" class="py-2 text-right">
                        {{ month.mark === null ? '–' : format(month.mark) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <button
            type="button"
            class="cursor-pointer self-start border-0 bg-transparent p-0 text-row text-action hover:text-action-dark"
            @click="asTable = !asTable"
        >
            {{ asTable ? $t('See as a chart') : $t('See as a table') }}
        </button>
    </figure>
</template>
