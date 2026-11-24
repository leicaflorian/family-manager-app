import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h, provide, ref } from 'vue';
import { PAGE_INFO } from '@/lib/titles';
import { pandaRow } from '@/test/fixtures';
import FmButton from './FmButton.vue';
import FmCardHeader from './FmCardHeader.vue';

describe('FmCardHeader', () => {
    it('shows the area and type, the name and the line of data', () => {
        render(FmCardHeader, {
            props: { card: pandaRow, heading: 'Plate GH 482 TK · Marco' },
            slots: { actions: '<button>Edit</button>' },
        });

        expect(screen.getByText('Vehicles · Vehicle')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Fiat Panda' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Plate GH 482 TK · Marco')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Edit' }),
        ).toBeInTheDocument();
    });

    it('fades from the dark colour of the area to a lighter one', () => {
        const { container } = render(FmCardHeader, {
            props: { card: pandaRow, heading: 'Plate GH 482 TK · Marco' },
        });

        expect(container.firstElementChild).toHaveClass(
            'bg-linear-160',
            'from-vehicles-dark',
            'to-vehicles-dark-end',
        );
    });

    it('has the title at 32 px only on the computer and no lg: class', () => {
        const { container } = render(FmCardHeader, {
            props: { card: pandaRow, heading: 'Plate GH 482 TK · Marco' },
        });

        const heading = screen.getByRole('heading', { level: 1 });
        expect(heading).toHaveClass('text-title-mobile', 'computer:text-title');
        expect(container.innerHTML).not.toContain('lg:');
        expect(container.firstElementChild).toHaveClass('p-5', 'computer:p-7');
    });

    it('fades in grey when the card has no area', () => {
        const { container } = render(FmCardHeader, {
            props: {
                card: { ...pandaRow, area: 'violet' },
                heading: 'Plate GH 482 TK · Marco',
            },
        });

        expect(container.firstElementChild).toHaveClass(
            'bg-linear-160',
            'from-general-dark',
            'to-general-dark-end',
        );
    });

    it('makes the buttons of the actions translucent on its colour', () => {
        render(FmCardHeader, {
            props: { card: pandaRow, heading: 'Plate GH 482 TK · Marco' },
            slots: {
                actions: () => h(FmButton, { variant: 'soft' }, () => 'Edit'),
            },
        });

        expect(screen.getByRole('button', { name: 'Edit' })).toHaveClass(
            'bg-on-hero-soft',
            'text-on-hero',
        );
    });
    it('has the «i» of the page beside the name when the page gives its key', () => {
        const Page = defineComponent({
            setup() {
                provide(PAGE_INFO, ref('card' as const));

                return () =>
                    h(FmCardHeader, {
                        card: pandaRow,
                        heading: 'Plate GH 482 TK · Marco',
                    });
            },
        });
        render(Page);

        expect(
            screen.getByRole('button', { name: 'What is this page' }),
        ).toBeInTheDocument();
    });
});
