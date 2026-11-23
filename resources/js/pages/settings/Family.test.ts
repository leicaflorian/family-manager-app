import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle, withTrail } from '@/test/page';
import Family from './Family.vue';

const settings: App.Data.FamilyNameSettingsData = {
    name: 'Bianchi',
    can_edit: true,
};

describe('Settings, Family name', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('writes the trail Settings > Family name into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(Family, { settings }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'Family name' },
        ]);
    });

    it('shows the name to change and says the address for the bills stays', () => {
        renderPage(Family, { settings });

        expect(
            screen.getByRole('heading', { name: 'Family name' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Family name')).toHaveValue('Bianchi');
        expect(
            screen.getByText('The address for the bills stays the same'),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Save' })).toHaveAttribute(
            'type',
            'submit',
        );
    });

    it('saves the new name', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Family, { settings });

        const field = screen.getByLabelText('Family name');
        await userEvent.clear(field);
        await userEvent.type(field, 'Bianchi Rossi');
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(put).toHaveBeenCalledWith(
            '/settings/family',
            { name: 'Bianchi Rossi' },
            expect.anything(),
        );
    });

    it('shows the error of the server under the field', async () => {
        vi.spyOn(router, 'put').mockImplementation((_url, _data, options) => {
            options?.onError?.({ name: 'The family name field is required.' });
        });
        renderPage(Family, { settings });

        await userEvent.clear(screen.getByLabelText('Family name'));
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        await settle();

        expect(
            screen.getByText('The family name field is required.'),
        ).toBeInTheDocument();
    });

    it('lets a member see the name without changing it', () => {
        renderPage(Family, { settings: { ...settings, can_edit: false } });

        expect(screen.getByLabelText('Family name')).toHaveAttribute(
            'readonly',
        );
        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
    });
});
