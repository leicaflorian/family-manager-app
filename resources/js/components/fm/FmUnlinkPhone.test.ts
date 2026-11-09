import { router } from '@inertiajs/vue3';
import { fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import FmUnlinkPhone from './FmUnlinkPhone.vue';

const iphone: App.Data.LinkedDeviceData = {
    id: 4,
    name: 'iPhone of Marco',
    linked_on: '2026-10-12',
    last_upload_on: null,
    linked_now: false,
};

afterEach(() => vi.restoreAllMocks());

describe('FmUnlinkPhone', () => {
    it('asks nothing without a phone', () => {
        render(FmUnlinkPhone, { props: { device: null } });

        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('names the phone and says what happens', async () => {
        render(FmUnlinkPhone, { props: { device: iphone } });

        expect(
            await screen.findByText('Unlink this phone?'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'iPhone of Marco stops uploading documents until you link it again.',
            ),
        ).toBeInTheDocument();
    });

    it('deletes the phone only after the person confirms', async () => {
        const deleted = vi
            .spyOn(router, 'delete')
            .mockImplementation(() => undefined);
        render(FmUnlinkPhone, { props: { device: iphone } });

        await fireEvent.click(
            await screen.findByRole('button', { name: 'Unlink' }),
        );

        expect(deleted).toHaveBeenCalledWith(
            '/settings/devices/4',
            expect.anything(),
        );
    });

    it('tells the page when the question is cancelled', async () => {
        const view = render(FmUnlinkPhone, { props: { device: iphone } });

        await fireEvent.click(
            await screen.findByRole('button', { name: 'Cancel' }),
        );

        expect(view.emitted('close')).toHaveLength(1);
    });
});
