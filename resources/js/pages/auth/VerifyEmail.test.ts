import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import VerifyEmail from './VerifyEmail.vue';

describe("Conferma l'email", () => {
    it("offre di rimandare l'email e di uscire", () => {
        renderPagina(VerifyEmail);

        expect(
            screen.getByRole('button', { name: "Mandami di nuovo l'email" }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Esci' }),
        ).toBeInTheDocument();
    });

    it('dopo il nuovo invio lo dice', () => {
        renderPagina(VerifyEmail, { status: 'verification-link-sent' });

        expect(screen.getByRole('status')).toHaveTextContent(
            "Ti abbiamo mandato un'email nuova",
        );
    });
});
