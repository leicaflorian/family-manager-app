import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { familySummary, peopleList } from '@/test/fixtures';
import { layOut, watchLayout } from '@/test/layout';
import { renderPage } from '@/test/page';
import People from './People.vue';

describe('People', () => {
    it('lists the people with who they are and their account', () => {
        renderPage(People, {
            people: peopleList,
            family: familySummary,
            canEdit: true,
        });

        expect(screen.getByRole('link', { name: 'People 3' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        const giulia = screen.getAllByRole('link', { name: /Giulia Bianchi/ });
        expect(giulia[0]).toHaveAttribute('href', '/family/people/3');
        expect(giulia[0]).toHaveTextContent('Child · 10 years · No account');
        expect(
            screen.getAllByRole('link', { name: /Marco Bianchi/ })[0],
        ).toHaveTextContent('Administrator · you');
    });

    it('lets an admin add a person and invite a family member', () => {
        renderPage(People, {
            people: peopleList,
            family: familySummary,
            canEdit: true,
        });

        expect(
            screen.getByRole('link', { name: 'New person' }),
        ).toHaveAttribute('href', '/family/people/create');
        expect(
            screen.getAllByRole('link', { name: /Invite a family member/ })[0],
        ).toHaveAttribute('href', '/family/invite');
    });

    it('without the permissions offers neither', () => {
        renderPage(People, {
            people: peopleList,
            family: familySummary,
            canEdit: false,
        });

        expect(
            screen.queryByRole('link', { name: 'New person' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Invite a family member/ }),
        ).not.toBeInTheDocument();
    });

    it('on the computer shows the cards with documents and records', () => {
        renderPage(People, {
            people: peopleList,
            family: familySummary,
            canEdit: true,
        });

        const card = screen.getAllByRole('link', { name: /Giulia Bianchi/ })[1];
        expect(within(card).getByText('4')).toBeInTheDocument();
        expect(within(card).getByText('2')).toBeInTheDocument();
    });

    it('puts the actions in the menu when there is no room for them', async () => {
        watchLayout();
        renderPage(People, {
            people: peopleList,
            family: familySummary,
            canEdit: true,
        });

        await layOut(100);

        expect(
            screen.queryByRole('link', { name: 'New person' }),
        ).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        expect(
            await screen.findByRole('menuitem', { name: 'New person' }),
        ).toHaveAttribute('href', '/family/people/create');
        expect(
            screen.getByRole('menuitem', { name: 'Invite a family member' }),
        ).toHaveAttribute('href', '/family/invite');
    });

    it('shows the photo of whoever has one, the initials for the others', () => {
        renderPage(People, {
            people: peopleList.map((person) =>
                person.id === 3 ? { ...person, photo: '/files/40' } : person,
            ),
            family: familySummary,
            canEdit: true,
        });

        for (const photo of screen.getAllByRole('img', {
            name: 'Giulia Bianchi',
        })) {
            expect(photo).toHaveAttribute('src', '/files/40');
        }
        expect(
            screen.queryAllByRole('img', { name: 'Marco Bianchi' }),
        ).toHaveLength(0);
    });
});
