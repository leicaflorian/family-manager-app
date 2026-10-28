import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmOrganizationMark from './FmOrganizationMark.vue';

describe('FmOrganizationMark', () => {
    it('shows the icon of the type without a logo', () => {
        const { container } = render(FmOrganizationMark, {
            props: { types: ['bank'], name: 'Intesa' },
        });

        expect(screen.queryByRole('img')).toBeNull();
        expect(container.querySelector('svg')).not.toBeNull();
    });

    it('shows the logo when there is one', () => {
        const { container } = render(FmOrganizationMark, {
            props: { types: ['bank'], logo: '/files/8', name: 'Intesa' },
        });

        expect(screen.getByRole('img', { name: 'Intesa' })).toHaveAttribute(
            'src',
            '/files/8',
        );
        expect(container.querySelector('svg')).toBeNull();
    });
});
