import { render } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { defineComponent, nextTick, ref } from 'vue';
import { screenWidth } from '@/test/page';
import { useFilterBox } from './useFilterBox';

/**
 * Uses the composable inside a component.
 *
 * @param active - the condition given, if any
 * @returns what the composable gives
 */
function setup(active?: () => boolean) {
    let box!: ReturnType<typeof useFilterBox>;

    render(
        defineComponent({
            setup() {
                box = useFilterBox(active);

                return () => null;
            },
        }),
    );

    return box;
}

describe('useFilterBox', () => {
    afterEach(() => screenWidth(1024));

    it('is closed to start with and follows the wide layout', () => {
        screenWidth(1200);
        const box = setup();

        expect(box.wide.value).toBe(true);
        expect(box.boxOpen.value).toBe(false);
    });

    it('is not wide on a narrow window', () => {
        screenWidth(500);

        expect(setup().wide.value).toBe(false);
    });

    it('shuts the box when the condition given stops being true', async () => {
        const on = ref(true);
        const box = setup(() => on.value);

        box.boxOpen.value = true;
        on.value = false;
        await nextTick();

        expect(box.wide.value).toBe(false);
        expect(box.boxOpen.value).toBe(false);
    });
});
