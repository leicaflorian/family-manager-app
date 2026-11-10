import { render } from '@testing-library/vue';
import { defineComponent, h, nextTick, provide, reactive, ref } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import type { TrailState } from '@/lib/pageTrail';
import { TRAIL_KEY, createTrailState } from '@/lib/pageTrail';
import { usePageTrail } from './usePageTrail';

/** Mounts a child that uses `usePageTrail` under a parent that provides `state`. */
function mountTrail(
    state: TrailState | null,
    name = ref('Rossi'),
    show = ref(true),
) {
    const Child = defineComponent({
        setup() {
            usePageTrail(() => [
                { label: 'Areas', href: '/areas' },
                { label: name.value },
            ]);

            return () => h('div');
        },
    });
    const Parent = defineComponent({
        setup() {
            if (state) {
                provide(TRAIL_KEY, state);
            }

            return () => (show.value ? h(Child) : h('span'));
        },
    });

    return { ...render(Parent), name, show };
}

describe('usePageTrail', () => {
    it('writes the pieces right away', () => {
        const state = createTrailState();
        mountTrail(state);

        expect(state.pieces.value).toEqual([
            { label: 'Areas', href: '/areas' },
            { label: 'Rossi' },
        ]);
    });

    it('follows changes of the last piece', async () => {
        const state = createTrailState();
        const { name } = mountTrail(state);

        name.value = 'Bianchi';
        await nextTick();

        expect(state.pieces.value[1]).toEqual({ label: 'Bianchi' });
    });

    it('clears the pieces when the page unmounts', async () => {
        const state = createTrailState();
        const { show } = mountTrail(state);

        show.value = false;
        await nextTick();

        expect(state.pieces.value).toEqual([]);
    });

    it('keeps the pieces another page wrote before unmount', async () => {
        const state = createTrailState();
        const { show } = mountTrail(state);

        state.set([{ label: 'Next page' }]);
        show.value = false;
        await nextTick();

        expect(state.pieces.value).toEqual([{ label: 'Next page' }]);
    });

    it('clears the pieces of a page that returns a reactive array', async () => {
        const state = createTrailState();
        const pieces = reactive([{ label: 'Areas', href: '/areas' }]);
        const show = ref(true);
        const Child = defineComponent({
            setup() {
                usePageTrail(() => pieces);

                return () => h('div');
            },
        });
        const Parent = defineComponent({
            setup() {
                provide(TRAIL_KEY, state);

                return () => (show.value ? h(Child) : h('span'));
            },
        });
        render(Parent);

        show.value = false;
        await nextTick();

        expect(state.pieces.value).toEqual([]);
    });

    it('does nothing without a layout', () => {
        expect(() => mountTrail(null)).not.toThrow();
    });
});
