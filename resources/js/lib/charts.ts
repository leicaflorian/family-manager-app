/**
 * The data of the bar chart (`FmBarChart`): a month with its bar and its mark,
 * and the round steps of the lines of the grid.
 */

/** A month of the bar chart. */
export type ChartMonth = {
    /** The month, `yyyy-mm`. */
    month: string;
    /** The height of the bar (euros, kWh, Smc, m³); `null` when the month has no data: no bar, not a bar at zero. */
    value: number | null;
    /** Where the mark stands (the planned, or the same month of the year before); `null` for no mark. */
    mark: number | null;
    /** The euros of the month, said next to the value when the value is not in euros. */
    amount?: number | null;
    /** The month in progress: its name in bold and its value above the bar. */
    current?: boolean;
    /** Only part of the month is covered: a light, dashed bar. */
    partial?: boolean;
    /** The supplier estimated the month: a striped bar. */
    estimated?: boolean;
    /** No data for the month, like `value: null`. */
    empty?: boolean;
    /** A few words more on the month, for the hover and the table (`until the 14th`). */
    note?: string;
};

/** The first digits of the round steps: 1, 2.5 and 5, times a power of ten. */
const ROUND = [1, 2.5, 5];

/** The most lines of the grid above zero. */
const MAX_LINES = 4;

/**
 * The step between two lines of the grid: the smallest round one (1, 2.5, 5
 * times a power of ten) that gives four lines or fewer.
 *
 * @param highest - the highest value of the chart, above zero
 * @param minimum - the smallest step allowed (100 for euros, as the budget always drew)
 * @returns the step
 */
export function gridStep(highest: number, minimum = 0): number {
    for (let power = -3; power <= 12; power++) {
        for (const digit of ROUND) {
            // toPrecision keeps 0.25 from becoming 0.25000000000000006
            const step = Number((digit * 10 ** power).toPrecision(3));

            if (step >= minimum && highest / step <= MAX_LINES) {
                return step;
            }
        }
    }

    return highest;
}

/**
 * The box on the page where a bar of the drawing ends at the top, as wide as
 * the bar and with no height: a bubble opened beside it sits on the bar, not
 * on the top of the whole chart.
 *
 * @param drawing - the box of the drawing on the page
 * @param bar - where the bar stands in the drawing: left edge, width and top
 * @param frame - the size of the drawing (its `viewBox`)
 * @returns the box on the page
 */
export function barAnchor(
    drawing: DOMRect,
    bar: { x: number; width: number; top: number },
    frame: { width: number; height: number },
): DOMRect {
    const across = drawing.width / frame.width;
    const down = drawing.height / frame.height;

    return new DOMRect(
        drawing.left + bar.x * across,
        drawing.top + bar.top * down,
        bar.width * across,
        0,
    );
}

/**
 * Turns a month of *Planned and spent* into a month of the chart: the spent is
 * the bar, the planned the mark. A month of the chart stays as it is.
 *
 * @param month - the month as the server sends it, or already for the chart
 * @returns the month for the chart
 */
export function toChartMonth(
    month: ChartMonth | App.Data.MonthBarData,
): ChartMonth {
    if (!('spent' in month)) {
        return month;
    }

    return {
        month: month.month,
        value: month.spent,
        mark: month.planned,
        current: month.current,
    };
}
