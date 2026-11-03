import { render } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent } from 'vue';
import { useToSortCount } from './useToSortCount';

const shared = vi.hoisted(() => ({ to_sort_count: 0 as number | undefined }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { ...shared } }),
}));

/** Mounts a component that reads the count and gives it back. */
function count(): number {
    let value = -1;

    render(
        defineComponent({
            setup() {
                value = useToSortCount().value;

                return (): null => null;
            },
        }),
    );

    return value;
}

describe('useToSortCount', () => {
    it('gives the number of files to sort the server shares', () => {
        shared.to_sort_count = 4;

        expect(count()).toBe(4);
    });

    it('gives zero when the server shares nothing', () => {
        shared.to_sort_count = undefined;

        expect(count()).toBe(0);
    });
});
