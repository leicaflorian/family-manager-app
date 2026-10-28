import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { cardTypes } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import NewCard from './NewCard.vue';

describe('New card', () => {
    it('lists the card types by area, with what they are for', () => {
        renderPage(NewCard, { types: cardTypes });

        expect(
            screen.getByRole('heading', { name: 'Home' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Vehicles' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /Utility/ })).toHaveAttribute(
            'href',
            '/cards/create?card=utility',
        );
        expect(
            screen.getByText('Electricity, gas, water, internet, waste tax'),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('Start from a document'),
        ).not.toBeInTheDocument();
    });
});
