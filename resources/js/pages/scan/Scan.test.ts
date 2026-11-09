import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import Scan from './Scan.vue';

describe('Scan', () => {
    it('shows its title', () => {
        renderPage(Scan);

        expect(
            screen.getByRole('heading', { name: 'Scan' }),
        ).toBeInTheDocument();
    });
});
