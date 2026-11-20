import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/** What the server shares about the link to the bank while it is on. */
export type BankFeedShare = {
    /** The transactions still to sort. */
    unsorted: number;
};

/**
 * The link to the bank, as the server shares it with every page: what is still to sort while the family has it on,
 * `null` while it is off (and on a phone linked with a code, which does not get it).
 *
 * @returns the share, `null` when the link is off
 */
export function useBankFeed(): ComputedRef<BankFeedShare | null> {
    const page = usePage();

    return computed((): BankFeedShare | null => page.props.bank_feed ?? null);
}
