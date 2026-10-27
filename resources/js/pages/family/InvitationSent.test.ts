import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import InvitationSent from './InvitationSent.vue';

const invitation: App.Data.SentInvitationData = {
    email: 'laura.bianchi@example.com',
    link: 'http://localhost/invitation/abc',
    expires_on: '2026-11-02',
};

describe('Invitation sent', () => {
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
});
