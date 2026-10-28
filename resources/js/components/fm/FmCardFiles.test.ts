import { fireEvent, render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { defineComponent, h, ref } from 'vue';
import { healthCard } from '@/test/fixtures';
import { stubObjectUrls } from '@/test/page';
import FmCardFiles from './FmCardFiles.vue';

const libretto = new File(['%PDF-1.4'], 'Libretto.pdf', {
    type: 'application/pdf',
});
const bollo = new File(['%PDF-1.4'], 'Bollo.pdf', { type: 'application/pdf' });
const card = { type: 'insurance_policy', id: 1 };

/**
 * The box with its files kept in a ref, as the form keeps them.
 *
 * @param props - the documents, the card, the errors
 * @returns the files chosen so far
 */
function box(
    props: {
        documents?: App.Data.DocumentData[];
        card?: { type: string; id: number };
        canDelete?: boolean;
        errors?: string[];
    } = {},
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

/**
 * Whether an element comes before another in the page.
 *
 * @param first - the element expected first
 * @param second - the element expected after
 * @returns true when `first` comes first
 */
function before(first: Element, second: Element): boolean {
    return Boolean(
        first.compareDocumentPosition(second) &
        Node.DOCUMENT_POSITION_FOLLOWING,
    );
}

beforeEach(() => stubObjectUrls());
afterEach(() => vi.unstubAllGlobals());

describe('FmCardFiles', () => {
    it('has the zone at the top, then the new files, then the linked documents', async () => {
        box({ documents: [healthCard], card, canDelete: true });

        await choose(libretto);

        const zone = screen.getByText(
            'Its documents? Drag them here, or choose them',
        );
        const chosen = screen.getByText('Libretto.pdf');
        const linked = screen.getByText('Tessera sanitaria');
        expect(before(zone, chosen)).toBe(true);
        expect(before(chosen, linked)).toBe(true);
    });

    it('opens a linked document in a new tab, with the buttons of the card page', () => {
        box({ documents: [healthCard], card, canDelete: true });

        const row = screen.getByRole('link', { name: /^Tessera sanitaria/ });
        expect(row).toHaveAttribute('href', `/documents/${healthCard.id}`);
        expect(row).toHaveAttribute('target', '_blank');
        expect(
            screen.getByRole('link', {
                name: 'Open Tessera sanitaria in a new tab',
            }),
        ).toHaveAttribute('href', healthCard.file_url);
        expect(
            screen.getByRole('button', {
                name: 'More actions on Tessera sanitaria',
            }),
        ).toBeInTheDocument();
        expect(screen.queryByText('No documents yet')).not.toBeInTheDocument();
    });

    it('leaves the date out of the rows, as the box is narrow', () => {
        box({ documents: [healthCard], card });

        // The date of healthCard, which a row elsewhere shows on the right
        expect(screen.queryByText('20/10/2020')).not.toBeInTheDocument();
    });

    it('says so when the card has no documents', () => {
        box();

        expect(screen.getByText('No documents yet')).toBeInTheDocument();
    });

    it('opens a new file in a new tab, and takes one away', async () => {
        const files = box({ documents: [healthCard], card });

        await choose(libretto);
        await choose(bollo);
        expect(files.value).toEqual([libretto, bollo]);
        const row = screen.getByRole('link', { name: /^Libretto.pdf/ });
        expect(row).toHaveAttribute('href', 'blob:chosen');
        expect(row).toHaveAttribute('target', '_blank');

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
