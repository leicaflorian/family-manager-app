import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import InvalidInvitation from './InvalidInvitation.vue';

describe('Invalid invitation', () => {
    it('says the invitation is no longer valid and leads to Log in', () => {
        renderPage(InvalidInvitation);

        expect(
            screen.getByRole('heading', {
                name: 'This invitation is no longer valid',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Go to Log in' }),
        ).toHaveAttribute('href', '/login');
    });
});
