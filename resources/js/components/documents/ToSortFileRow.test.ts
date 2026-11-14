import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { pendingPayslip } from '@/test/fixtures';
import { renderPage } from '@/test/page';
import ToSortFileRow from './ToSortFileRow.vue';

/** A file as the server sends it, over the payslip of the fixtures. */
function file(
    attributes: Partial<App.Data.PendingFileData> = {},
): App.Data.PendingFileData {
    return {
        ...pendingPayslip,
        name: 'Telefono 2026-10-25 19.04.pdf',
        source: 'phone',
        note: 'Uploaded from the phone on 25/10/2026 by Laura: «dentista Giulia»',
        short_note: 'Laura · «dentista Giulia»',
        created_on: '2026-10-25',
        ...attributes,
    };
}

/** Renders the row inside a list, as the page does. */
function row(
    props: { file: App.Data.PendingFileData; chosen: boolean; touch?: boolean },
    listeners: Record<string, unknown> = {},
) {
    return renderPage(ToSortFileRow, { ...props, ...listeners });
}

describe('ToSortFileRow on the phone (touch)', () => {
    it('emits choose with true when touched, and false when already chosen', async () => {
        const chosen: boolean[] = [];
        const view = row(
            { file: file(), chosen: false, touch: true },
            { onChoose: (on: boolean) => chosen.push(on) },
        );
        const button = screen.getByRole('button', {
            name: /Telefono 2026-10-25 19.04.pdf/,
        });

        expect(button).toHaveAttribute('aria-pressed', 'false');
        await fireEvent.click(button);
        await view.rerender({ chosen: true });
        expect(
            screen.getByRole('button', {
                name: /Telefono 2026-10-25 19.04.pdf/,
            }),
        ).toHaveAttribute('aria-pressed', 'true');
        await fireEvent.click(
            screen.getByRole('button', {
                name: /Telefono 2026-10-25 19.04.pdf/,
            }),
        );

        expect(chosen).toEqual([true, false]);
    });

    it('shows the short note and not the long one', () => {
        row({ file: file(), chosen: false, touch: true });

        expect(
            screen.getByText('Laura · «dentista Giulia»'),
        ).toBeInTheDocument();
        expect(screen.queryByText(/Uploaded from the phone/)).toBeNull();
        expect(screen.getByText('25/10')).toBeInTheDocument();
    });

    it('cannot choose a copy and says it can be deleted', () => {
        row({
            file: file({ copy_of: 'Other.pdf', short_note: null }),
            chosen: false,
            touch: true,
        });

        expect(
            screen.getByRole('button', {
                name: /Telefono 2026-10-25 19.04.pdf/,
            }),
        ).toBeDisabled();
        expect(
            screen.getByText('It is a copy: it can be deleted'),
        ).toBeInTheDocument();
    });

    it('has the bin only when the file can be deleted', () => {
        const first = row({
            file: file({ can_delete: true }),
            chosen: false,
            touch: true,
        });
        expect(
            screen.getByRole('button', { name: 'Delete' }),
        ).toBeInTheDocument();
        first.unmount();

        row({ file: file({ can_delete: false }), chosen: false, touch: true });
        expect(screen.queryByRole('button', { name: 'Delete' })).toBeNull();
    });
});

describe('ToSortFileRow without touch', () => {
    it('shows the kind of file in the box and where it came from on its corner, not before the note', () => {
        const { container } = row({
            file: file({ mime_type: 'image/jpeg', source: 'upload' }),
            chosen: false,
        });

        expect(
            container.querySelector('[class*="lucide-file-image"]'),
        ).not.toBeNull();
        expect(
            container.querySelector('[data-badge] [class*="lucide-upload"]'),
        ).not.toBeNull();
        expect(
            container.querySelectorAll('[class*="lucide-upload"]'),
        ).toHaveLength(1);
    });

    it('a mail text has the envelope in the box', () => {
        const { container } = row({
            file: file({ mime_type: 'text/plain', source: 'mail' }),
            chosen: false,
        });

        expect(
            container.querySelector(
                '[aria-hidden="true"] > [class*="lucide-mail"]',
            ),
        ).not.toBeNull();
    });

    it('a copy says which file it is the same as, with Copy under the box', () => {
        row({ file: file({ copy_of: 'Other.pdf' }), chosen: false });

        expect(screen.getByText('Same as Other.pdf')).toHaveClass(
            'text-warning',
        );
        expect(screen.getAllByText('Copy')).toHaveLength(1);
    });

    it('keeps the name as a link that opens the file in a new tab, with the long note', () => {
        row({ file: file(), chosen: false });

        const link = screen.getByRole('link', {
            name: 'Telefono 2026-10-25 19.04.pdf',
        });
        expect(link).toHaveAttribute('href', '/files/12');
        expect(link).toHaveAttribute('target', '_blank');
        expect(
            screen.getByText(
                'Uploaded from the phone on 25/10/2026 by Laura: «dentista Giulia»',
            ),
        ).toBeInTheDocument();
    });
});
