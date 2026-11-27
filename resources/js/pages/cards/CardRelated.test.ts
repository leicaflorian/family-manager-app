import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { pandaRow } from '@/test/fixtures';
import { renderPage, withTrail } from '@/test/page';
import CardRelated from './CardRelated.vue';

const policyRow: App.Data.CardData = {
    ...pandaRow,
    type: 'insurance_policy',
    id: 4,
    type_label: 'Insurance policy',
    label: 'Assicurazione Auto - Fiat Panda',
    area: 'insurance',
    area_label: 'Insurance',
    next_label: 'Expiry',
    next_date: '2027-09-20',
};

const closedRow: App.Data.CardData = {
    ...policyRow,
    id: 5,
    label: 'Vecchia polizza',
    next_date: '2026-09-20',
    closed: true,
};

const props = {
    card: pandaRow,
    related: [policyRow, closedRow],
    addable: [
        {
            type: 'maintenance_record',
            label: 'Maintenance job',
            href: '/cards/create?card=maintenance_record&target=vehicle&vehicle_id=1',
        },
    ],
    back: '/cards/vehicle/1',
};

describe('Linked cards of a card', () => {
    it('writes the trail Areas > area > card > links into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(CardRelated, props, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Areas', href: '/areas' },
            { label: 'Vehicles', href: '/areas/vehicles' },
            { label: 'Fiat Panda', href: '/cards/vehicle/1' },
            { label: 'Links of Fiat Panda' },
        ]);
    });

    it('lists every linked card, in the order the server sends, with Add', () => {
        renderPage(CardRelated, props);

        expect(
            screen.getByRole('heading', {
                name: 'Links of Fiat Panda',
                level: 1,
            }),
        ).toBeInTheDocument();
        const links = screen
            .getAllByRole('link')
            .filter((link) => link.getAttribute('href')?.startsWith('/cards/'));
        expect(links.map((link) => link.textContent)).toEqual([
            expect.stringContaining('Assicurazione Auto - Fiat Panda'),
            expect.stringContaining('Vecchia polizza'),
        ]);
        expect(screen.getByRole('button', { name: 'Add' })).toBeInTheDocument();
    });

    it('says in which order the cards are', () => {
        renderPage(CardRelated, props);

        expect(
            screen.getByText(/the closed ones .* are at the bottom/i),
        ).toBeInTheDocument();
    });
});
