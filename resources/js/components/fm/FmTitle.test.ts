import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmTitle from './FmTitle.vue';

describe('FmTitle', () => {
    it('is the title of the page with the sentence below and the actions on the right', () => {
        render(FmTitle, {
            props: { title: 'Documents', subtitle: '18 documents' },
            slots: { actions: '<button>Upload</button>' },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Documents' }),
        ).toBeInTheDocument();
        expect(screen.getByText('18 documents')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Upload' }),
        ).toBeInTheDocument();
    });
});
