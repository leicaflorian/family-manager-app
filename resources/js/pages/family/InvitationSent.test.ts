import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, withTrail } from '@/test/page';
import InvitationSent from './InvitationSent.vue';

const invitation: App.Data.SentInvitationData = {
    email: 'laura.bianchi@example.com',
    link: 'http://localhost/invitation/abc',
    expires_on: '2026-11-02',
};

describe('Invitation sent', () => {
    it('writes the trail Family > Invitation sent into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(InvitationSent, { invitation }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Family', href: '/family' },
            { label: 'Invitation sent' },
        ]);
    });

    it('shows the link to copy and how long it is valid', async () => {
        const copy = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText: copy } });
        renderPage(InvitationSent, { invitation });

        expect(
            screen.getByText(/laura.bianchi@example.com/),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('The link to join')).toHaveValue(
            'http://localhost/invitation/abc',
        );
        expect(
            screen.getByText(/It is valid for 7 days, until 02\/11\/2026/),
        ).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Copy the link' }),
        );

        expect(copy).toHaveBeenCalledWith('http://localhost/invitation/abc');
        expect(
            await screen.findByRole('button', { name: 'Copied' }),
        ).toBeInTheDocument();
    });

    it('hides the link during a visit', () => {
        renderPage(InvitationSent, {
            invitation: { ...invitation, link: null },
        });

        expect(
            screen.queryByLabelText('The link to join'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Copy the link' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText('The link is hidden during a visit.'),
        ).toBeInTheDocument();
    });

    it('says so when the browser refuses to copy', async () => {
        const copy = vi.fn().mockRejectedValue(new Error('denied'));
        Object.assign(navigator, { clipboard: { writeText: copy } });
        renderPage(InvitationSent, { invitation });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Copy the link' }),
        );

        expect(
            await screen.findByText(
                'Could not copy: select the text and copy it by hand.',
            ),
        ).toHaveAttribute('role', 'status');
        expect(
            screen.getByRole('button', { name: 'Copy the link' }),
        ).toBeInTheDocument();
    });
});
