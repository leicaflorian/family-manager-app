import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { cardTypes } from '@/test/fixtures';
import { renderPage, withTrail } from '@/test/page';
import NewCard from './NewCard.vue';

describe('New card', () => {
    it('writes the trail Areas > New card into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(NewCard, { types: cardTypes }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Areas', href: '/areas' },
            { label: 'New card' },
        ]);
    });

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
    });

    it('starts from a document: Upload proposes the card', () => {
        renderPage(NewCard, { types: cardTypes });

        expect(
            screen.getByRole('link', {
                name: /Have a document\? Start from it/,
            }),
        ).toHaveAttribute('href', '/upload');
        expect(
            screen.getByText(
                'Upload the bill, the policy or the booklet: we propose the card.',
            ),
        ).toBeInTheDocument();
    });

    it('opened with documents, carries them to the form of the type chosen', () => {
        renderPage(NewCard, { types: cardTypes, document: '41,42' });

        expect(screen.getByRole('link', { name: /Utility/ })).toHaveAttribute(
            'href',
            '/cards/create?card=utility&document=41%2C42',
        );
        expect(
            screen.queryByRole('link', {
                name: /Have a document\? Start from it/,
            }),
        ).not.toBeInTheDocument();
    });
});
