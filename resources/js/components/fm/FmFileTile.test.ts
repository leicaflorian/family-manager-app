import { FileImage, Mail, Smartphone } from '@lucide/vue';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmFileTile from './FmFileTile.vue';

describe('FmFileTile', () => {
    it('draws the sheet of a file, hidden from screen readers', () => {
        const { container } = render(FmFileTile);

        const tile = container.firstElementChild;
        expect(tile).toHaveClass('h-[46px]', 'w-[38px]');
        expect(tile).toHaveAttribute('aria-hidden', 'true');
        expect(tile?.querySelector('svg')).not.toBeNull();
    });

    it('square is the box of 44 px of a file waiting, with the icon of its kind', () => {
        const { container } = render(FmFileTile, {
            props: { square: true, icon: FileImage },
        });

        const tile = container.firstElementChild;
        expect(tile).toHaveClass('size-11', 'rounded-md');
        expect(tile).not.toHaveClass('h-[46px]');
        expect(
            tile?.querySelector('[class*="lucide-file-image"]'),
        ).not.toBeNull();
    });

    it('puts where the file came from in a small circle on the corner', () => {
        const { container } = render(FmFileTile, {
            props: { square: true, icon: Mail, badge: Smartphone },
        });

        const circle = container.querySelector('[data-badge]');
        expect(circle).toHaveClass('rounded-full', 'size-5.5');
        expect(
            circle?.querySelector('[class*="lucide-smartphone"]'),
        ).not.toBeNull();
    });

    it('a copy has the label Copy under the sheet, and no circle', () => {
        const { container } = render(FmFileTile, {
            props: { square: true, badge: Smartphone, copy: true },
        });

        expect(screen.getByText('Copy')).toHaveClass(
            'text-badge',
            'text-warning',
        );
        expect(container.querySelector('[data-badge]')).toBeNull();
    });
});
