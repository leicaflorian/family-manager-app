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

        const heading = screen.getByRole('heading', {
            level: 1,
            name: 'Hi, Rosa',
        });
        expect(heading).toBeInTheDocument();
        // larger with the layout of the computer, as the card of `AuthLayout`
        expect(heading).toHaveClass('text-title-mobile', 'computer:text-title');
        expect(screen.getByText('Marco invited you.')).toBeInTheDocument();
    });
});
