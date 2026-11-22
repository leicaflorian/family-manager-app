import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmFilterBox from './FmFilterBox.vue';

describe('FmFilterBox', () => {
    it('is a named region around its fields', () => {
        render(FmFilterBox, {
            props: { id: 'filters' },
            slots: { default: '<p>The fields</p>' },
        });

        const region = screen.getByRole('region', { name: 'Filters' });

        expect(region).toHaveAttribute('id', 'filters');
        expect(region).toHaveTextContent('The fields');
    });
});
