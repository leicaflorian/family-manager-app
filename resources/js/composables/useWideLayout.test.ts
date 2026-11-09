import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { WIDE_QUERY, useWideLayout } from './useWideLayout';

type Listener = (event: { matches: boolean }) => void;

/** A `matchMedia` that can be changed from the test, like a window that is resized. */
function fakeMedia(initial: boolean) {
    const listeners = new Set<Listener>();
    const queries: string[] = [];
    const list = {
        matches: initial,
        addEventListener: (_: string, listener: Listener) =>
            listeners.add(listener),
        removeEventListener: (_: string, listener: Listener) =>
            listeners.delete(listener),
    };
    window.matchMedia = vi.fn((query: string) => {
        queries.push(query);
        return list;
    }) as unknown as typeof window.matchMedia;

    return {
        queries,
        listeners,
        resize(matches: boolean) {
            list.matches = matches;
            listeners.forEach((listener) => listener({ matches }));
        },
    };
}

/** Mounts a component that reads the layout. */
function mountWith() {
    let wide!: ReturnType<typeof useWideLayout>;
    const wrapper = mount(
        defineComponent({
            setup() {
                wide = useWideLayout();
                return () => h('div');
            },
        }),
    );

    return { wide, wrapper };
}

afterEach(() => vi.restoreAllMocks());

describe('useWideLayout', () => {
    it('asks for a wide and tall window', () => {
        const media = fakeMedia(false);
        mountWith();

        expect(WIDE_QUERY).toBe('(min-width: 900px) and (min-height: 560px)');
        expect(media.queries).toEqual([WIDE_QUERY]);
    });

    it('starts from the window and follows it', () => {
        const media = fakeMedia(true);
        const { wide } = mountWith();

        expect(wide.value).toBe(true);
        media.resize(false);
        expect(wide.value).toBe(false);
        media.resize(true);
        expect(wide.value).toBe(true);
    });

    it('stops listening when the component goes', () => {
        const media = fakeMedia(false);
        const { wide, wrapper } = mountWith();

        wrapper.unmount();
        media.resize(true);

        expect(media.listeners.size).toBe(0);
        expect(wide.value).toBe(false);
    });

    it('is false where there is no matchMedia', () => {
        // @ts-expect-error an old browser
        window.matchMedia = undefined;
        const { wide } = mountWith();

        expect(wide.value).toBe(false);
    });
});
