import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import InvitoMandato from './InvitoMandato.vue';

const invito: App.Data.InvitoMandatoData = {
    email: 'laura.bianchi@example.com',
    link: 'http://localhost/invito/abc',
    scade: '02/11/2026',
};

describe('Invito mandato', () => {
    it('mostra il link da copiare e quanto vale', async () => {
        const copia = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText: copia } });
        renderPagina(InvitoMandato, { invito });

        expect(
            screen.getByText(/laura.bianchi@example.com/),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Il link per entrare')).toHaveValue(
            'http://localhost/invito/abc',
        );
        expect(
            screen.getByText(/Vale 7 giorni, fino al 02\/11\/2026/),
        ).toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'Copia il link' }),
        );

        expect(copia).toHaveBeenCalledWith('http://localhost/invito/abc');
        expect(
            await screen.findByRole('button', { name: 'Copiato' }),
        ).toBeInTheDocument();
    });
});
