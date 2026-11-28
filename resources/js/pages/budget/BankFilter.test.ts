import { http, router } from '@inertiajs/vue3';
import { fireEvent, screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import {
    bankFilterOptions,
    bankFilterPreview,
    bankFilters,
} from '@/test/fixtures';
import {
    choose,
    openMenu,
    renderPage,
    settle,
    shown,
    withTrail,
} from '@/test/page';
import { hasChanges } from '@/lib/unsavedChanges';
import BankFilter from './BankFilter.vue';

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const satispay = bankFilters[1]!;
const real = http.getClient();
const request = vi.fn<(config: Config) => Promise<unknown>>();

/** The props the server sends for the filter *Satispay*, or for a new filter. */
function props(changes: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        filter: satispay,
        draft: null,
        preview: bankFilterPreview,
        options: bankFilterOptions,
        can_pay: true,
        ai: false,
        ...changes,
    };
}

/** The preview the fake server answers: one row taken by another filter. */
const conflicting: App.Data.BankFilterPreviewData = {
    ...bankFilterPreview,
    rows: [
        {
            ...bankFilterPreview.rows[0]!,
            will: 'create',
            will_title: null,
            will_on: null,
            taken_by: 'Satispay ricariche',
        },
    ],
    summary: { pay: 0, create: 1, link: 0, transfer: 0, ignore: 0 },
    conflicts: 1,
};

/** Waits longer than the pause before the preview is asked. */
function pause(): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, 700));
}

beforeEach(() => {
    request.mockReset();
    request.mockResolvedValue({
        status: 200,
        headers: {},
        data: JSON.stringify(bankFilterPreview),
    });
    http.setClient({ request } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('The form of a bank filter', () => {
    it('writes the trail Budget > From the bank > the filter, which leads back on the phone', () => {
        const { state, provide } = withTrail();
        renderPage(BankFilter, props(), provide);

        expect(state.pieces.value).toEqual([
            { label: 'Budget', href: '/budget' },
            { label: 'From the bank', href: '/budget/bank?show=filters' },
            { label: 'Filter Satispay' },
        ]);
        expect(
            screen.getByRole('heading', {
                name: 'Edit the filter Satispay',
                level: 1,
            }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Close' })).toHaveAttribute(
            'href',
            '/budget/bank?show=filters',
        );
    });

    it('the words are tags: Enter adds one', async () => {
        renderPage(BankFilter, props({ filter: null }));
        const input = screen.getByRole('combobox', {
            name: 'Contains the words',
        });

        await userEvent.type(input, 'PAGOPA{Enter}');
        await settle();

        const field = input.closest<HTMLElement>('[data-type]')!;
        expect(within(field).getByText('PAGOPA')).toBeInTheDocument();
    });

    it('changing a field asks the preview after a pause and shows the rows', async () => {
        renderPage(
            BankFilter,
            props({
                filter: null,
                preview: {
                    rows: [],
                    summary: conflicting.summary,
                    conflicts: 0,
                },
            }),
        );

        await userEvent.type(
            screen.getByRole('combobox', { name: 'Contains the words' }),
            'SATISPAY{Enter}',
        );
        await settle();
        expect(request).not.toHaveBeenCalled();

        await pause();

        expect(request).toHaveBeenCalledTimes(1);
        const config = request.mock.calls[0]![0];
        expect(config.url).toBe('/budget/bank/filters/preview');
        expect(config.data).toMatchObject({
            contains: ['SATISPAY'],
            filter: null,
        });
        expect(
            await screen.findByRole('heading', {
                name: 'The movements it takes · 1',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('-10,00 €')).toBeInTheDocument();
    });

    it('the words found are highlighted', () => {
        renderPage(BankFilter, props());

        const marks = [...document.querySelectorAll('mark')].map(
            (mark) => mark.textContent,
        );
        expect(marks).toEqual(['DOMICILIAZIONE', 'SATISPAY']);
        expect(screen.getByText('+ 0,40 € on 25/11')).toBeInTheDocument();
        expect(
            screen.getByText('Link «Ricarica Satispay» of 23/11'),
        ).toBeInTheDocument();
    });

    it('the fee section has three choices and shows its words only with Recognise it with these words', async () => {
        renderPage(BankFilter, props());
        const fee = screen.getByRole('group', { name: /^Commission/ });

        expect(
            within(fee)
                .getAllByRole('radio')
                .map((radio) => radio.closest('label')?.textContent?.trim()),
        ).toEqual([
            expect.stringContaining('Find it by itself'),
            expect.stringContaining('Recognise it with these words'),
            expect.stringContaining('No commission'),
        ]);
        expect(
            within(fee).getByRole('combobox', { name: 'Contains the words' }),
        ).toBeInTheDocument();

        await userEvent.click(
            within(fee).getByRole('radio', { name: /Find it by itself/ }),
        );
        await settle();

        expect(
            within(fee).queryByRole('combobox', { name: 'Contains the words' }),
        ).not.toBeInTheDocument();
    });

    it('Create the expense shows title, category, organization, person, card and notes', () => {
        renderPage(BankFilter, props());
        const action = screen.getByRole('group', { name: 'Action' });

        expect(within(action).getByLabelText('Title')).toHaveValue(
            'Ricarica Satispay',
        );
        for (const label of [
            /^Category/,
            /^Organization/,
            /^Person/,
            /^Refers to/,
        ]) {
            expect(
                within(action).getByRole('combobox', { name: label }),
            ).toBeInTheDocument();
        }
        expect(within(action).getByLabelText(/^Notes/)).toBeInTheDocument();
        expect(
            within(action).getByText('Add the month to the title'),
        ).toBeInTheDocument();
        expect(
            within(action).getByText(
                'Amount, date and account come from the movement.',
            ),
        ).toBeInTheDocument();
    });

    it('Pay a due date shows the planned items and is hidden to a member', async () => {
        const admin = renderPage(BankFilter, props());

        await choose('What it does', 'Pay a due date');
        const list = await openMenu('Planned expense');
        expect(shown(list)).toEqual(['None', 'Retta danza', 'Bollo Panda']);
        expect(
            screen.queryByRole('combobox', { name: 'Category' }),
        ).not.toBeInTheDocument();
        admin.unmount();

        renderPage(BankFilter, props({ can_pay: false }));
        const actions = await openMenu('What it does');
        expect(shown(actions)).not.toContain('Pay a due date');
    });

    it('Save is off while a transaction is taken by another filter and the line says which', () => {
        renderPage(BankFilter, props({ preview: conflicting }));

        expect(
            screen.getByRole('button', { name: 'Save the filter' }),
        ).toBeDisabled();
        expect(
            screen.getByText(
                'Before saving: a movement is already taken by the filter Satispay ricariche.',
            ),
        ).toBeInTheDocument();
        const note = screen.getByRole('note');
        expect(note).toHaveTextContent(
            'The filter Satispay ricariche already takes it. Two filters on the same movement cannot be saved: change one of the two.',
        );
    });

    it('the summary line says what saving does', () => {
        renderPage(BankFilter, props());

        expect(
            screen.getByText('Saving, links 1 expense already recorded'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Save the filter' }),
        ).toBeEnabled();
    });

    it('saves the filter with its fields', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(BankFilter, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'Save the filter' }),
        );

        expect(put).toHaveBeenCalledWith(
            '/budget/bank/filters/1',
            expect.objectContaining({
                name: 'Satispay',
                contains: ['SATISPAY', 'DOMICILIAZIONE'],
                action: 'create',
                category_id: 3,
                fee_mode: 'words',
            }),
            expect.anything(),
        );
    });

    it('leaving with changes asks', async () => {
        renderPage(BankFilter, props());
        expect(hasChanges()).toBe(false);

        await fireEvent.update(
            screen.getByLabelText('Filter name'),
            'Satispay e ricariche',
        );
        await settle();

        expect(hasChanges()).toBe(true);
    });

    it('a change of direction clears what belongs to the other direction', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(
            BankFilter,
            props({ filter: { ...satispay, planned_expense_id: 7 } }),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Coming in' }),
        );
        await settle();
        await pause();
        await userEvent.click(
            screen.getByRole('button', { name: 'Save the filter' }),
        );

        const body = put.mock.calls[0]![1] as Record<string, unknown>;
        expect(body).toMatchObject({
            direction: 'income',
            planned_id: null,
            category_id: null,
            card: null,
        });
        expect(request.mock.calls.at(-1)![0].data).toMatchObject({
            planned_id: null,
            category_id: null,
        });
    });

    it('an income filter has no commission section and sends none', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(BankFilter, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'Coming in' }),
        );
        await settle();

        expect(
            screen.queryByRole('group', { name: /^Commission/ }),
        ).not.toBeInTheDocument();
        await pause();
        await userEvent.click(
            screen.getByRole('button', { name: 'Save the filter' }),
        );
        expect(put.mock.calls[0]![1]).toMatchObject({ fee_mode: 'none' });
    });

    it('Save waits while the preview of a change is on its way', async () => {
        renderPage(BankFilter, props());

        await fireEvent.update(screen.getByLabelText('Filter name'), 'Nuovo');
        await settle();
        expect(
            screen.getByRole('button', { name: 'Save the filter' }),
        ).toBeDisabled();

        await pause();
        expect(
            screen.getByRole('button', { name: 'Save the filter' }),
        ).toBeEnabled();
    });

    it('shows the conflict the server finds on save, also for an advanced filter', async () => {
        const message =
            'A movement to sort is already taken by the filter «Satispay ricariche»: change one of the two.';
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            options?.onError?.({ contains: message });
        });
        const first = renderPage(BankFilter, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'Save the filter' }),
        );
        expect(await screen.findByText(message)).toBeInTheDocument();
        first.unmount();

        renderPage(
            BankFilter,
            props({
                filter: { ...satispay, pattern: 'SATISPAY.*', contains: [] },
            }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Save the filter' }),
        );
        expect(await screen.findByText(message)).toBeInTheDocument();
    });

    it('the AI buttons are hidden without the AI', () => {
        renderPage(BankFilter, props({ ai: false }));

        expect(
            screen.queryByRole('button', { name: 'Write it with the AI' }),
        ).toBeNull();
        expect(
            screen.queryByRole('button', { name: 'Improve the filter' }),
        ).toBeNull();
    });

    it('Write it with the AI reads the movements the filter takes and fills the words', async () => {
        request.mockImplementation((config) =>
            Promise.resolve({
                status: 200,
                headers: {},
                data: JSON.stringify(
                    config.url === '/budget/bank/filters/suggest'
                        ? {
                              contains: ['SATISPAY', 'RICARICA'],
                              excludes: [],
                              title: null,
                              category: null,
                              category_id: null,
                              organization: null,
                              organization_id: null,
                              organization_is_new: false,
                              explanation: 'Takes the Satispay top-ups.',
                          }
                        : bankFilterPreview,
                ),
            }),
        );
        renderPage(BankFilter, props({ ai: true }));

        await userEvent.click(
            screen.getByRole('button', { name: 'Write it with the AI' }),
        );

        expect(
            await screen.findByText('Takes the Satispay top-ups.'),
        ).toBeInTheDocument();
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({
                url: '/budget/bank/filters/suggest',
                data: { keep: [9], drop: [], mode: 'words' },
            }),
        );
        await pause();
    });

    describe('the AI fills a new filter once, when asked with ai=1', () => {
        const fill = {
            contains: ['SATISPAY'],
            excludes: ['COMMISSIONI'],
            title: 'Ricarica Satispay',
            category: 'Varie',
            category_id: 3,
            organization: null,
            organization_id: null,
            organization_is_new: false,
            explanation: 'Takes the Satispay top-ups.',
        };
        const draft = { ...satispay, id: null, action: null, title: null };

        let replace: ReturnType<typeof vi.spyOn>;

        beforeEach(() => {
            replace = vi.spyOn(router, 'replace').mockImplementation(() => {});
            window.history.replaceState(
                null,
                '',
                '/budget/bank/filters/create?group=abc&ai=1',
            );
            request.mockImplementation((config) =>
                Promise.resolve({
                    status: 200,
                    headers: {},
                    data: JSON.stringify(
                        config.url === '/budget/bank/filters/suggest'
                            ? fill
                            : bankFilterPreview,
                    ),
                }),
            );
        });

        /** The calls made to the AI so far. */
        function suggestions(): unknown[] {
            return request.mock.calls.filter(
                ([config]) => config.url === '/budget/bank/filters/suggest',
            );
        }

        it('asks once, fills title and category, takes ai=1 out of the address and is not a change', async () => {
            renderPage(
                BankFilter,
                props({ filter: null, draft, ai: true, ask_ai: true }),
            );

            expect(
                await screen.findByText('Takes the Satispay top-ups.'),
            ).toBeInTheDocument();
            expect(suggestions()).toHaveLength(1);
            expect(request).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: { keep: [9], drop: [], mode: 'fill' },
                }),
            );
            expect(screen.getByRole('textbox', { name: 'Title' })).toHaveValue(
                'Ricarica Satispay',
            );
            expect(replace).toHaveBeenCalledWith(
                expect.objectContaining({
                    url: '/budget/bank/filters/create?group=abc',
                }),
            );
            expect(hasChanges()).toBe(false);
            await pause();
        });

        it('does not ask without ai=1, and a second mount without it does not ask again', async () => {
            const first = renderPage(
                BankFilter,
                props({ filter: null, draft, ai: true, ask_ai: false }),
            );
            await pause();
            first.unmount();
            renderPage(
                BankFilter,
                props({ filter: null, draft, ai: true, ask_ai: false }),
            );
            await pause();

            expect(suggestions()).toHaveLength(0);
            expect(replace).not.toHaveBeenCalled();
        });

        it('Write it with the AI works from the example movement while the filter takes none', async () => {
            renderPage(
                BankFilter,
                props({
                    filter: null,
                    draft: { ...draft, contains: [] },
                    preview: { ...bankFilterPreview, rows: [] },
                    example_ids: [41, 42],
                    ai: true,
                }),
            );

            await userEvent.click(
                screen.getByRole('button', { name: 'Write it with the AI' }),
            );

            await screen.findByText('Takes the Satispay top-ups.');
            expect(suggestions()[0]).toEqual([
                expect.objectContaining({
                    data: { keep: [41, 42], drop: [], mode: 'words' },
                }),
            ]);
            await pause();
        });

        it('the words are locked while the AI writes', async () => {
            /** The field of the words of the matches (the commission has one with the same label). */
            const words = (): HTMLElement =>
                screen
                    .getAllByRole('combobox', { name: 'Contains the words' })
                    .find(
                        (input) => input.getAttribute('name') === 'contains',
                    )!;

            let release: () => void = () => {};
            request.mockImplementation(
                (config) =>
                    new Promise((resolve) => {
                        const answer = {
                            status: 200,
                            headers: {},
                            data: JSON.stringify(
                                config.url === '/budget/bank/filters/suggest'
                                    ? fill
                                    : bankFilterPreview,
                            ),
                        };
                        if (config.url === '/budget/bank/filters/suggest') {
                            release = () => resolve(answer);
                        } else {
                            resolve(answer);
                        }
                    }),
            );
            renderPage(BankFilter, props({ ai: true }));

            await userEvent.click(
                screen.getByRole('button', { name: 'Write it with the AI' }),
            );

            expect(words()).toBeDisabled();
            release();
            await screen.findByText('Takes the Satispay top-ups.');
            expect(words()).toBeEnabled();
            await pause();
        });
    });

    describe('It is not of this group', () => {
        const base = bankFilterPreview.rows[0]!;

        /**
         * A row of the preview of the filter *Eni (POS)*.
         *
         * @param id - the id of the movement
         * @param text - its text
         * @param amount - its amount
         */
        function eni(
            id: number,
            text: string,
            amount: number,
        ): App.Data.BankFilterPreviewRowData {
            return {
                ...base,
                transaction: {
                    ...base.transaction,
                    id,
                    text,
                    amount,
                    counterparty: 'Eni',
                    booked_on: '2026-11-15',
                    value_on: '2026-11-15',
                },
                hits: ['ENI', 'POS'],
                fee: null,
                will: 'create',
                will_title: null,
                will_on: null,
            };
        }

        const kept = [
            eni(21, 'PAGAMENTO POS ENI STATION VIA EMILIA BOLOGNA', -45),
            eni(22, 'PAGAMENTO POS ENI STATION VIA STALINGRADO BOLOGNA', -38.5),
        ];
        const wash = eni(23, 'PAGAMENTO POS ENI LAVAGGIO SELF MODENA', -12);
        const before: App.Data.BankFilterPreviewData = {
            ...bankFilterPreview,
            rows: [...kept, wash],
            summary: { pay: 0, create: 3, link: 0, transfer: 0, ignore: 0 },
            conflicts: 0,
        };
        const draft = {
            ...satispay,
            id: null,
            name: 'Eni (POS)',
            contains: ['ENI', 'POS'],
            excludes: [],
            fee_mode: 'none',
        };
        const refined: App.Data.BankFilterRefinementData = {
            contains: ['ENI', 'POS'],
            excludes: ['LAVAGGIO'],
            amount_min: null,
            amount_max: null,
            change: 'excludes',
            value: 'LAVAGGIO',
        };

        /**
         * The fake server: the preview with `drop` answers `dropAnswer`, the AI answers the words, every other call ok.
         *
         * @param dropAnswer - what the preview with drop gives
         */
        function server(
            dropAnswer: Partial<App.Data.BankFilterPreviewData>,
            leaveRefused: string | null = null,
        ): void {
            request.mockImplementation((config) => {
                if (leaveRefused !== null && config.url.endsWith('/leave')) {
                    return Promise.reject({
                        response: {
                            status: 422,
                            data: {
                                message: leaveRefused,
                                errors: { group: [leaveRefused] },
                            },
                        },
                    });
                }

                const data = (config.data ?? {}) as Record<string, unknown>;
                let body: unknown = {};

                if (config.url === '/budget/bank/filters/preview') {
                    body =
                        data.drop === 23
                            ? { ...before, ...dropAnswer }
                            : { ...before, rows: kept };
                } else if (config.url === '/budget/bank/filters/suggest') {
                    body = {
                        contains: ['ENI', 'STATION'],
                        excludes: [],
                        title: null,
                        category: null,
                        category_id: null,
                        organization: null,
                        organization_id: null,
                        organization_is_new: false,
                        explanation: 'Takes the refuels only.',
                    };
                }

                return Promise.resolve({
                    status: config.url.endsWith('/leave') ? 204 : 200,
                    headers: {},
                    data: JSON.stringify(body),
                });
            });
        }

        /** Renders the form of the filter of the group *Eni (POS)*. */
        function renderEni(changes: Record<string, unknown> = {}): void {
            renderPage(
                BankFilter,
                props({
                    filter: null,
                    draft,
                    preview: before,
                    group: { key: 'abc', name: 'Eni (POS)' },
                    example_ids: [21, 22, 23],
                    ...changes,
                }),
            );
        }

        /**
         * Taps the button of the car wash that takes it out.
         *
         * @param name - the label of the button
         */
        async function dropWash(
            name = 'It is not of this group',
        ): Promise<void> {
            const list = screen.getByRole('region', {
                name: 'The movements it takes · 3',
            });
            const row = within(list)
                .getAllByRole('listitem')
                .find((item) => item.textContent?.includes('LAVAGGIO'))!;

            await userEvent.click(within(row).getByRole('button', { name }));
        }

        /** The calls made to an address so far. */
        function calls(url: string): Config[] {
            return request.mock.calls
                .map(([config]) => config)
                .filter((config) => config.url === url);
        }

        /** The field of the words the text must not have. */
        function excludesField(): HTMLElement {
            return screen
                .getAllByRole('combobox', { name: /^Does not contain/ })
                .find((input) => input.getAttribute('name') === 'excludes')!
                .closest<HTMLElement>('[data-type]')!;
        }

        it('after It is not of this group the form says what it changed and how many it takes now', async () => {
            server({ rows: kept, refined, ai: false });
            renderEni();

            await dropWash();

            const status = await screen.findByText(
                (_, element) =>
                    element?.textContent ===
                    'I changed the filter: LAVAGGIO in Does not contain.',
            );
            expect(status).toBeInTheDocument();
            expect(
                screen.getByText(
                    'Now it takes 2 movements, all those of the group but the one taken out.',
                ),
            ).toBeInTheDocument();
            expect(
                within(excludesField()).getByText('LAVAGGIO'),
            ).toBeInTheDocument();
            expect(
                screen.getByText(
                    'Added now: only the movement taken out has this word.',
                ),
            ).toBeInTheDocument();
            expect(calls('/budget/bank/filters/preview')[0]).toEqual(
                expect.objectContaining({
                    data: expect.objectContaining({
                        drop: 23,
                        contains: ['ENI', 'POS'],
                    }),
                }),
            );
            // The movement leaves the group for good
            expect(calls('/budget/bank/23/leave')).toEqual([
                expect.objectContaining({ data: { group: 'abc' } }),
            ]);
            expect(calls('/budget/bank/filters/suggest')).toHaveLength(0);
            await pause();
        });

        it('Undo the change puts the criteria back', async () => {
            server({ rows: kept, refined, ai: false });
            renderEni();
            await dropWash();
            await screen.findByText(
                'Now it takes 2 movements, all those of the group but the one taken out.',
            );

            await userEvent.click(
                screen.getByRole('button', { name: 'Undo the change' }),
            );
            await settle();

            expect(within(excludesField()).queryByText('LAVAGGIO')).toBeNull();
            expect(
                screen.queryByText(
                    'Now it takes 2 movements, all those of the group but the one taken out.',
                ),
            ).toBeNull();
            await pause();
        });

        it('the box asks what the dropped movement is', async () => {
            server({ rows: kept, refined, ai: false });
            renderEni();
            await dropWash();

            const box = await screen.findByRole('region', {
                name: 'Movement taken out',
            });
            expect(
                within(box).getByText('Taken out of the group Eni (POS)'),
            ).toBeInTheDocument();
            expect(
                within(box).getByText('It does not come back'),
            ).toBeInTheDocument();
            expect(
                within(box).getByText('What is this movement?'),
            ).toBeInTheDocument();
            expect(
                within(box).getByRole('button', {
                    name: 'It belongs to an expense',
                }),
            ).toBeInTheDocument();
            expect(
                within(box).getByRole('link', { name: 'Create the expense' }),
            ).toHaveAttribute('href', '/budget/movements/create?bank=23');
            expect(
                within(box).getByRole('button', { name: 'It is a transfer' }),
            ).toBeInTheDocument();

            await userEvent.click(
                within(box).getByRole('button', { name: 'Ignore' }),
            );

            expect(
                await within(box).findByText('Done: the movement is sorted.'),
            ).toBeInTheDocument();
            expect(calls('/budget/bank/23/ignore')).toHaveLength(1);
            await pause();
        });

        it('after It is not of this group the AI is asked only when nothing was found', async () => {
            server({ refined: null, ai: true });
            renderEni({ ai: true });

            await dropWash();

            expect(
                await screen.findByText('Takes the refuels only.'),
            ).toBeInTheDocument();
            expect(calls('/budget/bank/filters/suggest')).toEqual([
                expect.objectContaining({
                    data: { keep: [21, 22], drop: [23], mode: 'words' },
                }),
            ]);
            await pause();
        });

        it('without the AI it says nothing was found', async () => {
            server({ refined: null, ai: false });
            renderEni();

            await dropWash();

            expect(
                await screen.findByText(
                    'I found no way to leave it out: change the words yourself.',
                ),
            ).toBeInTheDocument();
            expect(calls('/budget/bank/filters/suggest')).toHaveLength(0);
            await pause();
        });

        it('Undo the change puts back only what it changed', async () => {
            // The server answers the other words written another way: only Does not contain is its change
            server({
                rows: kept,
                refined: { ...refined, contains: ['eni', 'pos'] },
                ai: false,
            });
            renderEni();
            await dropWash();
            await screen.findByText(
                'Now it takes 2 movements, all those of the group but the one taken out.',
            );
            const contains = screen
                .getAllByRole('combobox', { name: 'Contains the words' })
                .find((input) => input.getAttribute('name') === 'contains')!
                .closest<HTMLElement>('[data-type]')!;

            expect(within(contains).getByText('ENI')).toBeInTheDocument();

            await userEvent.click(
                screen.getByRole('button', { name: 'Undo the change' }),
            );
            await settle();

            expect(within(excludesField()).queryByText('LAVAGGIO')).toBeNull();
            expect(within(contains).getByText('ENI')).toBeInTheDocument();
            await pause();
        });

        it('the box of the change goes once the words are changed by hand', async () => {
            server({ rows: kept, refined, ai: false });
            renderEni();
            await dropWash();
            await screen.findByText(
                'Now it takes 2 movements, all those of the group but the one taken out.',
            );

            await userEvent.type(
                screen
                    .getAllByRole('combobox', { name: 'Contains the words' })
                    .find(
                        (input) => input.getAttribute('name') === 'contains',
                    )!,
                'STATION{Enter}',
            );
            await settle();

            expect(
                screen.queryByText(
                    'Now it takes 2 movements, all those of the group but the one taken out.',
                ),
            ).toBeNull();
            expect(
                screen.queryByText(
                    'Added now: only the movement taken out has this word.',
                ),
            ).toBeNull();
            await pause();
        });

        it('a movement already sorted only changes the filter: it does not leave the group and is not asked about', async () => {
            const linked = { ...wash, will: 'linked' };
            server({ rows: kept, refined, ai: false });
            renderEni({ preview: { ...before, rows: [...kept, linked] } });

            await dropWash();
            await screen.findByText(
                'Now it takes 2 movements, all those of the group but the one taken out.',
            );

            expect(calls('/budget/bank/23/leave')).toHaveLength(0);
            expect(
                screen.queryByRole('region', { name: 'Movement taken out' }),
            ).toBeNull();
            await pause();
        });

        it('a movement of another group does not leave this one', async () => {
            server({ rows: kept, refined, ai: false });
            renderEni({ example_ids: [21, 22] });

            await dropWash();
            await screen.findByText(
                'Now it takes 2 movements, all those of the group but the one taken out.',
            );

            expect(calls('/budget/bank/23/leave')).toHaveLength(0);
            expect(
                screen.queryByRole('region', { name: 'Movement taken out' }),
            ).toBeNull();
            await pause();
        });

        it('a refusal to leave the group shows in the box, not over the list', async () => {
            server(
                { rows: kept, refined, ai: false },
                'This movement is not in this group any more: look at the groups again.',
            );
            renderEni();

            await dropWash();

            const box = await screen.findByRole('region', {
                name: 'Movement taken out',
            });
            expect(
                await within(box).findByText(
                    'This movement is not in this group any more: look at the groups again.',
                ),
            ).toBeInTheDocument();
            const list = screen.getByRole('region', {
                name: /The movements it takes/,
            });
            expect(within(list).queryByRole('alert')).toBeNull();
            await pause();
        });

        it('an advanced filter has no button to take a movement out', () => {
            renderEni({
                draft: { ...draft, pattern: 'ENI.*POS', contains: [] },
            });

            expect(
                screen.queryByRole('button', {
                    name: 'It is not of this group',
                }),
            ).toBeNull();
        });

        it('a filter that does not come from a group says Leave it out', async () => {
            server({ rows: kept, refined, ai: false });
            renderEni({ group: null, example_ids: [] });

            await dropWash('Leave it out');
            const box = await screen.findByRole('region', {
                name: 'Movement taken out',
            });

            expect(
                screen.queryByRole('button', {
                    name: 'It is not of this group',
                }),
            ).toBeNull();
            expect(
                screen.getByText(
                    'Now it takes 2 movements, all of them but the one taken out.',
                ),
            ).toBeInTheDocument();
            expect(
                within(box).getByText('Taken out of the filter'),
            ).toBeInTheDocument();
            expect(calls('/budget/bank/23/leave')).toHaveLength(0);
            await pause();
        });
    });
});
