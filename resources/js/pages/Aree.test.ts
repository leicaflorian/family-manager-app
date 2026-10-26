import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPagina } from '@/test/pagina';
import Aree from './Aree.vue';

describe('Aree', () => {
    it('dice che le schede arrivano presto e porta ai documenti', () => {
        renderPagina(Aree);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Aree' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Le schede arrivano presto'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Vai ai documenti' }),
        ).toHaveAttribute('href', '/documenti');
    });
});
