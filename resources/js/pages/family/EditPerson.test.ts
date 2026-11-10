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
    withTrail,
    settle,
    stubObjectUrls,
    tapCancel,
    screenWidth,
} from '@/test/page';
import EditPerson from './EditPerson.vue';
import { hasChanges } from '@/lib/unsavedChanges';

beforeEach(() => {
    stubObjectUrls();
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Edit a person', () => {
    it('writes the trail of a new and of a changed person into the top bar', () => {
        const created = withTrail();
        const first = renderPage(
            EditPerson,
            { person: null, roles: personRoles },
            created.provide,
        );

        expect(created.state.pieces.value).toEqual([
            { label: 'Family', href: '/family' },
            { label: 'New person' },
        ]);
        first.unmount();

        const changed = withTrail();
        renderPage(
            EditPerson,
            { person: personDetail, roles: personRoles },
            changed.provide,
        );

        expect(changed.state.pieces.value).toEqual([
            { label: 'Family', href: '/family' },
            { label: 'Giulia Bianchi', href: '/family/people/3' },
            { label: 'Edit Giulia' },
        ]);
    });

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

describe('Changes not saved', () => {
    it('has them once a field changes, and gives them up with Cancel', async () => {
        renderPage(EditPerson, { person: null, roles: personRoles });
        expect(hasChanges()).toBe(false);

        await fireEvent.update(screen.getByLabelText('First name'), 'Rosa');
        await settle();
        expect(hasChanges()).toBe(true);

        await tapCancel();
        expect(hasChanges()).toBe(false);
    });
});

describe('EditPerson on the computer', () => {
    it('has no back link and no close button above the title on the computer, and both on the tablet and the phone', () => {
        for (const [width, shown] of [
            [1280, false],
            [820, true],
            [390, true],
        ] as const) {
            screenWidth(width);
            const view = renderPage(EditPerson, {
                person: null,
                roles: personRoles,
            });

            expect(screen.queryByRole('link', { name: 'Close' }) !== null).toBe(
                shown,
            );
            expect(
                screen.queryByRole('link', { name: 'People' }) !== null,
            ).toBe(shown);
            view.unmount();
        }
    });
});
