import { screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { healthCard, personDetail } from '@/test/fixtures';
import { layOut, watchLayout } from '@/test/layout';
import { renderPage } from '@/test/page';
import Person from './Person.vue';

const links: App.Data.LinkGroupData[] = [
    {
        type: 'enrollment',
        label: 'School and activities',
        options: [{ value: '2', label: 'Danza Giulia', help: null }],
    },
];

/** *A person* for Giulia, with the health card expired. */
function renderGiulia(person: App.Data.PersonDetailData = personDetail): void {
    renderPage(Person, {
        person,
        expiring: [healthCard],
        personal: [healthCard],
        documents: [
            {
                ...healthCard,
                id: 19,
                title: 'Iscrizione danza 2026/27',
                expiry_date: null,
            },
        ],
        links,
    });
}

describe('A person', () => {
    it('shows the person, the health card at the top, her documents and her records', () => {
        renderGiulia();

        expect(
            screen.getByRole('heading', { name: 'Giulia Bianchi', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Child · 10 years')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Expiring' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/^Expired on 20\/10\/2026$/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Personal documents' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Iscrizione danza/ }),
        ).toHaveAttribute('href', '/documents/19');
        expect(screen.getByText('Danza Giulia')).toBeInTheDocument();
        expect(screen.getByText('BNCGLI16E64A944K')).toBeInTheDocument();
        expect(screen.getByText('24/05/2016')).toBeInTheDocument();
        expect(screen.getByText(/Giulia has no account/)).toBeInTheDocument();
    });

    it('lets an admin edit and delete a person without an account', () => {
        renderGiulia();

        expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            '/family/people/3/edit',
        );
        expect(
            screen.getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
    });

    it('a person with an account cannot be deleted, and without the permissions cannot be edited', () => {
        renderGiulia({
            ...personDetail,
            account_label: 'Family member',
            can_edit: false,
            can_delete: false,
        });

        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Delete' }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('Family member')).toBeInTheDocument();
    });

    it('puts Edit and Delete in the menu when there is no room for them', async () => {
        watchLayout();
        renderGiulia();

        await layOut(100);

        expect(
            screen.queryByRole('link', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        expect(
            await screen.findByRole('menuitem', { name: 'Edit' }),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));
        expect(
            await screen.findByRole('heading', { name: 'Delete Giulia?' }),
        ).toBeInTheDocument();
    });

    it('shows her photo beside the name', () => {
        renderGiulia({ ...personDetail, photo: '/files/40' });

        expect(
            screen.getByRole('img', { name: 'Giulia Bianchi' }),
        ).toHaveAttribute('src', '/files/40');
    });
});
