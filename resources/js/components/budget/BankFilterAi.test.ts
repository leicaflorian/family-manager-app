import { http } from '@inertiajs/vue3';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { formValues } from '@/lib/bankFilters';
import type { BankFilterFormValues } from '@/lib/bankFilters';
import { bankFilters } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import BankFilterAi from './BankFilterAi.vue';

type Client = ReturnType<typeof http.getClient>;
type Config = Parameters<Client['request']>[0];

const real = http.getClient();
const request = vi.fn<(config: Config) => Promise<unknown>>();

/** What the server answers for a filter of words. */
const words: App.Data.Banks.BankFilterSuggestionData = {
    contains: ['ENEL', 'ENERGIA'],
    excludes: ['STORNO'],
    title: null,
    category: null,
    category_id: null,
    organization: null,
    organization_id: null,
    organization_is_new: false,
    explanation: 'Takes the bills of Enel.',
};

/** What it answers in the fill mode: a title, a category of the family and a new organization. */
const filled: App.Data.Banks.BankFilterSuggestionData = {
    ...words,
    title: 'Bolletta luce',
    category: 'Varie',
    category_id: 3,
    organization: 'Enel Energia',
    organization_id: null,
    organization_is_new: true,
};

/** Makes the fake server answer this. */
function answers(data: App.Data.Banks.BankFilterSuggestionData): void {
    request.mockResolvedValue({
        status: 200,
        headers: {},
        data: JSON.stringify(data),
    });
}

/** Renders the buttons over the form values of the filter *Satispay*. */
function show(
    props: {
        ai?: boolean;
        keep?: number[];
        auto?: boolean;
        drop?: number[];
    } = {},
): BankFilterFormValues {
    const values = formValues(bankFilters[1]!);

    renderPage(BankFilterAi, {
        modelValue: values,
        ai: true,
        keep: [9, 10],
        ...props,
    });

    return values;
}

beforeEach(() => {
    request.mockReset();
    http.setClient({ request } as unknown as Client);
});

afterEach(() => {
    http.setClient(real);
    vi.restoreAllMocks();
});

describe('BankFilterAi', () => {
    it('is hidden without the AI', () => {
        show({ ai: false });

        expect(
            screen.queryByRole('button', { name: 'Write it with the AI' }),
        ).toBeNull();
        expect(
            screen.queryByRole('button', { name: 'Improve the filter' }),
        ).toBeNull();
    });

    it('says the AI reads only the text of the movements', () => {
        show();

        expect(
            screen.getByText(
                'The AI reads only the text of the movements, never the amounts.',
            ),
        ).toBeInTheDocument();
    });

    it('Write it with the AI fills the words from the movements the filter takes', async () => {
        answers(words);
        const values = show();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Write it with the AI' }),
        );

        await waitFor(() =>
            expect(values.contains).toEqual(['ENEL', 'ENERGIA']),
        );
        expect(values.excludes).toEqual(['STORNO']);
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({
                url: '/budget/bank/filters/suggest',
                data: { keep: [9, 10], drop: [], mode: 'words' },
            }),
        );
        expect(
            screen.getByText('Takes the bills of Enel.'),
        ).toBeInTheDocument();
    });

    it('Improve the filter replaces the words and can be undone', async () => {
        answers(words);
        const values = show();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Improve the filter' }),
        );
        await waitFor(() =>
            expect(values.contains).toEqual(['ENEL', 'ENERGIA']),
        );

        await fireEvent.click(
            screen.getByRole('button', { name: 'Undo the change' }),
        );

        expect(values.contains).toEqual(['SATISPAY', 'DOMICILIAZIONE']);
        expect(values.excludes).toEqual(['COMMISSIONI']);
        expect(
            screen.queryByRole('button', { name: 'Undo the change' }),
        ).toBeNull();
    });

    it('Improve the filter sends the movements taken out, Write it does not', async () => {
        answers(words);
        show({ drop: [23] });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Improve the filter' }),
        );
        await screen.findByText('Takes the bills of Enel.');
        await fireEvent.click(
            screen.getByRole('button', { name: 'Write it with the AI' }),
        );
        await waitFor(() => expect(request).toHaveBeenCalledTimes(2));

        expect(request.mock.calls.map(([config]) => config.data)).toEqual([
            { keep: [9, 10], drop: [23], mode: 'words' },
            { keep: [9, 10], drop: [], mode: 'words' },
        ]);
    });

    it('on a group without a proposal Create the filter asks the AI and fills title, category and organization', async () => {
        answers(filled);
        const values = formValues({
            ...bankFilters[1]!,
            title: null,
            category_id: null,
            organization_id: null,
        });

        renderPage(BankFilterAi, {
            modelValue: values,
            ai: true,
            keep: [9, 10],
            auto: true,
        });

        await waitFor(() => expect(values.title).toBe('Bolletta luce'));
        expect(values.category_id).toBe('3');
        expect(values.organization_id).toBe('');
        expect(request).toHaveBeenCalledWith(
            expect.objectContaining({
                data: { keep: [9, 10], drop: [], mode: 'fill' },
            }),
        );
        expect(
            screen.getByText(
                'The AI suggests a new organization: Enel Energia. You can save the filter without it and add the organization later.',
            ),
        ).toBeInTheDocument();
    });

    it('does not ask by itself without the AI or without movements', async () => {
        show({ ai: false, auto: true });
        show({ keep: [], auto: true });
        await Promise.resolve();

        expect(request).not.toHaveBeenCalled();
    });

    it('stays off while no movement is taken, and says why', () => {
        show({ keep: [] });

        expect(
            screen.getByRole('button', { name: 'Write it with the AI' }),
        ).toBeDisabled();
        expect(
            screen.getByText(
                'The filter takes no movement yet, so the AI has nothing to read.',
            ),
        ).toBeInTheDocument();
    });

    it('tells why the AI could not write it and leaves the words alone', async () => {
        request.mockRejectedValue({
            response: {
                status: 422,
                data: JSON.stringify({
                    errors: { keep: ['Automatic reading is not on.'] },
                }),
            },
        });
        const values = show();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Write it with the AI' }),
        );

        expect(
            await screen.findByText('Automatic reading is not on.'),
        ).toBeInTheDocument();
        expect(values.contains).toEqual(['SATISPAY', 'DOMICILIAZIONE']);
    });

    it('has no buttons on an advanced filter', () => {
        renderPage(BankFilterAi, {
            modelValue: formValues({ ...bankFilters[0]!, pattern: 'ENEL.*' }),
            ai: true,
            keep: [9],
        });

        expect(
            screen.queryByRole('button', { name: 'Write it with the AI' }),
        ).toBeNull();
    });

    it('says when it starts and when it ends, and tells that it filled by itself', async () => {
        answers(filled);
        const values = formValues(bankFilters[1]!);
        const view = renderPage(BankFilterAi, {
            modelValue: values,
            ai: true,
            keep: [9],
            auto: true,
        });

        await waitFor(() => expect(values.title).toBe('Bolletta luce'));

        expect(view.emitted('busy')).toEqual([[true], [false]]);
        expect(view.emitted('filled')).toHaveLength(1);
    });
});
