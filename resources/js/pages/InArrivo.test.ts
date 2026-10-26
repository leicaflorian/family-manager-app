import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import InArrivo from './InArrivo.vue';

describe('InArrivo', () => {
    it('mostra il nome della sezione e che arriva presto', () => {
        renderPagina(InArrivo, { titolo: 'Famiglia' });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Famiglia' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Questa pagina arriva presto'),
        ).toBeInTheDocument();
    });
});
