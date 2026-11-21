import { render } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent } from 'vue';
import { useBankFeed } from './useBankFeed';

const shared = vi.hoisted(() => ({
    bank_feed: undefined as { unsorted: number } | null | undefined,
}));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { ...shared } }),
}));

/** Mounts a component that reads the link to the bank and gives it back. */
function feed(): { unsorted: number } | null {
    let value: { unsorted: number } | null = { unsorted: -1 };

    render(
        defineComponent({
            setup() {
                value = useBankFeed().value;

                return (): null => null;
            },
        }),
    );

    return value;
}

describe('useBankFeed', () => {
    it('gives what is still to sort while the link to the bank is on', () => {
        shared.bank_feed = { unsorted: 3 };

        expect(feed()).toEqual({ unsorted: 3 });
    });

    it('gives null when the link is off or the server shares nothing', () => {
        shared.bank_feed = null;
        expect(feed()).toBeNull();

        shared.bank_feed = undefined;
        expect(feed()).toBeNull();
    });
});
