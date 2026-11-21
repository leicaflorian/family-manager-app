import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useCopy } from './useCopy';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('useCopy', () => {
    it('copies the text and says it was copied', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        const { copy, copied, failed } = useCopy();

        expect(await copy('123456789')).toBe(true);

        expect(writeText).toHaveBeenCalledWith('123456789');
        expect(copied.value).toBe(true);
        expect(failed.value).toBe(false);
    });

    it('says it failed when the browser refuses, so the page can ask to copy by hand', async () => {
        vi.stubGlobal('navigator', {
            clipboard: {
                writeText: vi.fn().mockRejectedValue(new Error('no')),
            },
        });
        const { copy, copied, failed } = useCopy();

        expect(await copy('123456789')).toBe(false);

        expect(copied.value).toBe(false);
        expect(failed.value).toBe(true);
    });

    it('fails without a clipboard at all, as on a page that is not secure', async () => {
        vi.stubGlobal('navigator', {});
        const { copy, failed } = useCopy();

        expect(await copy('123456789')).toBe(false);
        expect(failed.value).toBe(true);
    });

    it('forgets both when reset, for a new text to copy', async () => {
        vi.stubGlobal('navigator', {
            clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
        });
        const { copy, copied, failed, reset } = useCopy();
        await copy('123456789');

        reset();

        expect(copied.value).toBe(false);
        expect(failed.value).toBe(false);
    });

    it('selects the element when the browser refuses, and leaves it alone when it copies', async () => {
        const element = document.createElement('button');
        element.textContent = 'a@b.c';
        document.body.append(element);
        const writeText = vi.fn().mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { writeText } });
        const { copyOrSelect } = useCopy();

        expect(await copyOrSelect('a@b.c', element)).toBe(true);
        expect(window.getSelection()?.toString()).toBe('');

        writeText.mockRejectedValue(new Error('no'));
        expect(await copyOrSelect('a@b.c', element)).toBe(false);
        expect(window.getSelection()?.toString()).toBe('a@b.c');
        element.remove();
    });
});
