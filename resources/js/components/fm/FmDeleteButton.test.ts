import { router } from '@inertiajs/vue3';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import FmDeleteButton from './FmDeleteButton.vue';

const props = {
    url: '/family/organizations/4',
    question: 'Delete Edison?',
    text: 'Its documents stay, without the organization.',
    confirmLabel: 'Delete the organization',
};

describe('FmDeleteButton', () => {
    it('asks first, with what happens, then deletes', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(FmDeleteButton, props);

        await fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
        expect(destroy).not.toHaveBeenCalled();
        expect(
            await screen.findByRole('heading', { name: 'Delete Edison?' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Its documents stay, without the organization.'),
        ).toBeInTheDocument();
        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the organization' }),
        );

        expect(destroy).toHaveBeenCalledWith(
            '/family/organizations/4',
            expect.anything(),
        );
    });

    it('gives up with Cancel', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(FmDeleteButton, props);

        await fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
        await fireEvent.click(
            await screen.findByRole('button', { name: 'Cancel' }),
        );

        expect(destroy).not.toHaveBeenCalled();
        await waitFor(() =>
            expect(
                screen.queryByRole('heading', { name: 'Delete Edison?' }),
            ).not.toBeInTheDocument(),
        );
    });
});
