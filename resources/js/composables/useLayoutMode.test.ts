import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { screenWidth } from '@/test/page';
import {
    COMPUTER_QUERY,
    TABLET_QUERY,
    useLayoutMode,
} from './useLayoutMode';

type Listener = (event: { matches: boolean }) => void;

/** Mounts a component that reads the layout mode. */
function mountWith() {
    let mode!: ReturnType<typeof useLayoutMode>;
    const wrapper = mount(
        defineComponent({
            setup() {
                mode = useLayoutMode();
                return () => h('div');
            },
        }),
    );

    return { mode, wrapper };
}

afterEach(() => vi.unstubAllGlobals());

describe('useLayoutMode', () => {
    it('writes the two conditions in one place', () => {
        expect(TABLET_QUERY).toBe('(min-width: 768px) and (min-height: 560px)');
        expect(COMPUTER_QUERY).toBe(
            '(min-width: 1024px) and (min-height: 560px)',
        );
    });

    it('is phone on a narrow screen', () => {
        screenWidth(390);
        expect(mountWith().mode.value).toBe('phone');
    });

    it('is tablet between 768 and 1023 pixels', () => {
        screenWidth(820);
        expect(mountWith().mode.value).toBe('tablet');
    });

    it('is computer from 1024 pixels', () => {
        screenWidth(1280);
        expect(mountWith().mode.value).toBe('computer');
    });

    it('checks the computer first', () => {
        screenWidth(true);
        expect(mountWith().mode.value).toBe('computer');
    });

    it('is phone when the screen is wide but too short', () => {
        vi.stubGlobal('matchMedia', (query: string) => ({
            matches: !query.includes('min-height'),
            addEventListener: () => {},
            removeEventListener: () => {},
        }));
        expect(mountWith().mode.value).toBe('phone');
    });

    it('follows the window', () => {
        const listeners = new Map<string, Set<Listener>>();
        const state: Record<string, boolean> = {
            [TABLET_QUERY]: true,
            [COMPUTER_QUERY]: false,
        };
        vi.stubGlobal('matchMedia', (query: string) => ({
            get matches() {
                return state[query];
            },
            addEventListener: (_: string, l: Listener) => {
                if (!listeners.has(query)) listeners.set(query, new Set());
                listeners.get(query)!.add(l);
            },
            removeEventListener: (_: string, l: Listener) =>
                listeners.get(query)?.delete(l),
        }));
        const { mode, wrapper } = mountWith();

        expect(mode.value).toBe('tablet');
        state[COMPUTER_QUERY] = true;
        listeners
            .get(COMPUTER_QUERY)!
            .forEach((l) => l({ matches: true }));
        expect(mode.value).toBe('computer');

        wrapper.unmount();
        expect(listeners.get(COMPUTER_QUERY)!.size).toBe(0);
        expect(listeners.get(TABLET_QUERY)!.size).toBe(0);
    });

    it('is phone where there is no matchMedia', () => {
        vi.stubGlobal('matchMedia', undefined);
        expect(mountWith().mode.value).toBe('phone');
    });
});
