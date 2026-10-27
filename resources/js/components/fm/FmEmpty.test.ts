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
});
