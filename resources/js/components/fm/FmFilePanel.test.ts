import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { documentDetail } from '@/test/fixtures';
import FmFilePanel from './FmFilePanel.vue';

describe('FmFilePanel', () => {
    it('shows the preview of the file and its name under it', () => {
        render(FmFilePanel, { props: { file: documentDetail.file } });

        expect(
            screen.getByTitle('Busta paga ottobre 2026.pdf'),
        ).toHaveAttribute('src', '/files/12');
        expect(
            screen.getByText('Busta paga ottobre 2026.pdf'),
        ).toBeInTheDocument();
    });

    it('the grey box has the proportions of an A4 sheet, and the preview fills it', () => {
        const { container } = render(FmFilePanel, {
            props: { file: documentDetail.file },
        });

        expect(container.firstElementChild).toHaveClass('aspect-[210/297]');
        expect(screen.getByTitle('Busta paga ottobre 2026.pdf')).toHaveClass(
            'h-full',
        );
    });
});
