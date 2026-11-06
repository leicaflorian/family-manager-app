<script setup lang="ts">
/**
 * The chart *Expenses, the last 6 months*: for each month a bar with what was
 * spent and a dark mark where the plan reaches, the month in progress named
 * and its amount written above. Drawn in SVG, with a legend, a title on each
 * month for the hover and *See as a table* for those who read the numbers.
 * The spent is told by the bar and the planned by the mark, so the two are not
 * told apart by colour alone.
 *
 * @group figures
 */
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { longMonth, monthName, shortMonth } from '@/lib/budget';

const { months } = defineProps<{
    /** The months, oldest first. */
    months: App.Data.MonthBarData[];
}>();

const { euro } = useFormat();

/** Whether the numbers are shown as a table instead of the chart. */
const asTable = ref(false);

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

/** The steps the lines of the grid can have, in euros. */
const steps = [100, 250, 500, 1000, 2500, 5000, 10000, 25000, 50000];

/** The step between two lines: the smallest one that gives four lines or fewer. */
const step = computed(() => {
    const highest = Math.max(...months.flatMap((m) => [m.spent, m.planned]), 1);

    return steps.find((candidate) => highest / candidate <= 4) ?? 100000;
});

/** The value at the top of the chart: the first line above the highest value. */
const top = computed(() => {
    const highest = Math.max(...months.flatMap((m) => [m.spent, m.planned]), 1);

    return Math.ceil(highest / step.value) * step.value;
});

/** The lines of the grid, from the first above zero. */
const gridLines = computed(() =>
    Array.from({ length: top.value / step.value }, (_, i) => {
        const value = (i + 1) * step.value;

        return { value, y: y(value) };
    }),
);

/** The width of the slot of a month. */
const slot = computed(
    () => (frame.width - frame.left - frame.right) / months.length,
);

/** The width of a bar. */
const barWidth = computed(() => Math.min(40, slot.value * 0.46));

/**
 * Where a value stands from the top of the drawing.
 *
 * @param value - an amount
 * @returns the y of the drawing
 */
function y(value: number): number {
    return frame.top + plotHeight - (value / top.value) * plotHeight;
}

/** What each month needs to be drawn. */
const bars = computed(() =>
    months.map((month, index) => {
        const centre = frame.left + slot.value * (index + 0.5);
        const x = centre - barWidth.value / 2;
        const barTop = y(month.spent);
        const height = frame.top + plotHeight - barTop;
        const radius = Math.min(4, height / 2, barWidth.value / 2);

        return {
            month,
            centre,
            x,
            barTop,
            // A bar with the top corners rounded and the foot on the baseline
            path:
                height <= 0
                    ? ''
                    : `M${x},${barTop + height} V${barTop + radius} Q${x},${barTop} ${x + radius},${barTop} H${x + barWidth.value - radius} Q${x + barWidth.value},${barTop} ${x + barWidth.value},${barTop + radius} V${barTop + height} Z`,
            plannedY: y(month.planned),
            label: month.current
                ? trans(':month (in progress)', {
                      month: shortMonth(month.month),
                  })
                : shortMonth(month.month),
            title: trans(':month: spent :spent, planned :planned', {
                month: shortMonth(month.month),
                spent: euro(month.spent),
                planned: euro(month.planned),
            }),
        };
    }),
);

/** What the chart says to those who cannot see it. */
const description = computed(() =>
    trans('Spent and planned from :from to :to', {
        from: longMonth(months[0]?.month ?? ''),
        to: monthName(months[months.length - 1]?.month ?? '', false),
    }),
);
</script>

<template>
    <figure class="m-0 flex flex-col gap-2.5 rounded-card bg-white p-4">
        <figcaption
            class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
        >
            <span class="text-base font-bold">{{
                $t('Expenses, the last 6 months')
            }}</span>
            <span class="flex flex-wrap gap-4 text-[13px] text-[#4A5565]">
                <span class="flex items-center gap-1.5"
                    ><span class="size-3 rounded-[3px] bg-action" />{{
                        $t('Spent')
                    }}</span
                >
                <span class="flex items-center gap-1.5"
                    ><span class="h-0.5 w-4 bg-ink" />{{ $t('Planned') }}</span
                >
            </span>
        </figcaption>

        <svg
            v-if="!asTable"
            role="img"
            :aria-label="description"
            :viewBox="`0 0 ${frame.width} ${frame.height}`"
            class="h-auto w-full"
        >
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
                    class="fill-ink-soft text-[11px]"
                >
                    {{ euro(line.value).replace(/,00 €$/, '') }}
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
                <title>{{ bar.title }}</title>
                <!-- A wider invisible target, so the hover does not need a steady hand -->
                <rect
                    :x="bar.centre - slot / 2"
                    :y="frame.top"
                    :width="slot"
                    :height="plotHeight"
                    fill="transparent"
                />
                <path :d="bar.path" class="fill-action" />
                <line
                    :x1="bar.centre - barWidth * 0.75"
                    :x2="bar.centre + barWidth * 0.75"
                    :y1="bar.plannedY"
                    :y2="bar.plannedY"
                    stroke="#fff"
                    stroke-width="6"
                />
                <line
                    :x1="bar.centre - barWidth * 0.75"
                    :x2="bar.centre + barWidth * 0.75"
                    :y1="bar.plannedY"
                    :y2="bar.plannedY"
                    stroke-width="2"
                    class="stroke-ink"
                />
                <text
                    v-if="bar.month.current"
                    :x="bar.centre"
                    :y="bar.barTop - 6"
                    text-anchor="middle"
                    class="fill-ink text-xs font-bold"
                >
                    {{ euro(bar.month.spent) }}
                </text>
                <text
                    :x="bar.centre"
                    :y="frame.height - 10"
                    text-anchor="middle"
                    class="text-xs"
                    :class="
                        bar.month.current
                            ? 'fill-ink font-bold'
                            : 'fill-ink-soft'
                    "
                >
                    {{ bar.label }}
                </text>
            </g>
        </svg>

        <table v-else class="w-full border-collapse text-left text-[15px]">
            <thead>
                <tr class="text-[13px] text-ink-soft">
                    <th class="py-1.5 font-semibold">{{ $t('Month') }}</th>
                    <th class="py-1.5 text-right font-semibold">
                        {{ $t('Spent') }}
                    </th>
                    <th class="py-1.5 text-right font-semibold">
                        {{ $t('Planned') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="month in months"
                    :key="month.month"
                    class="border-t border-divider"
                >
                    <th class="py-2 font-normal">
                        {{ monthName(month.month) }}
                    </th>
                    <td class="py-2 text-right font-semibold">
                        {{ euro(month.spent) }}
                    </td>
                    <td class="py-2 text-right">{{ euro(month.planned) }}</td>
                </tr>
            </tbody>
        </table>

        <button
            type="button"
            class="cursor-pointer self-start border-0 bg-transparent p-0 text-[15px] font-semibold text-action hover:text-action-dark"
            @click="asTable = !asTable"
        >
            {{ asTable ? $t('See as a chart') : $t('See as a table') }}
        </button>
    </figure>
</template>
