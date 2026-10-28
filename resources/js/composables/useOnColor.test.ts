import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h } from 'vue';
import { provideOnColor, useOnColor } from './useOnColor';

/** Shows whether it sits on a colour. */
const Reader = defineComponent({
    setup() {
        const onColor = useOnColor();

        return () => h('span', onColor ? 'on colour' : 'on white');
    },
});

describe('useOnColor', () => {
    it('is false outside a coloured background', () => {
        render(Reader);

        expect(screen.getByText('on white')).toBeInTheDocument();
    });

    it('is true inside a component that provides the colour', () => {
        render(
            defineComponent({
                setup() {
                    provideOnColor();

                    return () => h(Reader);
                },
            }),
        );

        expect(screen.getByText('on colour')).toBeInTheDocument();
    });

    it('is false again inside a white surface on top of the colour', () => {
        const White = defineComponent({
            setup() {
                provideOnColor(false);

                return () => h(Reader);
            },
        });

        render(
            defineComponent({
                setup() {
                    provideOnColor();

                    return () => h(White);
                },
            }),
        );

        expect(screen.getByText('on white')).toBeInTheDocument();
    });
});
