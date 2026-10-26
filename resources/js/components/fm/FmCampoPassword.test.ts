import { plugin as formkit } from '@formkit/vue';
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import formkitConfig from '@/formkit.config';
import FmCampoPassword from './FmCampoPassword.vue';

/**
 * Il campo della password, come nel modulo *Iscriviti*.
 */
function campo(errors: string[] = []) {
    return render(FmCampoPassword, {
        props: {
            label: 'Scegli una password',
            name: 'password',
            help: 'Almeno 10 caratteri',
            errors,
        },
        global: { plugins: [[formkit, formkitConfig]] },
    });
}

describe('FmCampoPassword', () => {
    it("nasconde la password e la mostra con l'occhio", async () => {
        campo();
        const input = screen.getByLabelText('Scegli una password');

        expect(input).toHaveAttribute('type', 'password');
        await userEvent.click(
            screen.getByRole('button', { name: 'Mostra la password' }),
        );
        expect(input).toHaveAttribute('type', 'text');
        await userEvent.click(
            screen.getByRole('button', { name: 'Nascondi la password' }),
        );
        expect(input).toHaveAttribute('type', 'password');
    });

    it('mostra il suggerimento e gli errori del server', () => {
        campo(['La password deve avere almeno 10 caratteri.']);

        expect(screen.getByText('Almeno 10 caratteri')).toBeInTheDocument();
        expect(
            screen.getByText('La password deve avere almeno 10 caratteri.'),
        ).toBeInTheDocument();
    });
});
