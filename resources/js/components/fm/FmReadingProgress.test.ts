import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmReadingProgress from './FmReadingProgress.vue';

describe('FmReadingProgress', () => {
    it('says the document is being read and that it takes a few seconds', () => {
        render(FmReadingProgress, { props: { name: 'bolletta.pdf' } });

        expect(screen.getByRole('status')).toHaveTextContent(
            'Reading the document…',
        );
        expect(screen.getByText('It takes a few seconds')).toBeInTheDocument();
    });

    it('shows the name with the pages, one or more', () => {
        const view = render(FmReadingProgress, {
            props: { name: 'bolletta.pdf', pages: 1 },
        });
        expect(screen.getByText('bolletta.pdf · 1 page')).toBeInTheDocument();

        return view.rerender({ name: 'bolletta.pdf', pages: 3 }).then(() => {
            expect(
                screen.getByText('bolletta.pdf · 3 pages'),
            ).toBeInTheDocument();
        });
    });

    it('shows only the name when the pages are not known', () => {
        render(FmReadingProgress, { props: { name: 'bolletta.pdf' } });

        expect(screen.getByText('bolletta.pdf')).toBeInTheDocument();
    });

    it('lists what it found so far', () => {
        render(FmReadingProgress, {
            props: {
                name: 'bolletta.pdf',
                found: [
                    { label: 'From', value: 'Edison' },
                    { label: 'Amount', value: '84,20 €' },
                ],
            },
        });

        expect(screen.getByText('Edison')).toBeInTheDocument();
        expect(screen.getByText('84,20 €')).toBeInTheDocument();
        expect(screen.getByText('Amount')).toBeInTheDocument();
    });
});
