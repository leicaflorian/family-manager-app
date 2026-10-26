import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import ForgotPassword from './ForgotPassword.vue';

describe('Password dimenticata', () => {
    it("chiede l'email per mandare il link", () => {
        renderPagina(ForgotPassword);

        expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
        expect(
            screen.getByRole('button', { name: 'Mandami il link' }),
        ).toBeInTheDocument();
        expect(screen.queryByRole('status')).toBeNull();
    });

    it("dopo l'invio dice dove guardare e offre di rimandarlo", () => {
        renderPagina(ForgotPassword, { status: 'Ti abbiamo inviato il link.' });

        expect(screen.getByRole('status')).toHaveTextContent(
            "Il link vale un'ora",
        );
        expect(
            screen.getByRole('button', { name: 'Mandami di nuovo il link' }),
        ).toBeInTheDocument();
    });
});
