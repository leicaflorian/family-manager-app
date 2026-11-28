import { render, screen } from '@testing-library/vue';
import { Shapes } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmEmpty from './FmEmpty.vue';

describe('FmEmpty', () => {
    it('says why the page is empty and what can be done', () => {
        render(FmEmpty, {
            props: {
                icon: Shapes,
                title: 'Nothing yet',
                text: 'Start from a document.',
            },
            slots: { default: '<a href="/upload">Upload</a>' },
        });

        expect(
            screen.getByRole('heading', { name: 'Nothing yet' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Start from a document.')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Upload' }),
        ).toBeInTheDocument();
    });

    it('has the blue icon, or the green one when the empty page is good news', () => {
        const first = render(FmEmpty, {
            props: { icon: Shapes, title: 'Nothing yet' },
        });
        expect(first.container.querySelector('svg')!.parentElement).toHaveClass(
            'bg-action-soft',
            'text-action-dark',
        );
        first.unmount();

        const done = render(FmEmpty, {
            props: {
                icon: Shapes,
                title: 'Nothing left to sort',
                tone: 'paid',
            },
        });
        expect(done.container.querySelector('svg')!.parentElement).toHaveClass(
            'bg-paid-soft',
            'text-paid',
        );
    });
});
