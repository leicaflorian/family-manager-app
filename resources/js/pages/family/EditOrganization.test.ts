import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { organizationDetail, organizationTypes } from '@/test/fixtures';
import { pressSave, renderPage, settle } from '@/test/page';
import EditOrganization from './EditOrganization.vue';

describe('Edit an organization', () => {
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
        await fireEvent.click(screen.getByLabelText('Shop or service'));
        await settle();
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(post).toHaveBeenCalledWith(
            '/family/organizations',
            expect.objectContaining({ name: 'Coop Alleanza', types: ['shop'] }),
            expect.anything(),
        );
    });

    it('changes an organization, starting from its data', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditOrganization, {
            organization: organizationDetail,
            types: organizationTypes,
        });

        expect(
            screen.getByRole('heading', { name: 'Edit Edison', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Utility provider')).toBeChecked();
        expect(screen.getByLabelText('Phone')).toHaveValue('800 123 456');
        await fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put).toHaveBeenCalledWith(
            '/family/organizations/1',
            expect.objectContaining({
                name: 'Edison',
                types: ['utility_provider'],
                website: 'edison.it',
            }),
            expect.anything(),
        );
    });

    it('saves with ⌘S too', () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(EditOrganization, {
            organization: organizationDetail,
            types: organizationTypes,
        });

        pressSave();

        expect(put).toHaveBeenCalled();
    });
});
