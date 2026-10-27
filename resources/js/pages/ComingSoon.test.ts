import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import ComingSoon from './ComingSoon.vue';

describe('ComingSoon', () => {
    it('shows the name of the section and that it is coming soon', () => {
        renderPage(ComingSoon, { title: 'Family' });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Family' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('This page is coming soon'),
        ).toBeInTheDocument();
    });
});
