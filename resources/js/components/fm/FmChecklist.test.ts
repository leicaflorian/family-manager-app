import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmChecklist from './FmChecklist.vue';

const steps = [
    {
        title: 'The home bills',
        subtitle:
            'Electricity, gas, water and internet are already in the areas',
        href: '/areas',
        done: true,
    },
    {
        title: 'Upload the first bill',
        subtitle: 'Take a photo or choose the PDF',
        href: '/upload',
        done: false,
    },
];

describe('FmChecklist', () => {
    it('lists the steps, each a link', () => {
        render(FmChecklist, { props: { steps } });

        expect(screen.getByText('To begin')).toBeInTheDocument();
        expect(screen.getAllByRole('link')).toHaveLength(2);
        expect(
            screen.getByRole('link', { name: /Upload the first bill/ }),
        ).toHaveAttribute('href', '/upload');
    });

    it('marks a step done with a tick and the text struck through', () => {
        render(FmChecklist, { props: { steps } });

        expect(screen.getByTitle('The home bills')).toHaveClass('line-through');
        expect(screen.getByTitle('Upload the first bill')).not.toHaveClass(
            'line-through',
        );
        expect(screen.getAllByText('Done')).toHaveLength(1);
    });
});
