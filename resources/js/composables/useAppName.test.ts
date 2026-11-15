import { render } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent } from 'vue';
import { useAppName } from './useAppName';

const shared = vi.hoisted(() => ({ name: 'Fameja.app' }));

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    usePage: () => ({ props: { ...shared } }),
}));

/** Mounts a component that reads the name and gives it back. */
function appName(): string {
    let value = '';

    render(
        defineComponent({
            setup() {
                value = useAppName().value;

                return (): null => null;
            },
        }),
    );

    return value;
}

describe('useAppName', () => {
    it('gives the name of the app that the server shares', () => {
        expect(appName()).toBe('Fameja.app');
    });
});
