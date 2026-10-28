import { fireEvent, render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import { defineComponent, h, ref } from 'vue';
import { healthCard } from '@/test/fixtures';
import FmCardFiles from './FmCardFiles.vue';

const libretto = new File(['%PDF-1.4'], 'Libretto.pdf', {
    type: 'application/pdf',
});
const bollo = new File(['%PDF-1.4'], 'Bollo.pdf', { type: 'application/pdf' });

/**
 * The box with its files kept in a ref, as the form keeps them.
 *
 * @param props - the documents and the errors
 * @returns the files chosen so far
 */
function box(
    props: { documents?: App.Data.DocumentData[]; errors?: string[] } = {},
) {
    const files = ref<File[]>([]);
    render(
        defineComponent({
            setup() {
                return () =>
                    h(FmCardFiles, {
                        ...props,
                        files: files.value,
                        'onUpdate:files': (next: File[]) =>
                            (files.value = next),
                    });
            },
        }),
    );

    return files;
}

/**
 * Chooses files in the zone, as the person does.
 *
 * @param chosen - the files
 */
async function choose(...chosen: File[]): Promise<void> {
    await fireEvent.change(screen.getByLabelText('Choose the files'), {
        target: { files: chosen },
    });
}

describe('FmCardFiles', () => {
    it('shows the documents already linked to the card, without leaving the form', () => {
        box({ documents: [healthCard] });

        expect(
            screen.getByRole('heading', { name: 'Documents' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Tessera sanitaria')).toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.queryByText('No documents yet')).not.toBeInTheDocument();
    });

    it('says so when the card has no documents', () => {
        box();

        expect(screen.getByText('No documents yet')).toBeInTheDocument();
    });

    it('adds the chosen files to the list, and takes one away', async () => {
        const files = box({ documents: [healthCard] });

        await choose(libretto);
        await choose(bollo);
        expect(files.value).toEqual([libretto, bollo]);
        expect(screen.getByText('Libretto.pdf')).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove Libretto.pdf' }),
        );
        expect(files.value).toEqual([bollo]);
        expect(screen.queryByText('Libretto.pdf')).not.toBeInTheDocument();
    });

    it('shows why the server refused a file', () => {
        box({ errors: ['Only PDF, JPG, PNG and HEIC files can be uploaded.'] });

        expect(
            screen.getByText(
                'Only PDF, JPG, PNG and HEIC files can be uploaded.',
            ),
        ).toBeInTheDocument();
    });
});
