import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { healthCard, organizationDetail } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import Organization from './Organization.vue';

const links: App.Data.LinkGroupData[] = [
    {
        type: 'utility',
        label: 'Utilities',
        options: [{ value: '1', label: 'Luce - Edison', help: null }],
    },
];
const bill = {
    ...healthCard,
    id: 7,
    title: 'Bolletta luce di ottobre',
    expiry_date: null,
};

describe('An organization', () => {
    it('shows its contacts, its records and its documents', () => {
        renderPage(Organization, {
            organization: organizationDetail,
            links,
            documents: [bill],
        });

        expect(
            screen.getByRole('heading', { name: 'Edison', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Utility provider')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /800 123 456/ }),
        ).toHaveAttribute('href', 'tel:800123456');
        expect(
            screen.getByRole('link', { name: /edison\.it/ }),
        ).toHaveAttribute('href', 'https://edison.it');
        expect(screen.getByText('Codice cliente 40291837')).toBeInTheDocument();
        expect(screen.getByText('Luce - Edison')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Bolletta luce di ottobre/ }),
        ).toHaveAttribute('href', '/documents/7');
        expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/family/organizations/1/edit',
        );
        expect(
            screen.getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('without contacts and documents says so, and a member cannot edit it', () => {
        renderPage(Organization, {
            organization: {
                ...organizationDetail,
                phone: null,
                website: null,
                notes: null,
                can_edit: false,
                can_delete: false,
            },
            links: [],
            documents: [],
        });

        expect(screen.getByText('No contacts yet.')).toBeInTheDocument();
        expect(screen.getByText('No documents yet.')).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
    });
});
