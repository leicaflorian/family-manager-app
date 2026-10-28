import { router } from '@inertiajs/vue3';
import { fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
import FmFilePanel from './FmFilePanel.vue';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('FmFilePanel', () => {
    it('shows the preview of the file and its name under it', () => {
        render(FmFilePanel, { props: { file: documentDetail.file } });

        expect(
            screen.getByRole('img', { name: 'Busta paga ottobre 2026.pdf' }),
        ).toHaveAttribute('src', '/files/12/preview');
        expect(
            screen.getByText('Busta paga ottobre 2026.pdf'),
        ).toBeInTheDocument();
    });

    it('the grey box has the proportions of an A4 sheet, and the preview fills it', () => {
        const { container } = render(FmFilePanel, {
            props: { file: documentDetail.file },
        });

        expect(container.firstElementChild).toHaveClass('aspect-[210/297]');
        expect(
            screen.getByRole('img', { name: 'Busta paga ottobre 2026.pdf' }),
        ).toHaveClass('max-h-full');
    });

    it('without changeUrl does not offer to change the file', () => {
        render(FmFilePanel, { props: { file: documentDetail.file } });

        expect(
            screen.queryByRole('button', { name: 'Change the file' }),
        ).not.toBeInTheDocument();
    });

    it('with changeUrl sends the file chosen to take the place of this one', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        render(FmFilePanel, {
            props: {
                file: documentDetail.file,
                changeUrl: '/documents/4/file',
            },
        });

        expect(
            screen.getByRole('button', { name: 'Change the file' }),
        ).toBeInTheDocument();
        const picker = screen.getByLabelText('Change the file', {
            selector: 'input',
        });
        expect(picker).toHaveAttribute(
            'accept',
            expect.stringContaining('.heic'),
        );

        const file = new File(['%PDF-1.4'], 'Busta paga.pdf', {
            type: 'application/pdf',
        });
        await fireEvent.change(picker, { target: { files: [file] } });

        expect(post).toHaveBeenCalledOnce();
        expect(post.mock.calls[0]?.[0]).toBe('/documents/4/file');
        expect(post.mock.calls[0]?.[1]).toMatchObject({ file });
    });
});
