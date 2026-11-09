import { onBeforeUnmount, ref, watch, type Ref } from 'vue';
import { suggest } from '@/routes/search';

/** How long to wait after the last change before asking the server. */
const DELAY_MS = 250;

/** The fewest characters, spaces apart, that are worth a search. */
const MIN_LENGTH = 2;

/**
 * Asks the server for the first results of a search while the person writes,
 * for the menu under the search field. Answers that are not for the last
 * query are dropped.
 *
 * @param query - what is written in the field
 * @returns the results (`null` below two characters) and whether a request is
 *   on its way
 */
export function useSearchSuggest(query: Ref<string>): {
    results: Ref<App.Data.Search.SearchResultsData | null>;
    loading: Ref<boolean>;
} {
    const results = ref<App.Data.Search.SearchResultsData | null>(null);
    const loading = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;
    let latest = '';

    /** Stops the waiting timer and the request on its way. */
    function cancel(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }

        controller?.abort();
        controller = null;
    }

    /**
     * Asks the server for a query and keeps the answer if it is still the
     * query that counts.
     *
     * @param q - the trimmed query
     */
    async function load(q: string): Promise<void> {
        controller?.abort();
        const current = new AbortController();

        controller = current;
        latest = q;
        loading.value = true;

        try {
            const response = await fetch(suggest.url({ query: { q } }), {
                headers: { Accept: 'application/json' },
                signal: current.signal,
            });

            if (!response.ok) {
                throw new Error(`Suggest answered ${response.status}`);
            }

            const data: App.Data.Search.SearchResultsData =
                await response.json();

            if (latest === q && !current.signal.aborted) {
                results.value = data;
            }
        } catch {
            // An aborted or failed request leaves the results as they were.
        } finally {
            if (controller === current) {
                loading.value = false;
            }
        }
    }

    watch(query, (value) => {
        cancel();
        const q = value.trim();

        if (q.replace(/\s/g, '').length < MIN_LENGTH) {
            latest = '';
            loading.value = false;
            results.value = null;

            return;
        }

        timer = setTimeout(() => {
            timer = null;
            void load(q);
        }, DELAY_MS);
    });

    onBeforeUnmount(cancel);

    return { results, loading };
}
