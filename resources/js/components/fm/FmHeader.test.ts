import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmHeader from './FmHeader.vue';

describe('FmHeader', () => {
    it('writes the title and the sentence below', () => {
        render(FmHeader, {
            props: {
                title: 'Hi, Rosa',
                subtitle: 'Marco invited you.',
            },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Hi, Rosa' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Marco invited you.')).toBeInTheDocument();
    });
});
