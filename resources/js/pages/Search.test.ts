import { router } from '@inertiajs/vue3';
import { screen, waitFor, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import type { MockInstance } from 'vite-plus/test';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { WIDE_QUERY } from '@/composables/useWideLayout';
import { press, renderPage } from '@/test/page';
import Search from './Search.vue';

/**
 * A result as the server sends it.
 *
 * @param key - its key
 * @param group - its group
 * @param title - its title
 * @param more - the fields that differ from a plain document
 * @returns a search result
 */
function row(
    key: string,
    group: App.Enums.SearchGroup,
    title: string,
    more: Partial<App.Data.Search.SearchResultData> = {},
): App.Data.Search.SearchResultData {
    return {
        key,
        group,
        title,
        subtitle: null,
        date: null,
        amount: null,
        href: `/${key}`,
        area: null,
        icon: 'file-text',
        tone: 'neutral',
        flag: null,
        due_in: null,
        ...more,
    };
}

/** The cards found by «Panda». */
const cards: App.Data.Search.SearchGroupData = {
    group: 'cards',
    label: 'Cards',
    count: 3,
    results: [
        row('card-1', 'cards', 'Fiat Panda', {
            subtitle: 'Vehicles',
            area: 'vehicles',
            icon: null,
            tone: null,
        }),
        row('card-2', 'cards', 'RC auto Panda - UnipolSai', {
            subtitle: 'Insurance',
            date: '2026-11-22',
            area: 'insurance',
            icon: null,
            tone: null,
        }),
        row('card-3', 'cards', 'Finanziamento Panda', {
            subtitle: 'Banks and loans',
            area: 'banks',
            icon: null,
            tone: null,
            flag: 'Closed',
        }),
    ],
};

/** The documents found by «Panda». */
const documents: App.Data.Search.SearchGroupData = {
    group: 'documents',
    label: 'Documents',
    count: 3,
    results: [
        row('document-1', 'documents', 'Libretto di circolazione Fiat Panda', {
            date: '2019-03-12',
        }),
        row('document-2', 'documents', 'Polizza RC auto Panda 2026', {
            subtitle: 'UnipolSai',
            date: '2025-11-22',
        }),
        row('document-3', 'documents', 'Contratto finanziamento Panda', {
            date: '2024-01-15',
            flag: 'Archived',
        }),
    ],
};

/** The expenses found by «Panda». */
const movements: App.Data.Search.SearchGroupData = {
    group: 'movements',
    label: 'Expenses and incomes',
    count: 3,
    results: [
        row('expense-1', 'movements', 'Bollo Panda', {
            subtitle: 'Expense',
            date: '2026-02-28',
            amount: 186,
            icon: 'receipt',
            tone: 'expenses',
        }),
        row('expense-2', 'movements', 'Tagliando Panda', {
            subtitle: 'Expense',
            date: '2026-09-14',
            amount: 180,
            icon: 'receipt',
            tone: 'expenses',
        }),
        row('expense-3', 'movements', 'Gomme Panda', {
            subtitle: 'Expense',
            date: '2025-11-03',
            amount: 320,
            icon: 'receipt',
            tone: 'expenses',
        }),
    ],
};

/** The deadline found by «Panda». */
const dues: App.Data.Search.SearchGroupData = {
    group: 'dues',
    label: 'Deadlines',
    count: 1,
    results: [
        row('due-expense-1', 'dues', 'Rata finanziamento', {
            subtitle: 'Finanziamento Panda',
            amount: 189,
            area: 'banks',
            icon: null,
            tone: null,
            due_in: '2026-11-24',
        }),
    ],
};

/** The page of «Panda», as the server sends it. */
const panda: App.Data.Search.SearchResultsData = {
    query: 'Panda',
    type: null,
    total: 10,
    groups: [cards, documents, movements, dues],
};

/** «Panda» narrowed to the documents: the other groups are counted, not listed. */
const pandaDocuments: App.Data.Search.SearchResultsData = {
    query: 'Panda',
    type: 'documents',
    total: 10,
    groups: [
        { ...cards, results: [] },
        documents,
        { ...movements, results: [] },
        { ...dues, results: [] },
    ],
};

/** A search that finds nothing. */
const nothing: App.Data.Search.SearchResultsData = {
    query: 'Pandaa',
    type: null,
    total: 0,
    groups: [],
};

/** The suggestions for «Bol». */
const suggestions: App.Data.Search.SearchResultsData = {
    query: 'Bol',
    type: null,
    total: 2,
    groups: [
        {
            ...movements,
            count: 2,
            results: [
                row('expense-1', 'movements', 'Bollo Panda'),
                row('expense-9', 'movements', 'Bolletta gas'),
            ],
        },
    ],
};

/**
 * Makes the window wide, as a computer, or narrow, as a phone.
 *
 * @param on - true for the computer
 */
function wide(on: boolean): void {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches: on && query === WIDE_QUERY,
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
    }));
}

let get: MockInstance<typeof router.get>;
let visit: MockInstance<typeof router.visit>;

beforeEach(() => {
    get = vi.spyOn(router, 'get').mockImplementation(() => undefined);
    visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    vi.stubGlobal(
        'fetch',
        vi.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve(suggestions),
            }),
        ),
    );
    wide(false);
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

/** The search box of the page. */
const box = (): HTMLElement =>
    screen.getByRole('combobox', { name: 'Search in Family Manager' });

describe('Search', () => {
    it('brings the focus back into the box with ⌘K', () => {
        renderPage(Search, { results: panda });
        box().blur();

        press({ key: 'k', metaKey: true });

        expect(box()).toHaveFocus();
        expect(visit).not.toHaveBeenCalled();
    });

    it('has the title with the number of results and the text in the box', () => {
        renderPage(Search, { results: panda });

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: '10 results for «Panda»',
            }),
        ).toBeInTheDocument();
        expect(box()).toHaveValue('Panda');
    });

    it('has the chips with their numbers, «All» pressed', () => {
        renderPage(Search, { results: panda });

        const chips = screen
            .getAllByRole('button', { pressed: false })
            .map((chip) => chip.textContent?.replace(/\s+/g, ' ').trim());
        expect(chips).toEqual([
            'Cards 3',
            'Documents 3',
            'Expenses and incomes 3',
            'Deadlines 1',
        ]);
        expect(screen.getByRole('button', { pressed: true })).toHaveTextContent(
            /All results\s*10/,
        );
    });

    it('lists the four groups one after the other with their titles and rows', () => {
        renderPage(Search, { results: panda });

        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((h) => h.textContent?.replace(/\s+/g, ' ').trim()),
        ).toEqual([
            'Cards 3',
            'Documents 3',
            'Expenses and incomes 3',
            'Deadlines 1',
        ]);
        const documentsGroup = screen.getByRole('region', {
            name: 'Documents',
        });
        expect(within(documentsGroup).getAllByRole('link')).toHaveLength(3);
        expect(
            within(documentsGroup).getByRole('link', {
                name: /Polizza RC auto Panda 2026/,
            }),
        ).toHaveAttribute('href', '/document-2');
    });

    it('visits the page of one group from its chip, and of all from «All»', async () => {
        renderPage(Search, { results: panda });

        await userEvent.click(
            screen.getByRole('button', { name: /Documents/ }),
        );

        expect(get).toHaveBeenCalledWith(
            '/search',
            { q: 'Panda', type: 'documents' },
            { preserveState: false, replace: true },
        );

        get.mockClear();
        renderPage(Search, { results: pandaDocuments });
        await userEvent.click(
            screen.getAllByRole('button', { name: /^All results/ }).at(-1)!,
        );

        expect(get).toHaveBeenCalledWith(
            '/search',
            { q: 'Panda' },
            { preserveState: false, replace: true },
        );
    });

    it('narrowed to a group, shows only that group with its chip pressed', () => {
        renderPage(Search, { results: pandaDocuments });

        expect(screen.getByRole('button', { pressed: true })).toHaveTextContent(
            /Documents\s*3/,
        );
        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((h) => h.textContent),
        ).toEqual([expect.stringContaining('Documents')]);
        expect(screen.queryByText('Fiat Panda')).not.toBeInTheDocument();
    });

    it('narrowed to a group that found nothing, keeps the chips and says nothing was found', () => {
        wide(true);
        renderPage(Search, {
            results: { ...pandaDocuments, type: 'contacts' },
        });

        expect(
            screen.queryByRole('button', { pressed: true }),
        ).not.toBeInTheDocument();
        expect(screen.getAllByRole('button', { pressed: false })).toHaveLength(
            5,
        );
        expect(
            screen.getByRole('heading', {
                level: 2,
                name: 'No results for «Panda»',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Search' }),
        ).toBeInTheDocument();
    });

    it('with no result, says so with the three tips and no chips', () => {
        renderPage(Search, { results: nothing });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Search' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', {
                level: 2,
                name: 'No results for «Pandaa»',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Check how it is written. If you do not remember it well, try:',
            ),
        ).toBeInTheDocument();
        const tips = screen.getAllByRole('listitem');
        expect(tips).toHaveLength(3);
        expect(tips[0]).toHaveTextContent(
            'a part of the word: Pand also finds Panda',
        );
        expect(tips[1]).toHaveTextContent(
            'the name of who sent it: UnipolSai, Enel',
        );
        expect(tips[2]).toHaveTextContent(
            'an amount or a date: 84,20, 15/10/2026',
        );
        expect(
            screen.queryByRole('button', { pressed: false }),
        ).not.toBeInTheDocument();
    });

    it('without text, has the empty box with the focus and the line of what is searched', async () => {
        renderPage(Search, {
            results: { query: '', type: null, total: 0, groups: [] },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Search' }),
        ).toBeInTheDocument();
        await waitFor(() => expect(box()).toHaveFocus());
        expect(box()).toHaveValue('');
        expect(
            screen.queryByRole('heading', { level: 2 }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(/^I search in the names of the cards/),
        ).toBeInTheDocument();
    });

    it('searches with Enter', async () => {
        renderPage(Search, { results: panda });

        await userEvent.clear(box());
        await userEvent.type(box(), 'Panda bollo{Enter}');

        expect(get).toHaveBeenCalledWith(
            '/search',
            { q: 'Panda bollo' },
            { preserveState: false },
        );
    });

    it('while typing, shows the suggestions in place of the results; the arrows and Enter open one, Esc closes them', async () => {
        renderPage(Search, { results: panda });

        await userEvent.clear(box());
        await userEvent.type(box(), 'Bol');

        const menu = await screen.findByRole('listbox');
        expect(box()).toHaveAttribute('aria-expanded', 'true');
        expect(box()).toHaveAttribute('aria-controls', menu.parentElement!.id);
        expect(
            screen.queryByRole('region', { name: 'Documents' }),
        ).not.toBeInTheDocument();

        await userEvent.keyboard('{ArrowDown}{ArrowDown}');
        const chosen = screen
            .getAllByRole('option')
            .find((option) => option.getAttribute('aria-selected') === 'true');
        expect(chosen).toHaveTextContent('Bolletta gas');
        expect(box()).toHaveAttribute('aria-activedescendant', chosen!.id);

        await userEvent.keyboard('{Enter}');
        expect(visit).toHaveBeenCalledWith('/expense-9');
        expect(get).not.toHaveBeenCalled();

        await userEvent.keyboard('{Escape}');
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
        expect(box()).toHaveAttribute('aria-expanded', 'false');
        expect(
            screen.getByRole('region', { name: 'Documents' }),
        ).toBeInTheDocument();
    });

    it('the ✕ empties the box and closes the suggestions', async () => {
        renderPage(Search, { results: panda });

        await userEvent.clear(box());
        await userEvent.type(box(), 'Bol');
        await screen.findByRole('listbox');

        await userEvent.click(
            screen.getByRole('button', { name: 'Clear the search' }),
        );

        expect(box()).toHaveValue('');
        expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    });

    it('on the phone, goes back with the arrow, to the Home without a page before', async () => {
        const back = vi
            .spyOn(window.history, 'back')
            .mockImplementation(() => {});
        renderPage(Search, { results: panda });

        vi.spyOn(History.prototype, 'length', 'get').mockReturnValue(3);
        await userEvent.click(screen.getByRole('button', { name: 'Back' }));
        expect(back).toHaveBeenCalled();

        vi.spyOn(History.prototype, 'length', 'get').mockReturnValue(1);
        await userEvent.click(screen.getByRole('button', { name: 'Back' }));
        expect(visit).toHaveBeenCalledWith('/dashboard');
        expect(
            screen.queryByRole('link', { name: 'Home' }),
        ).not.toBeInTheDocument();
    });

    it('on the computer, while typing, shows the suggestions over the results, which stay', async () => {
        wide(true);
        renderPage(Search, { results: panda });

        await userEvent.clear(box());
        await userEvent.type(box(), 'Pan');

        expect(await screen.findByRole('listbox')).toBeInTheDocument();
        expect(box()).toHaveAttribute('aria-expanded', 'true');
        expect(
            screen.getByRole('region', { name: 'Documents' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { pressed: true })).toHaveTextContent(
            /All results\s*10/,
        );
    });

    it('on the computer, has the link to the Home above the title', () => {
        wide(true);
        renderPage(Search, { results: panda });

        expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute(
            'href',
            '/dashboard',
        );
        expect(
            screen.queryByRole('button', { name: 'Back' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('heading', {
                level: 1,
                name: '10 results for «Panda»',
            }),
        ).toBeVisible();
    });
});
