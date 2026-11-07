import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { withSlots } from './slots';

/** A component that draws its `default` slot and a slot called `aside`. */
const Box = defineComponent({
    props: { title: { type: String, default: '' } },
    setup(props, { slots }) {
        return () =>
            h('section', [
                h('h2', props.title),
                h('div', slots.default?.()),
                h('aside', slots.aside?.()),
            ]);
    },
});

describe('withSlots', () => {
    it('draws a slot given as a text and one given as a function', () => {
        const story = withSlots({
            default: 'Hello',
            aside: () => h('b', 'World'),
        });

        render(story({ title: 'Box' }, { component: Box }));

        expect(
            screen.getByRole('heading', { name: 'Box' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Hello')).toBeInTheDocument();
        expect(screen.getByText('World').tagName).toBe('B');
    });
});
