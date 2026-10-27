import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useShare } from './useShare';

const file = {
    name: 'Bill.pdf',
    mime_type: 'application/pdf',
    url: '/files/1',
    download_url: '/files/1/download',
};

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('useShare', () => {
    it('opens the menu of the phone with the file, where there is one', async () => {
        const share = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { share, canShare: () => true });
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(new Blob(['%PDF']))),
        );

        const { canShare, shareFile } = useShare();
        expect(canShare).toBe(true);
        await shareFile(file);

        expect(fetch).toHaveBeenCalledWith('/files/1', {
            credentials: 'same-origin',
        });
        const shared = share.mock.calls[0]?.[0] as ShareData;
        expect(shared.title).toBe('Bill.pdf');
        expect(shared.files?.[0]?.name).toBe('Bill.pdf');
    });

    it('cannot share where the browser has no share menu', () => {
        vi.stubGlobal('navigator', {});

        expect(useShare().canShare).toBe(false);
    });

    it('does nothing when the person closes the menu', async () => {
        vi.stubGlobal('navigator', {
            share: vi
                .fn()
                .mockRejectedValue(new DOMException('closed', 'AbortError')),
            canShare: () => true,
        });
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(new Response(new Blob(['%PDF']))),
        );

        await expect(useShare().shareFile(file)).resolves.toBeUndefined();
    });
});
