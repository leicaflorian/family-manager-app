import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vite-plus/test';
import FmChip from './FmChip.vue';

describe('FmChip', () => {
    it('is a toggle button that says if it is on, with its count in the name', () => {
        const view = render(FmChip, {
            props: { label: 'Documents', count: 3, pressed: true },
        });

        const chip = screen.getByRole('button', { name: 'Documents 3' });
        expect(chip).toHaveAttribute('aria-pressed', 'true');

        return view
            .rerender({ label: 'Documents', count: 3, pressed: false })
            .then(() => expect(chip).toHaveAttribute('aria-pressed', 'false'));
    });

    it('works without a count', () => {
        render(FmChip, { props: { label: 'All', pressed: false } });

        expect(screen.getByRole('button', { name: 'All' })).toBeInTheDocument();
    });

    it('emits click', async () => {
        const view = render(FmChip, {
            props: { label: 'Documents', pressed: false },
        });

        await userEvent.click(screen.getByRole('button'));

        expect(view.emitted()).toHaveProperty('click');
    });

    it('is a link that says the page it is on when it has an address', () => {
        render(FmChip, {
            props: {
                label: 'By mail',
                count: 2,
                href: '/to-sort?source=mail',
                pressed: true,
            },
        });

        const chip = screen.getByRole('link', { name: 'By mail 2' });
        expect(chip).toHaveAttribute('href', '/to-sort?source=mail');
        expect(chip).toHaveAttribute('aria-current', 'page');
        expect(chip).not.toHaveAttribute('aria-pressed');
    });

    it('a link that is off has no aria-current', () => {
        render(FmChip, {
            props: { label: 'By mail', href: '/to-sort', pressed: false },
        });

        expect(
            screen.getByRole('link', { name: 'By mail' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('disabled shows the text with no link and no button', () => {
        render(FmChip, {
            props: {
                label: 'By mail',
                count: 0,
                href: '/to-sort',
                disabled: true,
            },
        });

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(
            screen.getByText('By mail').closest('[aria-disabled]'),
        ).toHaveAttribute('aria-disabled', 'true');
    });

    it('solid, when on, is filled with the action colour', () => {
        render(FmChip, {
            props: { label: 'All', variant: 'solid', pressed: true },
        });

        expect(screen.getByRole('button', { name: 'All' })).toHaveClass(
            'bg-action',
        );
    });

    it('solid, when off, looks like the soft one off', () => {
        render(FmChip, {
            props: { label: 'All', variant: 'solid', pressed: false },
        });

        expect(screen.getByRole('button', { name: 'All' })).not.toHaveClass(
            'bg-action',
        );
    });
});
