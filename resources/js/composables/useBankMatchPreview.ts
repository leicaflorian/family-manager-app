import type { Ref } from 'vue';
import { onScopeDispose, ref, watch } from 'vue';
import type { BankMatchKind, BankMatchResult } from '@/lib/bankMatch';
import { BankMatchError, previewMatches } from '@/lib/bankMatch';

/** How long to wait after the last letter before asking the server, in milliseconds. */
const PREVIEW_DELAY_MS = 500;

/** What the preview reads from the form. */
export type BankMatchPreviewSources = {
    /** The rule written in the field. */
    pattern: () => string;
    /** The direction of the form. */
    kind: () => BankMatchKind;
    /** The account chosen in the form, null for none. */
    accountId: () => number | null;
};

/** What `useBankMatchPreview` gives to the field. */
export type UseBankMatchPreview = {
    /** What the rule recognises; null without a rule, while the first answer is awaited, and after an error. */
    result: Ref<BankMatchResult | null>;
    /** Why the rule does not work, as the server says it; null when it does. */
    error: Ref<string | null>;
    /** The server is being asked. */
    loading: Ref<boolean>;
};

/**
 * The transactions a rule recognises, half a second after the last letter of the field, so the person sees what
 * the rule would pay while writing it. An answer that is not for the last rule is dropped; an empty rule asks
 * nothing. It stops when the scope it runs in (the component) ends.
 *
 * @param sources - where to read the rule, the direction and the account
 * @returns the result, the error and whether a request is on its way
 */
export function useBankMatchPreview(
    sources: BankMatchPreviewSources,
): UseBankMatchPreview {
    const result = ref<BankMatchResult | null>(null);
    const error = ref<string | null>(null);
    const loading = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;
    let latest = 0;

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
     * Asks the server and keeps the answer if it is still the one that counts.
     *
     * @param pattern - the rule
     * @param kind - the direction
     * @param accountId - the account, null for none
     */
    async function load(
        pattern: string,
        kind: BankMatchKind,
        accountId: number | null,
    ): Promise<void> {
        const current = new AbortController();
        const mine = ++latest;

        controller = current;
        loading.value = true;

        try {
            const answer = await previewMatches(
                pattern,
                kind,
                accountId,
                current.signal,
            );

            if (mine === latest) {
                result.value = answer;
                error.value = null;
            }
        } catch (failure) {
            if (mine === latest && !current.signal.aborted) {
                result.value = null;
                error.value =
                    failure instanceof BankMatchError ? failure.message : null;
            }
        } finally {
            if (mine === latest) {
                loading.value = false;
            }
        }
    }

    /** Waits for a quiet moment, then asks about the rule of now. */
    function schedule(): void {
        cancel();
        const pattern = sources.pattern().trim();

        if (pattern === '') {
            latest++;
            result.value = null;
            error.value = null;
            loading.value = false;

            return;
        }

        timer = setTimeout(() => {
            timer = null;
            void load(pattern, sources.kind(), sources.accountId());
        }, PREVIEW_DELAY_MS);
    }

    watch([sources.pattern, sources.kind, sources.accountId], schedule, {
        immediate: true,
    });
    onScopeDispose(() => {
        cancel();
        latest++;
    });

    return { result, error, loading };
}
