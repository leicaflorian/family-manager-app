import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/**
 * The name of the app («Fameja.app»), as the server shares it with every page (`config('app.name')`): the
 * wordmark, the tab title and the texts with `:app` read it from here, so the name lives in one place.
 *
 * @returns the name of the app
 */
export function useAppName(): ComputedRef<string> {
    const page = usePage();

    return computed((): string => page.props.name);
}
