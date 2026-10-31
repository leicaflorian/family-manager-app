import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { loadExamples } from '@/catalog/examples';
import { renderPage } from '@/test/page';
import DevCases from './DevCases.vue';

describe('every case of the catalogue', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it.each(Object.keys(loadExamples()))(
        '%s mounts without warnings',
        (name) => {
            const warn = vi.spyOn(console, 'warn');
            const error = vi.spyOn(console, 'error');

            renderPage(DevCases, { name });

            expect(warn).not.toHaveBeenCalled();
            expect(error).not.toHaveBeenCalled();
        },
    );
});
