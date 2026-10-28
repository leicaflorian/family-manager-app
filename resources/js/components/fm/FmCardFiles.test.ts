import { router } from '@inertiajs/vue3';
import { fireEvent, screen } from '@testing-library/vue';
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
import { renderPage, stubObjectUrls } from '@/test/page';
import FmCardFiles from './FmCardFiles.vue';

const libretto = new File(['%PDF-1.4'], 'Libretto.pdf', {
    type: 'application/pdf',
});
const bollo = new File(['%PDF-1.4'], 'Bollo.pdf', { type: 'application/pdf' });
const card = { type: 'insurance_policy', id: 1 };
const booklet = { ...healthCard, id: 30, title: 'Libretto di circolazione' };

/**
 * The box with its files and its documents to link and unlink kept in refs,
 * as the form keeps them.
 *
 * @param props - the documents, the card, the errors, the documents to link
 * @returns the files, the documents to link and the ids to unlink so far
 */
function box(
    props: {
        documents?: App.Data.DocumentData[];
        card?: { type: string; id: number };
        canDelete?: boolean;
        errors?: string[];
        linkable?: App.Data.DocumentData[];
        hasLinkable?: boolean;
    } = {},
) {
    const files = ref<File[]>([]);
    const link = ref<App.Data.DocumentData[]>([]);
    const unlink = ref<number[]>([]);
    renderPage(
        defineComponent({
            setup() {
                return () =>
                    h(FmCardFiles, {
                        linkable: [],
                        ...props,
                        files: files.value,
                        'onUpdate:files': (next: File[]) =>
                            (files.value = next),
                        link: link.value,
                        'onUpdate:link': (next: App.Data.DocumentData[]) =>
                            (link.value = next),
                        unlink: unlink.value,
                        'onUpdate:unlink': (next: number[]) =>
                            (unlink.value = next),
                    });
            },
        }),
    );

    return { files, link, unlink };
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
afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

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

    it('opens the file of a linked document in a new tab, with only the menu beside it', () => {
        box({ documents: [healthCard], card, canDelete: true });

        const row = screen.getByRole('link', { name: /^Tessera sanitaria/ });
        expect(row).toHaveAttribute('href', healthCard.file_url);
        expect(row).toHaveAttribute('target', '_blank');
        expect(
            screen.queryByRole('link', {
                name: 'Open Tessera sanitaria in a new tab',
            }),
        ).not.toBeInTheDocument();
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
        const { files } = box({ documents: [healthCard], card });

        await choose(libretto);
        await choose(bollo);
        expect(files.value).toEqual([libretto, bollo]);
        const row = screen.getByRole('link', { name: /^Libretto.pdf/ });
        expect(row).toHaveAttribute('href', 'blob:chosen');
        expect(row).toHaveAttribute('target', '_blank');
        // Only the sheet of the file: no arrow, the ✕ follows
        expect(row.querySelectorAll('svg')).toHaveLength(1);

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

    it('links a document when the card is saved, and lets it go before', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const { link } = box({
            documents: [healthCard],
            card,
            linkable: [booklet],
            hasLinkable: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Link document' }),
        );
        await userEvent.click(
            await screen.findByRole('button', {
                name: /Libretto di circolazione/,
            }),
        );

        expect(post).not.toHaveBeenCalled();
        expect(link.value).toEqual([booklet]);
        expect(screen.getByText('Linked when you save')).toBeInTheDocument();
        const row = screen.getByRole('link', {
            name: /^Libretto di circolazione/,
        });
        expect(row).toHaveAttribute('href', booklet.file_url);
        expect(row).toHaveAttribute('target', '_blank');

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Remove Libretto di circolazione',
            }),
        );
        expect(link.value).toEqual([]);
    });

    it('has no Link when there is nothing to link', () => {
        box({ documents: [healthCard], card });

        expect(
            screen.queryByRole('button', { name: 'Link document' }),
        ).not.toBeInTheDocument();
    });

    it('unlinks a document when the card is saved, greyed until then, and undoes it', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        const { unlink } = box({ documents: [healthCard], card });

        await userEvent.click(
            screen.getByRole('button', {
                name: 'More actions on Tessera sanitaria',
            }),
        );
        await userEvent.click(
            await screen.findByRole('menuitem', { name: 'Unlink' }),
        );

        expect(destroy).not.toHaveBeenCalled();
        expect(unlink.value).toEqual([healthCard.id]);
        expect(screen.getByText('Unlinked when you save')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', {
                name: 'More actions on Tessera sanitaria',
            }),
        ).not.toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Keep Tessera sanitaria linked',
            }),
        );
        expect(unlink.value).toEqual([]);
        expect(
            screen.getByRole('button', {
                name: 'More actions on Tessera sanitaria',
            }),
        ).toBeInTheDocument();
    });
});
