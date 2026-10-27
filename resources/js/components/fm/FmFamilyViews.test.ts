import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmFamilyViews from './FmFamilyViews.vue';

const family = { name: 'Bianchi', people: 3, organizations: 14 };

describe('FmFamilyViews', () => {
    it('shows the family and the two views, with the current one marked', () => {
        render(FmFamilyViews, { props: { family, current: 'organizations' } });

        expect(
            screen.getByRole('heading', { name: 'Family', level: 1 }),
        ).toBeInTheDocument();
        expect(screen.getByText('Bianchi family')).toBeInTheDocument();
        const people = screen.getByRole('link', { name: 'People 3' });
        const organizations = screen.getByRole('link', {
            name: 'Organizations 14',
        });
        expect(people).toHaveAttribute('href', '/family/people');
        expect(people).not.toHaveAttribute('aria-current');
        expect(organizations).toHaveAttribute('href', '/family/organizations');
        expect(organizations).toHaveAttribute('aria-current', 'page');
    });
});
