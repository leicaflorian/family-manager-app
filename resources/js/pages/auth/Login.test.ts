import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import Login from './Login.vue';

describe('Accedi', () => {
    it('chiede email e password, con Resta collegato già scelto', () => {
        renderPagina(Login, { canResetPassword: true, canRegister: true });

        expect(screen.getByLabelText('Email')).toHaveAttribute('type', 'email');
        expect(screen.getByLabelText('Password')).toHaveAttribute(
            'type',
            'password',
        );
        expect(screen.getByLabelText('Resta collegato')).toBeChecked();
        expect(
            screen.getByRole('link', { name: 'Password dimenticata?' }),
        ).toHaveAttribute('href', '/forgot-password');
        expect(screen.getByRole('button', { name: 'Accedi' })).toHaveAttribute(
            'type',
            'submit',
        );
    });

    it('mostra il messaggio che arriva dal server', () => {
        renderPagina(Login, {
            canResetPassword: true,
            status: 'La password è stata cambiata.',
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'La password è stata cambiata.',
        );
    });
});
