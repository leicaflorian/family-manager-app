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

    it('a new filter from a group without a proposal asks the AI for title, category and organization when it opens', async () => {
        request.mockImplementation((config) =>
            Promise.resolve({
                status: 200,
                headers: {},
                data: JSON.stringify(
                    config.url === '/budget/bank/filters/suggest'
                        ? {
                              contains: ['SATISPAY'],
                              excludes: ['COMMISSIONI'],
                              title: 'Ricarica Satispay',
                              category: 'Varie',
                              category_id: 3,
                              organization: null,
                              organization_id: null,
                              organization_is_new: false,
                              explanation: 'Takes the Satispay top-ups.',
                          }
                        : bankFilterPreview,
                ),
            }),
        );
        renderPage(
            BankFilter,
            props({
                filter: null,
                draft: { ...satispay, id: null, action: null, title: null },
                ai: true,
            }),
        );

        expect(
            await screen.findByText('Takes the Satispay top-ups.'),
        ).toBeInTheDocument();
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({
                data: { keep: [9], drop: [], mode: 'fill' },
            }),
        );
        expect(screen.getByRole('textbox', { name: 'Title' })).toHaveValue(
            'Ricarica Satispay',
        );
        await pause();
    });
});
