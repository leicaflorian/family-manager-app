import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmFileDrop from './FmFileDrop.vue';

const libretto = new File(['%PDF-1.4'], 'Libretto.pdf', {
    type: 'application/pdf',
});

describe('FmFileDrop', () => {
    it('asks for the files of documents, with the accepted types', () => {
        render(FmFileDrop);

        expect(
            screen.getByText('Drag the files here, or choose them'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('PDF, JPG, PNG or HEIC, up to 25 MB each'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Choose the files')).toHaveAttribute(
            'accept',
            expect.stringContaining('.heic'),
        );
    });

    it('gives the chosen files, and can choose the same file again', async () => {
        const result = render(FmFileDrop);
        const input =
            screen.getByLabelText<HTMLInputElement>('Choose the files');

        await fireEvent.change(input, { target: { files: [libretto] } });

        expect(result.emitted('files')).toEqual([[[libretto]]]);
        expect(input.value).toBe('');
    });

    it('gives the dropped files', async () => {
        const result = render(FmFileDrop);

        await fireEvent.drop(
            screen.getByText('Drag the files here, or choose them'),
            {
                dataTransfer: { files: [libretto] },
            },
        );

        expect(result.emitted('files')).toEqual([[[libretto]]]);
    });

    it('gives nothing when no file is chosen', async () => {
        const result = render(FmFileDrop);

        await fireEvent.change(screen.getByLabelText('Choose the files'), {
            target: { files: [] },
        });

        expect(result.emitted('files')).toBeUndefined();
    });

    it('shows its own text and that the files are being sent', () => {
        render(FmFileDrop, {
            props: {
                label: 'Other files? Drag them here, or choose them',
                processing: true,
            },
        });

        expect(
            screen.getByText('Other files? Drag them here, or choose them'),
        ).toBeInTheDocument();
        expect(screen.getByText('Uploading…')).toBeInTheDocument();
    });
});
