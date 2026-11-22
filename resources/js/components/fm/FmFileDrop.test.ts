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

    it('turns the box blue while a file is over it, and back when it leaves', async () => {
        render(FmFileDrop);
        const zone = screen
            .getByLabelText('Choose the files')
            .closest('label') as HTMLElement;

        await fireEvent.dragOver(zone);
        expect(zone).toHaveClass('border-action', 'bg-action-soft');
        // Only one background: two would fight, and the order of the stylesheet would win
        expect(zone).not.toHaveClass('bg-card');

        await fireEvent.dragLeave(zone);
        expect(zone).toHaveClass('border-edge', 'bg-card');
        expect(zone).not.toHaveClass('bg-action-soft');
    });

    it('shows its own hint line instead of the default one', () => {
        render(FmFileDrop, {
            props: { hint: "From the gallery or the phone's files." },
        });

        expect(
            screen.getByText("From the gallery or the phone's files."),
        ).toBeInTheDocument();
        expect(
            screen.queryByText('PDF, JPG, PNG or HEIC, up to 25 MB each'),
        ).not.toBeInTheDocument();
    });

    it('writes the action as a link inside the sentence when compact', () => {
        render(FmFileDrop, {
            props: {
                compact: true,
                label: 'Drag the files here, or',
                action: 'choose from the computer',
            },
        });

        expect(screen.getByText('Drag the files here, or')).toBeInTheDocument();
        expect(screen.getByText('choose from the computer')).toHaveClass(
            'text-action',
            'text-body-strong',
        );
        expect(screen.getByLabelText('Choose the files')).toBeInTheDocument();
        expect(
            screen.getByText('Drag the files here, or').closest('span'),
        ).toHaveTextContent('Drag the files here, or choose from the computer');
    });

    it('is 320 px high by default and takes another least height', async () => {
        const view = render(FmFileDrop);
        const box = () =>
            screen
                .getByText('Drag the files here, or choose them')
                .closest('label');

        expect(box()).toHaveStyle({ minHeight: '320px' });

        await view.rerender({ minHeight: 260 });

        expect(box()).toHaveStyle({ minHeight: '260px' });
    });

    it('takes one file, with its own picker name and types, icon and chosen file name', () => {
        render(FmFileDrop, {
            props: {
                single: true,
                sheet: true,
                accept: 'image/*,application/pdf',
                inputLabel: 'Photo or PDF',
                chosen: 'dragon.jpg',
            },
        });

        const picker = screen.getByLabelText('Photo or PDF');
        expect(picker).not.toHaveAttribute('multiple');
        expect(picker).toHaveAttribute('accept', 'image/*,application/pdf');
        expect(screen.getByText('dragon.jpg')).toBeInTheDocument();
        expect(
            screen.queryByText('PDF, JPG, PNG or HEIC, up to 25 MB each'),
        ).toBeNull();
    });
});
