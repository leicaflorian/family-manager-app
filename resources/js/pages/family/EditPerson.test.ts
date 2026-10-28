import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { personDetail, personRoles } from '@/test/fixtures';
import { choose, pressSave, renderPage } from '@/test/page';
import EditPerson from './EditPerson.vue';

describe('Edit a person', () => {
    it('adds a new person with what was written', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPerson, { person: null, roles: personRoles });

        expect(
            screen.getByRole('heading', { name: 'New person', level: 1 }),
        ).toBeInTheDocument();
        await fireEvent.update(screen.getByLabelText('First name'), 'Rosa');
        await fireEvent.update(screen.getByLabelText('Last name'), 'Bianchi');
        await choose('Who they are', 'Other');
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/family/people',
            expect.objectContaining({
                first_name: 'Rosa',
                last_name: 'Bianchi',
                role: 'other',
                initials: '',
            }),
            expect.anything(),
        );
    });

    it('changes a person, starting from her data', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditPerson, { person: personDetail, roles: personRoles });

        expect(
            screen.getByRole('heading', { name: 'Edit Giulia', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Tax code')).toHaveValue(
            'BNCGLI16E64A944K',
        );
        expect(await screen.findByLabelText('Date of birth')).toHaveValue(
            '24/05/2016',
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put).toHaveBeenCalledWith(
            '/family/people/3',
            expect.objectContaining({
                first_name: 'Giulia',
                role: 'child',
                birth_date: '2016-05-24',
            }),
            expect.anything(),
        );
    });

    it('saves with ⌘S too', () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditPerson, { person: personDetail, roles: personRoles });

        pressSave();

        expect(put.mock.calls[0]?.[0]).toBe('/family/people/3');
    });
});
