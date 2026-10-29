import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { allAreas, cardTypes, homeArea, luceRow } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Area from './Area.vue';

/** The types of *Home* the cards of `homeArea` belong to. */
const homeTypes: App.Data.CardTypeData[] = [
    ...cardTypes.slice(0, 2),
    {
        type: 'belonging',
        label: 'Item under warranty',
        plural_label: 'Items under warranty',
        description: 'Appliances, electronics, furniture',
        area: 'home',
        area_label: 'Home',
    },
];

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

    it('groups the open cards by type when the area has more types, without the empty ones', () => {
        renderPage(Area, {
            area: homeArea,
            types: homeTypes,
            canCreate: false,
        });

        expect(
            screen
                .getAllByRole('heading', { level: 2 })
                .map((heading) =>
                    heading.textContent?.replace(/\s+/g, ' ').trim(),
                ),
        ).toEqual(['Residences 1', 'Items under warranty 1']);
        expect(
            within(
                screen.getByRole('region', { name: /Residences/ }),
            ).getByRole('link', { name: /Casa di via Roma/ }),
        ).toBeInTheDocument();
        expect(
            within(
                screen.getByRole('region', { name: /Items under warranty/ }),
            ).getByRole('link', { name: /TV Samsung/ }),
        ).toBeInTheDocument();
    });

    it('keeps the closed cards together in one fold, whatever their type', async () => {
        renderPage(Area, {
            area: {
                ...homeArea,
                cards: [
                    ...homeArea.cards,
                    {
                        ...homeArea.cards[1]!,
                        id: 3,
                        label: 'Lavatrice',
                        closed: true,
                    },
                ],
            },
            types: homeTypes,
            canCreate: false,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Closed (2)' }),
        );

        expect(
            screen.getByRole('link', { name: /Luce - Enel/ }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Lavatrice/ }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('heading', { level: 2 })).toHaveLength(2);
    });

    it('does not group the cards of an area with one type', () => {
        renderPage(Area, {
            area: allAreas[1]!,
            types: [cardTypes[2]!],
            canCreate: false,
        });

        expect(
            screen.getByRole('link', { name: /Fiat Panda/ }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('heading', { level: 2 }),
        ).not.toBeInTheDocument();
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

    it('shows the amount due beside a card, red when late', () => {
        renderPage(Area, {
            area: {
                ...homeArea,
                cards: [
                    { ...homeArea.cards[0] },
                    { ...luceRow, due_amount: 71.3, due_late: true },
                ],
            },
            types: cardTypes.slice(0, 2),
            canCreate: false,
        });

        const luce = screen.getByRole('link', { name: /Luce - Edison/ });
        expect(within(luce).getByText('71,30 €')).toHaveClass('text-overdue');
        expect(
            within(
                screen.getByRole('link', { name: /Casa di via Roma/ }),
            ).queryByText(/€/),
        ).not.toBeInTheDocument();
    });
});
