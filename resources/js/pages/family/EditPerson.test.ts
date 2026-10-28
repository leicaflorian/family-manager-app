import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { personDetail, personRoles } from '@/test/fixtures';
import {
    choose,
    pickFile,
    pressSave,
    renderPage,
    settle,
    stubObjectUrls,
} from '@/test/page';
import EditPerson from './EditPerson.vue';

beforeEach(() => {
    stubObjectUrls();
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

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
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
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

        expect(post).toHaveBeenCalledWith(
            '/family/people/3',
            expect.objectContaining({
                _method: 'put',
                first_name: 'Giulia',
                role: 'child',
                birth_date: '2016-05-24',
            }),
            expect.anything(),
        );
    });

    it('saves with ⌘S too', () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPerson, { person: personDetail, roles: personRoles });

        pressSave();

        expect(post.mock.calls[0]?.[0]).toBe('/family/people/3');
    });

    it('beside the form shows the initials as they are written', async () => {
        renderPage(EditPerson, { person: null, roles: personRoles });

        await fireEvent.update(screen.getByLabelText('First name'), 'Rosa');
        await fireEvent.update(screen.getByLabelText('Last name'), 'Bianchi');
        await settle();

        expect(screen.getByText('RB')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Add a photo' }),
        ).toBeInTheDocument();
    });

    it('the photo chosen goes with the person when saving', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const photo = new File(['x'], 'giulia.jpg', { type: 'image/jpeg' });
        renderPage(EditPerson, { person: personDetail, roles: personRoles });

        await pickFile('Add a photo', photo);
        expect(
            screen.getByRole('img', { name: 'Giulia Bianchi' }),
        ).toHaveAttribute('src', 'blob:chosen');
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/family/people/3',
            expect.objectContaining({
                _method: 'put',
                photo,
                remove_photo: false,
            }),
            expect.anything(),
        );
    });

    it('removing the photo tells the server when saving', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditPerson, {
            person: { ...personDetail, photo: '/files/40' },
            roles: personRoles,
        });

        expect(
            screen.getByRole('img', { name: 'Giulia Bianchi' }),
        ).toHaveAttribute('src', '/files/40');
        await fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(screen.getByText('GB')).toBeInTheDocument();
        expect(post).toHaveBeenCalledWith(
            '/family/people/3',
            expect.objectContaining({ photo: null, remove_photo: true }),
            expect.anything(),
        );
    });
});
