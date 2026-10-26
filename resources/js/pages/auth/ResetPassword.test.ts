import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import ResetPassword from './ResetPassword.vue';

describe('Nuova password', () => {
    it("mostra l'email e chiede la password nuova", () => {
        renderPagina(ResetPassword, {
            token: 'abc',
            email: 'marco.bianchi@example.com',
            passwordRules: '',
        });

        expect(screen.getByLabelText('Email')).toHaveValue(
            'marco.bianchi@example.com',
        );
        expect(screen.getByLabelText('Email')).toHaveAttribute('readonly');
        expect(
            screen.getByLabelText('Scegli una password nuova'),
        ).toHaveAttribute('type', 'password');
        expect(
            screen.getByRole('button', { name: 'Salva la password' }),
        ).toBeInTheDocument();
    });
});
