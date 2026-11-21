import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { consumptionPage } from '@/test/fixtures';
import { renderPage, screenWidth, settle, withTrail } from '@/test/page';
import Consumption from './Consumption.vue';

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

/**
 * The page with the props of the server, changed where a test needs it.
 *
 * @param extra - the fields of the page data to change
 * @returns what Testing Library renders
 */
function page(
    extra: Partial<App.Data.Consumption.ConsumptionPageData> = {},
): ReturnType<typeof renderPage> {
    return renderPage(Consumption, {
        consumption: { ...structuredClone(consumptionPage), ...extra },
    });
}

/** Stops the Inertia visits and hands back the spy, to read the address asked. */
function visits(): ReturnType<typeof vi.spyOn> {
    return vi.spyOn(router, 'visit').mockImplementation(() => {});
}

/**
 * The address of the last visit asked through `router.visit`.
 *
 * @param spy - the spy of `visits()`
 * @returns the address, with its query
 */
function lastUrl(spy: ReturnType<typeof vi.spyOn>): string {
    const url = spy.mock.calls.at(-1)?.[0] as string | URL;

    return String(url);
}

describe('Consumption', () => {
    it('tells the trail Budget › Usage and has no tabs of the Budget', () => {
        const { state, provide } = withTrail();
        renderPage(
            Consumption,
            { consumption: structuredClone(consumptionPage) },
            provide,
        );

        expect(state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'Usage' },
        ]);
        expect(
            screen.getByRole('heading', { level: 1, name: 'Usage' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Movements' }),
        ).not.toBeInTheDocument();
    });

    it('offers only the types of the family, and a type opens its first utility', async () => {
        const visit = visits();
        page({
            types: [
                { type: 'electricity', label: 'Electricity', utility: 1 },
                { type: 'gas', label: 'Gas', utility: 7 },
            ],
        });

        const types = screen.getByRole('navigation', { name: 'Utility' });
        expect(
            within(types)
                .getAllByRole('link')
                .map((link) => link.textContent?.trim()),
        ).toEqual(['Electricity', 'Gas']);
        expect(
            within(types).getByRole('link', { name: 'Electricity' }),
        ).toHaveAttribute('aria-current', 'page');

        await userEvent.click(within(types).getByRole('link', { name: 'Gas' }));

        expect(lastUrl(visit)).toContain('/budget/consumption?utility=7');
    });

    it('writes the utility shown, and its name is a menu when the type has two', async () => {
        const visit = visits();
        const first = page();

        expect(
            screen.getByText('Electricity - Edison · Casa di via Roma'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', {
                name: /Electricity - Edison · Casa di via Roma/,
            }),
        ).not.toBeInTheDocument();
        first.unmount();

        page({
            utilities: [
                {
                    id: 1,
                    title: 'Gas - Eni · Casa di via Roma',
                    start: null,
                    end: '2026-01-31',
                },
                {
                    id: 9,
                    title: 'Gas - Hera · Casa di via Roma',
                    start: '2026-02-01',
                    end: null,
                },
            ],
            utility: {
                id: 1,
                title: 'Gas - Eni · Casa di via Roma',
                type: 'gas',
                unit: 'smc',
                href: 'http://localhost/cards/utility/1',
            },
        });

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Gas - Eni · Casa di via Roma',
            }),
        );
        const other = await screen.findByRole('menuitem', {
            name: /Gas - Hera · Casa di via Roma/,
        });
        expect(other).toHaveTextContent('From 01/02/2026');
        expect(
            screen.getByRole('menuitem', {
                name: /Gas - Eni · Casa di via Roma/,
            }),
        ).toHaveTextContent('Until 31/01/2026');

        await userEvent.click(other);

        expect(lastUrl(visit)).toContain('/budget/consumption?utility=9');
    });

    it('goes a year back with the arrow, and Today goes back to the latest months', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const first = page({ month: '2026-01' });

        expect(
            screen.getByText('October 2025 – September 2026'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'A year later' }),
        ).toBeDisabled();
        expect(
            screen.queryByRole('button', { name: 'Today' }),
        ).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'A year earlier' }),
        );

        // The month open in the detail is not among the 12 of a year before
        expect(get.mock.calls.at(-1)?.[0]).toBe(
            '/budget/consumption?utility=1&to=2025-09',
        );
        first.unmount();

        page({
            from: '2024-10',
            to: '2025-09',
            previous: null,
            next: '2026-09',
        });
        await userEvent.click(screen.getByRole('button', { name: 'Today' }));

        expect(get.mock.calls.at(-1)?.[0]).toBe(
            '/budget/consumption?utility=1',
        );
    });

    it('shows the kWh of the months, and the euros with Spending', async () => {
        page();

        expect(screen.getByText('kWh per month')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /^January 2026: 290 kWh/ }),
        ).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Spending' }));
        await settle();

        expect(screen.getByText('Spending per month')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /^January 2026: 86,10 €/ }),
        ).toBeInTheDocument();
    });

    it('opens the month of a bar, keeping the state and the scroll', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        page({ to: '2026-09' });

        expect(
            screen.queryByRole('region', { name: 'January 2026' }),
        ).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: /^January 2026:/ }),
        );

        expect(get).toHaveBeenCalledWith(
            '/budget/consumption?utility=1&to=2026-09&month=2026-01',
            {},
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    });

    it('shows the detail of the open month: consumption, spending, price, the year before and its bill', () => {
        page({ month: '2026-01' });

        const detail = screen.getByRole('region', { name: 'January 2026' });
        expect(
            within(detail).getByText('Consumption').parentElement,
        ).toHaveTextContent('290 kWh');
        expect(
            within(detail).getByText('Spending').parentElement,
        ).toHaveTextContent('86,10 €');
        expect(
            within(detail).getByText('Price').parentElement,
        ).toHaveTextContent('0,30 €per kWh');
        expect(detail).toHaveTextContent(
            '42 kWh more than January 2025 (248 kWh, 0,29 € per kWh)',
        );
        expect(detail).toHaveTextContent('The bill of this month');
        const bill = within(detail).getByRole('link', {
            name: 'Bill of January 2026',
        });
        expect(bill).toHaveAttribute('href', '/documents/116');
        expect(detail).toHaveTextContent(
            '01/01 – 31/01/2026 · 290 kWh · 86,10 €',
        );
        expect(detail).toHaveTextContent('Bill paid');
        // Correcting opens the panel of the consumption, which comes with the next step
        expect(
            within(detail).getByRole('button', {
                name: 'Correct the consumption of Bill of January 2026',
            }),
        ).toBeInTheDocument();
    });

    it('says a bill of the open month is still to pay, and closes the month', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        page({ month: '2026-09' });

        const detail = screen.getByRole('region', { name: 'September 2026' });
        expect(detail).toHaveTextContent('To pay');

        await userEvent.click(
            within(detail).getByRole('button', { name: 'Close the month' }),
        );

        expect(get.mock.calls.at(-1)?.[0]).toBe(
            '/budget/consumption?utility=1&to=2026-09',
        );
    });

    it('says less than the year before with the arrow down', () => {
        page({ month: '2026-08' });

        expect(
            screen.getByRole('region', { name: 'August 2026' }),
        ).toHaveTextContent('7 kWh less than August 2025');
    });

    it('shows the four figures and the sentence', () => {
        page();

        expect(
            screen.getByText('Last 12 months').parentElement,
        ).toHaveTextContent('2.711 kWh4% less than the year before');
        expect(screen.getByText('The bills').parentElement).toHaveTextContent(
            '853,10 €12, one still to pay',
        );
        expect(screen.getByText('On average').parentElement).toHaveTextContent(
            '0,31 €per kWh, everything included',
        );
        expect(screen.getByText('September').parentElement).toHaveTextContent(
            '0,39 €per kWh, the dearest',
        );
        expect(
            screen.getByText(/the price changed/).closest('[role="status"]'),
        ).toHaveTextContent(
            'In September you paid the most for electricity this year',
        );
    });

    it('counts the months with data when the year before cannot be compared', () => {
        page({
            figures: {
                ...consumptionPage.figures,
                previousQuantity: null,
                coveredMonths: 9,
                toPay: 0,
                bills: 4,
            },
            sentence: null,
        });

        expect(
            screen.getByText('Last 12 months').parentElement,
        ).toHaveTextContent('9 months with data');
        expect(screen.getByText('The bills').parentElement).toHaveTextContent(
            '4, all paid',
        );
    });

    it('lists the bills one per row, the dearer and the ones to check marked, each opening its document', () => {
        screenWidth(true);
        page();

        const table = screen.getByRole('table', { name: 'Bill by bill' });
        const rows = within(table).getAllByRole('row').slice(1);
        expect(rows).toHaveLength(12);
        expect(rows[0]).toHaveTextContent('Bill of September 2026');
        expect(rows[0]).toHaveTextContent('205 kWh');
        expect(rows[0]).toHaveTextContent('0,39 € ↑');
        expect(rows[0]).toHaveTextContent('To pay');
        expect(rows[1]).not.toHaveTextContent('↑');
        expect(rows[3]).toHaveTextContent('Check this consumption');
        expect(rows[4]).toHaveTextContent('Estimated');
        expect(
            within(rows[0]!).getByRole('link', {
                name: 'Bill of September 2026',
            }),
        ).toHaveAttribute('href', '/documents/124');
        // Written by hand: no document to open
        expect(within(rows[11]!).queryByRole('link')).not.toBeInTheDocument();
        expect(
            screen.getByText(
                'We read consumption and period from the bills, together with the amount. The amount is the total of the bill, with transport, charges and VAT.',
            ),
        ).toBeInTheDocument();
    });

    it('lists the bills as rows on the phone, each opening its document', () => {
        page();

        const list = screen.getByRole('list', { name: 'Bill by bill' });
        const rows = within(list).getAllByRole('listitem');
        expect(rows).toHaveLength(12);
        expect(
            within(rows[0]!).getByRole('link', {
                name: /Bill of September 2026/,
            }),
        ).toHaveAttribute('href', '/documents/124');
        expect(rows[0]).toHaveTextContent('0,39 € per kWh ↑');
        expect(rows[3]).toHaveTextContent('Check this consumption');
    });

    it('lists the bills read whose consumption could not be saved', () => {
        page({
            toCheck: [
                {
                    document_id: 40,
                    title: 'Bolletta gas 14/12/2025',
                    date: '2025-12-14',
                    reason: 'No consumption found',
                },
            ],
        });

        const section = screen.getByRole('heading', { name: 'To check · 1' })
            .parentElement!.parentElement!;
        expect(
            within(section).getByRole('link', {
                name: 'Bolletta gas 14/12/2025',
            }),
        ).toHaveAttribute('href', '/documents/40');
        expect(section).toHaveTextContent('No consumption found');
    });

    it('offers to read the bills in place of the chart when there are bills but no consumption', () => {
        page({
            bills: [],
            months: consumptionPage.months.map((month) => ({
                ...month,
                quantity: null,
                amount: null,
                price: null,
                coverage: 'none',
                billIds: [],
            })),
            archive: {
                count: 14,
                cost: 0.09,
                left: 968,
                running: false,
                can_read: true,
            },
        });

        expect(
            screen.getByRole('heading', {
                name: '14 bills without consumption',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'I read them one by one and take consumption and period. Reading them costs about 0,09 $ (14 readings, you have 968 left this month).',
            ),
        ).toBeInTheDocument();
        // Reading them comes with the next step
        expect(
            screen.getByRole('button', { name: 'Read the bills' }),
        ).toBeDisabled();
        expect(screen.queryByText('kWh per month')).not.toBeInTheDocument();
        expect(screen.queryByText('Last 12 months')).not.toBeInTheDocument();
    });

    it('asks for a bill when there is none', () => {
        page({
            bills: [],
            archive: null,
            months: consumptionPage.months.map((month) => ({
                ...month,
                quantity: null,
                amount: null,
                coverage: 'none',
                billIds: [],
            })),
            utility: {
                id: 3,
                title: 'Water - Hera · Casa di via Roma',
                type: 'water',
                unit: 'm3',
                href: 'http://localhost/cards/utility/3',
            },
        });

        expect(
            screen.getByRole('heading', {
                name: "Upload a bill: I'll read the consumption",
            }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Upload' })).toHaveAttribute(
            'href',
            '/upload',
        );
        expect(screen.queryByText('m³ per month')).not.toBeInTheDocument();
    });

    it('puts the month beside the chart on a wide screen', () => {
        screenWidth(true);
        page({ month: '2026-01' });

        const detail = screen.getByRole('region', { name: 'January 2026' });
        expect(detail.parentElement).toHaveClass('sticky');
    });
});
