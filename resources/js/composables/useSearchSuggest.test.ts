import { render } from '@testing-library/vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h, ref, type Ref } from 'vue';
import { useSearchSuggest } from './useSearchSuggest';

type Results = App.Data.Search.SearchResultsData;

/**
 * Builds a response of the suggest route.
 *
 * @param marker - tells one response from another
 * @returns the fake response
 */
function reply(marker: string): Response {
    return {
        ok: true,
        json: () => Promise.resolve({ marker }),
    } as unknown as Response;
}

/**
 * Renders a page that uses the composable.
 *
 * @returns the query, the composable's state and how to unmount the page
 */
function renderSuggest(): {
    query: Ref<string>;
    state: ReturnType<typeof useSearchSuggest>;
    unmount: () => void;
} {
    const query = ref('');
    let state!: ReturnType<typeof useSearchSuggest>;
    const Page = defineComponent({
        setup() {
            state = useSearchSuggest(query);

            return () => h('div');
        },
    });
    const { unmount } = render(Page);

    return { query, state, unmount };
}

describe('useSearchSuggest', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });
    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('does not ask the server for a single letter', async () => {
        const fetchMock = vi.fn(() => Promise.resolve(reply('x')));
        vi.stubGlobal('fetch', fetchMock);
        const { query, state } = renderSuggest();

        query.value = 'P';
        await vi.advanceTimersByTimeAsync(500);

        expect(fetchMock).not.toHaveBeenCalled();
        expect(state.results.value).toBeNull();
    });

    it('asks once, 250 ms after the last letter', async () => {
        const fetchMock = vi.fn(() => Promise.resolve(reply('pan')));
        vi.stubGlobal('fetch', fetchMock);
        const { query, state } = renderSuggest();

        query.value = 'Pa';
        await vi.advanceTimersByTimeAsync(100);
        query.value = 'Pan';
        await vi.advanceTimersByTimeAsync(249);
        expect(fetchMock).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(1);

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, init] = fetchMock.mock.calls[0] as unknown as [
            string,
            RequestInit,
        ];
        expect(url).toBe('/search/suggest?q=Pan');
        expect(init.headers).toEqual({ Accept: 'application/json' });
        expect(state.results.value).toEqual({ marker: 'pan' });
        expect(state.loading.value).toBe(false);
    });

    it('drops an answer that arrives after a newer one', async () => {
        const resolvers: Record<string, (r: Response) => void> = {};
        const fetchMock = vi.fn((url: string) => {
            const q = new URL(url, 'http://x').searchParams.get('q') ?? '';

            return new Promise<Response>((resolve) => {
                resolvers[q] = resolve;
            });
        });
        vi.stubGlobal('fetch', fetchMock);
        const { query, state } = renderSuggest();

        query.value = 'Pa';
        await vi.advanceTimersByTimeAsync(250);
        query.value = 'Pan';
        await vi.advanceTimersByTimeAsync(250);
        resolvers.Pan(reply('Pan'));
        await vi.advanceTimersByTimeAsync(0);
        resolvers.Pa(reply('Pa'));
        await vi.advanceTimersByTimeAsync(0);

        expect((state.results.value as unknown as Results | null)).toEqual({
            marker: 'Pan',
        });
    });

    it('clears the results when the query gets shorter than two letters', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(reply('pan'))),
        );
        const { query, state } = renderSuggest();

        query.value = 'Pan';
        await vi.advanceTimersByTimeAsync(250);
        expect(state.results.value).not.toBeNull();

        query.value = 'P';
        await vi.advanceTimersByTimeAsync(0);

        expect(state.results.value).toBeNull();
    });

    it('does not ask once the page has gone', async () => {
        const fetchMock = vi.fn(() => Promise.resolve(reply('x')));
        vi.stubGlobal('fetch', fetchMock);
        const { query, unmount } = renderSuggest();

        query.value = 'Pan';
        unmount();
        await vi.advanceTimersByTimeAsync(500);

        expect(fetchMock).not.toHaveBeenCalled();
    });
});
