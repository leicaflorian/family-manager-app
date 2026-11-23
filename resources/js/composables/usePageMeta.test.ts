import { render } from '@testing-library/vue';
import { defineComponent, h, inject, nextTick, provide, ref } from 'vue';
import { describe, expect, it } from 'vite-plus/test';
import { TRAIL_KEY, createTrailState } from '@/lib/pageTrail';
import { PAGE_INFO } from '@/lib/titles';
import type { PageMetaOptions } from './usePageMeta';
import { usePageMeta } from './usePageMeta';

/** Mounts a page that uses `usePageMeta` under a layout that provides the trail; returns what the page got. */
function mountMeta(options: PageMetaOptions, withTrail = true) {
    const state = createTrailState();
    let meta!: ReturnType<typeof usePageMeta>;
    let info: string | undefined;
    // Stands for the FmTitle of the page: a child that reads what the page provides
    const Title = defineComponent({
        setup() {
            info = inject(PAGE_INFO, ref('none')).value;

            return () => h('div');
        },
    });
    const Inner = defineComponent({
        setup() {
            meta = usePageMeta(options);

            return () => h(Title);
        },
    });
    const Parent = defineComponent({
        setup() {
            if (withTrail) {
                provide(TRAIL_KEY, state);
            }

            return () => h(Inner);
        },
    });

    render(Parent);

    return { state, meta, info: () => info };
}

describe('usePageMeta', () => {
    it('gives the title as the head and as the last piece of the trail', () => {
        const { state, meta } = mountMeta({
            title: () => 'Rossi',
            parent: { label: 'Areas', href: '/areas' },
        });

        expect(meta.title.value).toBe('Rossi');
        expect(meta.head.value).toBe('Rossi');
        expect(state.pieces.value).toEqual([
            { label: 'Areas', href: '/areas' },
            { label: 'Rossi' },
        ]);
    });

    it('takes label and address of a section from the registry', () => {
        const { meta } = mountMeta({ title: 'Calendar', parent: 'settings' });

        expect(meta.trail.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'Calendar' },
        ]);
    });

    it('accepts several parents, sections and pieces mixed', () => {
        const { meta } = mountMeta({
            title: 'Usage',
            parent: ['budget', { label: 'Recurring', href: '/r' }],
        });

        expect(meta.trail.value.map((piece) => piece.label)).toEqual([
            'Budget',
            'Recurring',
            'Usage',
        ]);
    });

    it('writes no trail without a parent (a page of a section)', () => {
        const { state, meta } = mountMeta({ title: 'Areas' });

        expect(meta.trail.value).toEqual([]);
        expect(state.pieces.value).toEqual([]);
    });

    it('lets the head differ from the title', () => {
        const { meta } = mountMeta({ title: 'Hi, Anna', head: 'Home' });

        expect(meta.title.value).toBe('Hi, Anna');
        expect(meta.head.value).toBe('Home');
    });

    it('follows a title that changes', async () => {
        const name = ref('Rossi');
        const { state } = mountMeta({ title: name, parent: 'family' });

        name.value = 'Bianchi';
        await nextTick();

        expect(state.pieces.value[1]).toEqual({ label: 'Bianchi' });
    });

    it('gives the info key to the title', () => {
        expect(mountMeta({ title: 'Banks', info: 'banks' }).info()).toBe(
            'banks',
        );
    });

    it('works without a layout', () => {
        const { meta } = mountMeta({ title: 'A', parent: 'home' }, false);

        expect(meta.title.value).toBe('A');
    });
});
