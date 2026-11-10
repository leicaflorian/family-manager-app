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
import { organizationDetail, organizationTypes } from '@/test/fixtures';
import {
    pickFile,
    pressSave,
    renderPage,
    withTrail,
    settle,
    stubObjectUrls,
    tapCancel,
    screenWidth,
} from '@/test/page';
import EditOrganization from './EditOrganization.vue';
import { hasChanges } from '@/lib/unsavedChanges';

beforeEach(() => {
    stubObjectUrls();
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Edit an organization', () => {
    it('writes the trail of a new and of a changed organization into the top bar', () => {
        const created = withTrail();
        const first = renderPage(
            EditOrganization,
            { organization: null, types: organizationTypes },
            created.provide,
        );

        expect(created.state.pieces.value).toEqual([
            { label: 'Family', href: '/family' },
            { label: 'Organizations', href: '/family/organizations' },
            { label: 'New organization' },
        ]);
        first.unmount();

        const changed = withTrail();
        renderPage(
            EditOrganization,
            { organization: organizationDetail, types: organizationTypes },
            changed.provide,
        );

        expect(changed.state.pieces.value).toEqual([
            { label: 'Family', href: '/family' },
            { label: 'Organizations', href: '/family/organizations' },
            { label: 'Edison', href: '/family/organizations/1' },
            { label: 'Edit Edison' },
        ]);
    });

    it('adds a new organization with its types', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditOrganization, {
            organization: null,
            types: organizationTypes,
        });

        expect(
            screen.getByRole('heading', { name: 'New organization', level: 1 }),
        ).toBeInTheDocument();
        await fireEvent.update(screen.getByLabelText('Name'), 'Coop Alleanza');
        await fireEvent.click(
            screen.getByRole('button', { name: 'Shop or service' }),
        );
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/family/organizations',
            expect.objectContaining({ name: 'Coop Alleanza', types: ['shop'] }),
            expect.anything(),
        );
    });

    it('changes an organization, starting from its data', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditOrganization, {
            organization: organizationDetail,
            types: organizationTypes,
        });

        expect(
            screen.getByRole('heading', { name: 'Edit Edison', level: 1 }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Utility provider' }),
        ).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.getByRole('button', { name: 'Bank or lender' }),
        ).toHaveAttribute('aria-pressed', 'false');
        expect(screen.getByLabelText('Phone')).toHaveValue('800 123 456');
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/family/organizations/1',
            expect.objectContaining({
                _method: 'put',
                name: 'Edison',
                types: ['utility_provider'],
                website: 'edison.it',
            }),
            expect.anything(),
        );
    });

    it('saves with ⌘S too', () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditOrganization, {
            organization: organizationDetail,
            types: organizationTypes,
        });

        pressSave();

        expect(post.mock.calls[0]?.[0]).toBe('/family/organizations/1');
    });

    it('the logo chosen goes with the organization when saving', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const logo = new File(['x'], 'edison.png', { type: 'image/png' });
        renderPage(EditOrganization, {
            organization: organizationDetail,
            types: organizationTypes,
        });

        expect(screen.queryByRole('img', { name: 'Edison' })).toBeNull();
        await pickFile('Add a logo', logo);
        expect(screen.getByRole('img', { name: 'Edison' })).toHaveAttribute(
            'src',
            'blob:chosen',
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/family/organizations/1',
            expect.objectContaining({
                _method: 'put',
                logo,
                remove_logo: false,
            }),
            expect.anything(),
        );
    });

    it('removing the logo tells the server when saving', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(EditOrganization, {
            organization: { ...organizationDetail, logo: '/files/41' },
            types: organizationTypes,
        });

        expect(screen.getByRole('img', { name: 'Edison' })).toHaveAttribute(
            'src',
            '/files/41',
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/family/organizations/1',
            expect.objectContaining({ logo: null, remove_logo: true }),
            expect.anything(),
        );
    });
});

describe('Changes not saved', () => {
    it('has them once a field changes, and gives them up with Cancel', async () => {
        renderPage(EditOrganization, {
            organization: null,
            types: organizationTypes,
        });
        expect(hasChanges()).toBe(false);

        await fireEvent.update(screen.getByLabelText('Name'), 'Coop Alleanza');
        await settle();
        expect(hasChanges()).toBe(true);

        await tapCancel();
        expect(hasChanges()).toBe(false);
    });
});

describe('EditOrganization on the computer', () => {
    it('has no back link and no close button above the title on the computer, and both on the tablet and the phone', () => {
        for (const [width, shown] of [
            [1280, false],
            [820, true],
            [390, true],
        ] as const) {
            screenWidth(width);
            const view = renderPage(EditOrganization, {
                organization: null,
                types: organizationTypes,
            });

            expect(screen.queryByRole('link', { name: 'Close' }) !== null).toBe(
                shown,
            );
            expect(
                screen.queryByRole('link', { name: 'Organizations' }) !== null,
            ).toBe(shown);
            view.unmount();
        }
    });
});
