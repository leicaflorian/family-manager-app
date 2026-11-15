import { render, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { i18nVue, loadLanguageAsync } from 'laravel-vue-i18n';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { createApp } from 'vue';
import { i18nOptions } from '@/lib/i18n';
import type { MonthDay, MonthItem } from '@/lib/monthGrid';
import FmMonthGrid from './FmMonthGrid.vue';

/**
 * Sets the language of the texts, as the app does at start.
 *
 * @param lang - `en` or `it`
 */
async function speak(lang: string): Promise<void> {
    createApp({}).use(i18nVue, i18nOptions(lang));
    await loadLanguageAsync(lang);
}

afterEach(async () => {
    await speak('en');
});

/** An item of a day with the fields the tests change. */
function item(key: string, change: Partial<MonthItem> = {}): MonthItem {
    return {
        key,
        title: `Thing ${key}`,
        area: 'home',
        late: false,
        done: false,
        ...change,
    };
}

/** The days of November 2026 as the grid shows them (26/10 to 06/12), with the items given. */
function november(items: Record<string, MonthItem[]> = {}): MonthDay[] {
    const days: MonthDay[] = [];

    for (let index = 0; index < 42; index++) {
        const date = new Date(Date.UTC(2026, 9, 26 + index))
            .toISOString()
            .slice(0, 10);

        days.push({ date, items: items[date] ?? [] });
    }

    return days;
}

/** The props of the grid on 02/11/2026 with the 04/11 chosen. */
function props(
    items: Record<string, MonthItem[]> = {},
    wide = false,
): {
    month: string;
    from: string;
    to: string;
    today: string;
    selected: string;
    days: MonthDay[];
    wide: boolean;
} {
    return {
        month: '2026-11',
        from: '2026-10-26',
        to: '2026-12-06',
        today: '2026-11-02',
        selected: '2026-11-04',
        days: november(items),
        wide,
    };
}

describe('FmMonthGrid', () => {
    it('shows the 42 days of the weeks of November 2026, named after the month', () => {
        render(FmMonthGrid, { props: props() });

        const grid = screen.getByRole('group', { name: 'November 2026' });

        expect(within(grid).getAllByRole('button')).toHaveLength(42);
        expect(within(grid).getAllByRole('button')[0]).toHaveAccessibleName(
            'Monday 26 Oct',
        );
    });

    it('draws only the five weeks the server sends for October 2026', () => {
        render(FmMonthGrid, {
            props: {
                ...props(),
                month: '2026-10',
                from: '2026-09-28',
                to: '2026-11-01',
                days: [],
            },
        });

        const buttons = within(
            screen.getByRole('group', { name: 'October 2026' }),
        ).getAllByRole('button');

        expect(buttons).toHaveLength(35);
        expect(buttons[0]).toHaveAccessibleName('Monday 28 Sep');
        expect(buttons.at(-1)).toHaveAccessibleName('Sunday 1 Nov');
    });

    it('draws only the four weeks of February 2027', () => {
        render(FmMonthGrid, {
            props: {
                ...props(),
                month: '2027-02',
                from: '2027-02-01',
                to: '2027-02-28',
                days: [],
            },
        });

        const buttons = within(
            screen.getByRole('group', { name: 'February 2027' }),
        ).getAllByRole('button');

        expect(buttons).toHaveLength(28);
        expect(buttons.at(-1)).toHaveAccessibleName('Sunday 28 Feb');
        expect(
            screen.getAllByTestId('weekday').map((day) => day.textContent),
        ).toEqual(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
    });

    it('reads the weekday with the day in Italian', async () => {
        const view = render(FmMonthGrid, {
            props: props({ '2026-11-04': [item('a'), item('b'), item('c')] }),
        });
        await speak('it');
        await view.rerender({ selected: '2026-11-05' });

        expect(
            screen.getByRole('button', { name: 'mercoledì 4 nov, 3 cose' }),
        ).toBeInTheDocument();
    });

    it('writes the weekdays, from Monday', () => {
        render(FmMonthGrid, { props: props() });

        expect(
            screen.getAllByTestId('weekday').map((day) => day.textContent),
        ).toEqual(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
    });

    it('emits the day that was tapped', async () => {
        const view = render(FmMonthGrid, { props: props() });

        await userEvent.click(
            screen.getByRole('button', { name: 'Thursday 12 Nov' }),
        );

        expect(view.emitted('select')).toEqual([['2026-11-12']]);
    });

    it('says how many things a day has, the chosen day and today', () => {
        render(FmMonthGrid, {
            props: props({
                '2026-11-04': [item('a'), item('b'), item('c')],
                '2026-11-05': [item('d')],
            }),
        });

        const chosen = screen.getByRole('button', {
            name: 'Wednesday 4 Nov, 3 things',
        });

        expect(chosen).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.getByRole('button', { name: 'Thursday 5 Nov, 1 thing' }),
        ).toHaveAttribute('aria-pressed', 'false');
        expect(
            screen.getByRole('button', { name: 'Monday 2 Nov' }),
        ).toHaveAttribute('aria-current', 'date');
        expect(
            screen.getByRole('button', { name: 'Tuesday 3 Nov' }),
        ).not.toHaveAttribute('aria-current');
    });

    describe('on the phone', () => {
        it('shows at most three dots for a day, and no titles', () => {
            const { container } = render(FmMonthGrid, {
                props: props({
                    '2026-11-04': [
                        item('a'),
                        item('b'),
                        item('c'),
                        item('d'),
                        item('e'),
                        item('f'),
                    ],
                }),
            });

            const day = screen.getByRole('button', {
                name: 'Wednesday 4 Nov, 6 things',
            });

            expect(day.querySelectorAll('[data-dot]')).toHaveLength(3);
            expect(screen.queryByText('Thing a')).not.toBeInTheDocument();
            expect(container.querySelectorAll('[data-dot]')).toHaveLength(3);
        });

        it('paints a late thing red', () => {
            render(FmMonthGrid, {
                props: props({ '2026-11-01': [item('a', { late: true })] }),
            });

            const dot = screen
                .getByRole('button', { name: 'Sunday 1 Nov, 1 thing' })
                .querySelector('[data-dot]');

            expect(dot).toHaveClass('bg-overdue');
        });
    });

    describe('on the computer', () => {
        const six = ['a', 'b', 'c', 'd', 'e', 'f'].map((key) => item(key));

        it('shows three titles and how many more', () => {
            render(FmMonthGrid, {
                props: props({ '2026-11-04': six }, true),
            });

            const day = screen.getByRole('button', {
                name: 'Wednesday 4 Nov, 6 things',
            });

            expect(within(day).getByText('Thing a')).toBeInTheDocument();
            expect(within(day).getByText('Thing c')).toBeInTheDocument();
            expect(within(day).queryByText('Thing d')).not.toBeInTheDocument();
            expect(within(day).getByText('+3 more')).toBeInTheDocument();
        });

        it('says «+1 more» for one thing left over', () => {
            render(FmMonthGrid, {
                props: props(
                    {
                        '2026-11-04': ['a', 'b', 'c', 'd'].map((key) =>
                            item(key),
                        ),
                    },
                    true,
                ),
            });

            expect(screen.getByText('+1 more')).toBeInTheDocument();
        });

        it('strikes a done thing and paints a late one red', () => {
            render(FmMonthGrid, {
                props: props(
                    {
                        '2026-11-01': [
                            item('a', { late: true }),
                            item('b', { done: true }),
                        ],
                    },
                    true,
                ),
            });

            expect(screen.getByText('Thing b')).toHaveClass('line-through');
            expect(screen.getByText('Thing a').parentElement).toHaveClass(
                'bg-overdue-soft',
            );
        });

        it('writes the month on the first day of it', () => {
            render(FmMonthGrid, { props: props({}, true) });

            expect(
                screen.getByRole('button', { name: 'Sunday 1 Nov' }),
            ).toHaveTextContent('1 Nov');
            expect(
                screen.getByRole('button', { name: 'Monday 2 Nov' }),
            ).not.toHaveTextContent('Nov');
        });

        it('writes the short month in lower case in Italian, as Italian does', async () => {
            const view = render(FmMonthGrid, { props: props({}, true) });
            // Every render installs the English of the tests: Italian comes
            // after it, and another day chosen draws the grid again
            await speak('it');
            await view.rerender({ selected: '2026-11-05' });

            expect(
                screen.getByRole('button', { name: 'martedì 1 dic' }),
            ).toHaveTextContent('1 dic');
            expect(
                screen.getByRole('button', { name: 'lunedì 26 ott' }),
            ).toBeInTheDocument();
        });

        it('draws the month in a card, with a line under the weekdays', () => {
            const { container } = render(FmMonthGrid, {
                props: props({}, true),
            });

            expect(container.firstElementChild).toHaveClass(
                'bg-card',
                'rounded-card',
                'shadow-card',
                'overflow-hidden',
            );
            expect(
                screen.getAllByTestId('weekday')[0]?.parentElement,
            ).toHaveClass('border-b', 'border-divider');
        });
    });

    describe('on the phone', () => {
        it('draws the month in a card', () => {
            const { container } = render(FmMonthGrid, { props: props() });

            expect(container.firstElementChild).toHaveClass(
                'bg-card',
                'rounded-card',
                'shadow-card',
            );
        });
    });
});
