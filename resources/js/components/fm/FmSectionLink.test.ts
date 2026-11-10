import { Plus } from '@lucide/vue';
import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmSectionLink from './FmSectionLink.vue';

describe('FmSectionLink', () => {
    it('with an address is a link, with its icon and text', () => {
        const result = render(FmSectionLink, {
            props: { href: '/budget/movements/create', icon: Plus },
            slots: { default: 'Record payment' },
        });

        expect(
            screen.getByRole('link', { name: 'Record payment' }),
        ).toHaveAttribute('href', '/budget/movements/create');
        expect(result.container.querySelector('svg')).not.toBeNull();
    });

    it('without an address is a button that says it was tapped', async () => {
        const result = render(FmSectionLink, { slots: { default: 'Add' } });

        await fireEvent.click(screen.getByRole('button', { name: 'Add' }));

        expect(result.emitted('click')).toHaveLength(1);
    });

    it('looks like a plain link: normal weight, no box, underlined on hover', () => {
        render(FmSectionLink, { slots: { default: 'Add' } });

        const button = screen.getByRole('button', { name: 'Add' });
        expect(button).toHaveClass('text-body', 'hover:underline');
        expect(button).not.toHaveClass('font-semibold', 'px-3', 'h-11.5');
    });
});
