import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { screenWidth } from '@/test/page';
import {
    COMPUTER_QUERY,
    TABLET_QUERY,
    WIDE_COMPUTER_QUERY,
    useComputerLayout,
    useLayoutMode,
    useWideComputerLayout,
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
        listeners.get(COMPUTER_QUERY)!.forEach((l) => l({ matches: true }));
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

/** Mounts a component that reads whether it is on the computer, and on a wide one. */
function mountComputer(): { computer: boolean; wide: boolean } {
    let computer!: ReturnType<typeof useComputerLayout>;
    let wide!: ReturnType<typeof useWideComputerLayout>;
    mount(
        defineComponent({
            setup() {
                computer = useComputerLayout();
                wide = useWideComputerLayout();
                return () => h('div');
            },
        }),
    );

    return { computer: computer.value, wide: wide.value };
}

describe('useComputerLayout', () => {
    it('writes the wide computer in the same place', () => {
        expect(WIDE_COMPUTER_QUERY).toBe(
            '(min-width: 1440px) and (min-height: 560px)',
        );
    });

    it('is the computer from 1024 pixels, and wide from 1440', () => {
        screenWidth(1280);
        expect(mountComputer()).toEqual({ computer: true, wide: false });

        screenWidth(1500);
        expect(mountComputer()).toEqual({ computer: true, wide: true });
    });

    it('is not the computer on a tablet or a phone', () => {
        screenWidth(820);
        expect(mountComputer()).toEqual({ computer: false, wide: false });

        screenWidth(390);
        expect(mountComputer()).toEqual({ computer: false, wide: false });
    });

    it('is not the computer in a wide window that is too short', () => {
        screenWidth(1500, 400);
        expect(mountComputer()).toEqual({ computer: false, wide: false });
    });
});
