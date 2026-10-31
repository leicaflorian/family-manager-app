import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import ComponentFrame from './ComponentFrame.vue';

describe('ComponentFrame', () => {
    it('shows only the cases of the component, without the groups', () => {
        renderPage(ComponentFrame, { component: 'FmRow' });

        expect(
            screen.getByRole('heading', { name: 'Overdue' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('log', { name: 'Events' })).toBeInTheDocument();
        expect(
            screen.queryByRole('navigation', { name: 'Components' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('searchbox', { name: 'Search a component' }),
        ).not.toBeInTheDocument();
    });
});
