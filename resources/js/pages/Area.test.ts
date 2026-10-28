import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { allAreas, cardTypes, homeArea } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Area from './Area.vue';

describe('Area', () => {
    it('lists the open cards by name and keeps the closed ones folded', async () => {
        renderPage(Area, {
            area: homeArea,
            types: cardTypes.slice(0, 2),
            canCreate: true,
        });

        expect(
            screen.getByRole('heading', { name: 'Home', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('2 cards · 1 overdue')).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Luce - Enel/ }),
        ).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: /Closed/ }));

        expect(
            screen.getByRole('link', { name: /Luce - Enel/ }),
        ).toBeInTheDocument();
    });

    it('opens New card with the types of the area, or the form when it has one type', () => {
        const first = renderPage(Area, {
            area: homeArea,
            types: cardTypes.slice(0, 2),
            canCreate: true,
        });
        expect(screen.getByRole('link', { name: 'New' })).toHaveAttribute(
            'href',
            '/cards/create?area=home',
        );
        first.unmount();

        renderPage(Area, {
            area: allAreas[1],
            types: [cardTypes[2]],
            canCreate: true,
        });
        expect(screen.getByRole('link', { name: 'New' })).toHaveAttribute(
            'href',
            '/cards/create?card=vehicle',
        );
    });

    it('does not offer New to whoever cannot create', () => {
        renderPage(Area, {
            area: homeArea,
            types: cardTypes.slice(0, 2),
            canCreate: false,
        });

        expect(
            screen.queryByRole('link', { name: 'New' }),
        ).not.toBeInTheDocument();
    });

    it('says when the area has no cards', () => {
        renderPage(Area, {
            area: { ...allAreas[2]!, label: 'Insurance' },
            types: [],
            canCreate: false,
        });

        expect(
            screen.getByText('No cards in this area yet'),
        ).toBeInTheDocument();
    });
});
