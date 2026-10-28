import { render } from '@testing-library/vue';
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
});
