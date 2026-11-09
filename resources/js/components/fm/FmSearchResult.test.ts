import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmSearchResult from './FmSearchResult.vue';

/**
 * A result as the server sends it.
 *
 * @param extra - fields to change
 * @returns a search result
 */
function result(
    extra: Partial<App.Data.Search.SearchResultData> = {},
): App.Data.Search.SearchResultData {
    return {
        key: 'card:1',
        group: 'cards',
        title: 'Fiat Panda',
        subtitle: 'Vehicles',
        date: null,
        amount: null,
        href: '/cards/1',
        area: 'vehicles',
        icon: null,
        tone: null,
        flag: null,
        due_in: null,
        ...extra,
    };
}

describe('FmSearchResult', () => {
    it('is a link to its page', () => {
        render(FmSearchResult, { props: { result: result(), query: 'Pan' } });

        expect(screen.getByRole('link')).toHaveAttribute('href', '/cards/1');
    });

    it('marks the words of the query in the title', () => {
        render(FmSearchResult, { props: { result: result(), query: 'Pan' } });

        const mark = document.querySelector('mark');
        expect(mark).toHaveTextContent('Pan');
        expect(mark?.tagName).toBe('MARK');
    });

    it('writes the date and the amount, and says the flag', () => {
        render(FmSearchResult, {
            props: {
                result: result({
                    group: 'movements',
                    area: null,
                    icon: 'receipt',
                    tone: 'expenses',
                    date: '2026-09-14',
                    amount: 180,
                    flag: 'Archived',
                }),
                query: 'Pan',
            },
        });

        expect(screen.getByText(/14\/09\/2026/)).toBeInTheDocument();
        expect(screen.getByText(/180,00\s€/)).toBeInTheDocument();
        expect(screen.getByText(/Archived/)).toBeInTheDocument();
    });

    it('tells a deadline by the days left', () => {
        const day = new Date(Date.now() + 15 * 86_400_000)
            .toISOString()
            .slice(0, 10);

        render(FmSearchResult, {
            props: {
                result: result({ group: 'dues', due_in: day }),
                query: '',
            },
        });

        expect(screen.getByText(/in 1[45] days/)).toBeInTheDocument();
    });

    it('is the chosen row when active', () => {
        render(FmSearchResult, {
            props: { result: result(), query: '', active: true, compact: true },
        });

        expect(screen.getByRole('link')).toHaveClass('bg-canvas');
    });
});
