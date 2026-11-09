import { render } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h, ref, shallowRef, type Ref } from 'vue';
import { type SearchMenuControls, useSearchBox } from './useSearchBox';

/** The first results for «Pan», as the suggest route sends them. */
const pan: App.Data.Search.SearchResultsData = {
    query: 'Pan',
    type: null,
    total: 1,
    groups: [
        {
            group: 'cards',
            label: 'Cards',
            count: 1,
            results: [
                {
                    key: 'card-1',
                    group: 'cards',
                    title: 'Fiat Panda',
                    subtitle: null,
                    date: null,
                    amount: null,
                    href: '/card-1',
                    area: 'vehicles',
                    icon: null,
                    tone: null,
                    flag: null,
                    due_in: null,
                },
            ],
        },
    ],
};

/**
 * Renders a page that uses the box with a fake menu.
 *
 * @param opened - what `open()` of the menu answers
 * @returns the text, the menu, what submit does and the composable's state
 */
function renderBox(opened = false): {
    text: Ref<string>;
    menu: SearchMenuControls;
    submit: () => void;
    state: ReturnType<typeof useSearchBox>;
} {
    const text = ref('');
    const menu: SearchMenuControls = {
        move: vi.fn(),
        open: vi.fn(() => opened),
    };
    const submit = vi.fn();
    let state!: ReturnType<typeof useSearchBox>;
    const Page = defineComponent({
        setup() {
            state = useSearchBox(text, shallowRef(menu), submit);

            return () => h('div');
        },
    });
    render(Page);

    return { text, menu, submit, state };
}

/**
 * A key pressed in the box.
 *
 * @param key - the key
 * @returns the event
 */
function key(key: string): KeyboardEvent {
    return new KeyboardEvent('keydown', { key, cancelable: true });
}

describe('useSearchBox', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({
                    ok: true,
                    json: () => Promise.resolve(pan),
                }),
            ),
        );
    });
    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('opens the menu with the first results while the person types', async () => {
        const { text, state } = renderBox();

        expect(state.menuResults.value).toBeNull();
        text.value = 'Pan';
        await vi.advanceTimersByTimeAsync(300);

        expect(state.menuResults.value).toEqual(pan);
    });

    it('closes the menu with Esc until the text changes again', async () => {
        const { text, state } = renderBox();
        text.value = 'Pan';
        await vi.advanceTimersByTimeAsync(300);

        state.closeMenu();
        expect(state.menuResults.value).toBeNull();

        text.value = 'Pand';
        await vi.advanceTimersByTimeAsync(300);
        expect(state.menuResults.value).toEqual(pan);
    });

    it('moves the choice with the arrows only while the menu is open', async () => {
        const { text, menu, state } = renderBox();

        state.onKeydown(key('ArrowDown'));
        expect(menu.move).not.toHaveBeenCalled();

        text.value = 'Pan';
        await vi.advanceTimersByTimeAsync(300);
        const down = key('ArrowDown');
        state.onKeydown(down);
        state.onKeydown(key('ArrowUp'));
        state.onKeydown(key('a'));

        expect(menu.move).toHaveBeenNthCalledWith(1, 1);
        expect(menu.move).toHaveBeenNthCalledWith(2, -1);
        expect(menu.move).toHaveBeenCalledTimes(2);
        expect(down.defaultPrevented).toBe(true);
    });

    it('Enter opens the chosen result, and searches when none is chosen', async () => {
        const chosen = renderBox(true);
        chosen.text.value = 'Pan';
        await vi.advanceTimersByTimeAsync(300);
        chosen.state.enter();

        expect(chosen.menu.open).toHaveBeenCalledOnce();
        expect(chosen.submit).not.toHaveBeenCalled();

        const none = renderBox(false);
        none.text.value = 'Pan';
        await vi.advanceTimersByTimeAsync(300);
        none.state.enter();

        expect(none.submit).toHaveBeenCalledOnce();
    });

    it('closes the menu when the focus leaves the box and its menu, not when it moves inside', async () => {
        const { text, state } = renderBox();
        text.value = 'Pan';
        await vi.advanceTimersByTimeAsync(300);
        const box = document.createElement('div');
        const input = document.createElement('input');
        const option = document.createElement('a');
        box.append(input, option);
        document.body.append(box);
        box.addEventListener('focusout', state.onFocusOut);

        input.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: option,
            }),
        );
        expect(state.menuResults.value).toEqual(pan);

        input.dispatchEvent(
            new FocusEvent('focusout', {
                bubbles: true,
                relatedTarget: document.body,
            }),
        );
        expect(state.menuResults.value).toBeNull();
        box.remove();
    });

    it('Enter searches without asking the menu when it is closed', () => {
        const { menu, submit, state } = renderBox(true);

        state.enter();

        expect(menu.open).not.toHaveBeenCalled();
        expect(submit).toHaveBeenCalledOnce();
    });
});
