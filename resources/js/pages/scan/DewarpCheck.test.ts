import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import DewarpCheck from './DewarpCheck.vue';

const lib = vi.hoisted(() => ({
    available: true,
    load: vi.fn(),
    dewarp: vi.fn(),
}));

vi.mock('@/lib/scan/dewarp', () => ({
    isDewarpAvailable: () => lib.available,
    loadDewarp: lib.load,
    dewarp: lib.dewarp,
}));

vi.mock('@/lib/scan/canvas', () => ({
    readPhoto: vi.fn(async () => ({
        width: 4,
        height: 2,
        data: new Uint8ClampedArray(4 * 2 * 4),
    })),
    toObjectUrl: vi.fn(async () => 'blob:flat'),
}));

beforeEach(() => {
    lib.available = true;
    lib.load.mockReset().mockResolvedValue(undefined);
    lib.dewarp.mockReset().mockImplementation(async (sheet) => sheet);
});

describe('DewarpCheck', () => {
    it('asks for a photo of a sheet', () => {
        renderPage(DewarpCheck);

        expect(
            screen.getByRole('heading', { name: 'Straighten check' }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Photo of a sheet')).toHaveAttribute(
            'accept',
            'image/*',
        );
    });

    it('says when this phone cannot straighten', () => {
        lib.available = false;

        renderPage(DewarpCheck);

        expect(
            screen.getByText('Straighten is not available on this phone'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Photo of a sheet')).toBeDisabled();
    });

    it('times the model and the page, and shows the flat sheet', async () => {
        renderPage(DewarpCheck);

        await fireEvent.change(screen.getByLabelText('Photo of a sheet'), {
            target: {
                files: [new File(['x'], 'receipt.jpg', { type: 'image/jpeg' })],
            },
        });

        await waitFor(() =>
            expect(
                screen.getByText(/^Model: \d+(\.\d)? s · Page: \d+(\.\d)? s$/),
            ).toBeInTheDocument(),
        );
        expect(lib.load).toHaveBeenCalledTimes(1);
        expect(lib.dewarp).toHaveBeenCalledTimes(1);
        expect(screen.getByAltText('The flat sheet')).toHaveAttribute(
            'src',
            'blob:flat',
        );
    });

    it('says what went wrong', async () => {
        lib.dewarp.mockRejectedValue(new Error('broken'));

        renderPage(DewarpCheck);
        await fireEvent.change(screen.getByLabelText('Photo of a sheet'), {
            target: {
                files: [new File(['x'], 'receipt.jpg', { type: 'image/jpeg' })],
            },
        });

        expect(await screen.findByText('broken')).toBeInTheDocument();
    });
});
