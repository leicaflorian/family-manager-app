import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { h } from 'vue';
import { pandaRow } from '@/test/fixtures';
import FmButton from './FmButton.vue';
import FmCardHeader from './FmCardHeader.vue';

/** The line of data of the Panda: the plate, then Marco with his address. */
const heading: App.Data.CardLinkData[] = [
    { text: 'Plate GH 482 TK', href: null },
    { text: 'Marco', href: '/people/1' },
];

describe('FmCardHeader', () => {
    it('shows the area and type, the name and the line of data', () => {
        render(FmCardHeader, {
            props: { card: pandaRow, heading },
            slots: { actions: '<button>Edit</button>' },
        });

        expect(screen.getByText('Vehicles · Vehicle')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Fiat Panda' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Plate GH 482 TK')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Edit' }),
        ).toBeInTheDocument();
    });

    it('opens in a new tab the person or the organization of the line', () => {
        render(FmCardHeader, { props: { card: pandaRow, heading } });

        const marco = screen.getByRole('link', { name: 'Marco' });
        expect(marco).toHaveAttribute('href', '/people/1');
        expect(marco).toHaveAttribute('target', '_blank');
        expect(
            screen.queryByRole('link', { name: 'Plate GH 482 TK' }),
        ).not.toBeInTheDocument();
    });

    it('makes the buttons of the actions translucent on its colour', () => {
        render(FmCardHeader, {
            props: { card: pandaRow, heading },
            slots: {
                actions: () => h(FmButton, { variant: 'soft' }, () => 'Edit'),
            },
        });

        expect(screen.getByRole('button', { name: 'Edit' })).toHaveClass(
            'bg-white/15',
            'text-white',
        );
    });
});
