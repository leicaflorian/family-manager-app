import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/**
 * How many files wait in *To sort* (copies left out), as the server shares it with every page.
 *
 * @returns the number, zero when the server does not share it
 */
export function useToSortCount(): ComputedRef<number> {
    const page = usePage();

    return computed((): number => page.props.to_sort_count ?? 0);
}
