import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useFileUpload } from './useFileUpload';

const file = new File(['%PDF-1.4'], 'Libretto.pdf', {
    type: 'application/pdf',
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('useFileUpload', () => {
    it('sends the files to Upload as form data', () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});

        useFileUpload(() => undefined).send([file]);

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0]?.[0]).toBe('/upload');
        expect(post.mock.calls[0]?.[1]).toMatchObject({ files: [file] });
        expect(post.mock.calls[0]?.[2]).toMatchObject({ forceFormData: true });
    });

    it('sends them with the card they are linked to', () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});

        useFileUpload(() => ({ type: 'vehicle', id: 1 })).send([file]);

        expect(decodeURIComponent(`${post.mock.calls[0]?.[0] as string}`)).toBe(
            '/upload?link=vehicle:1',
        );
    });

    it('gives the messages of the files refused, all together', () => {
        vi.spyOn(router, 'post').mockImplementation(
            (_url, _data, options?: VisitOptions) => {
                options?.onError?.({
                    'files.0': 'Each file can be at most 25 MB.',
                    'files.1':
                        'Only PDF, JPG, PNG and HEIC files can be uploaded.',
                });
            },
        );

        const { send, errors } = useFileUpload(() => undefined);
        send([file, file]);

        expect(errors.value).toEqual([
            'Each file can be at most 25 MB.',
            'Only PDF, JPG, PNG and HEIC files can be uploaded.',
        ]);
    });
});
