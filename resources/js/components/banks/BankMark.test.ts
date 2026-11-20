import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import BankMark from './BankMark.vue';

describe('BankMark', () => {
    it('shows the logo of the bank when it has one', () => {
        render(BankMark, {
            props: { name: 'Poste', logo: 'https://logo.test/poste.png' },
        });

        expect(screen.getByRole('img', { name: 'Poste' })).toHaveAttribute(
            'src',
            'https://logo.test/poste.png',
        );
    });

    it('draws the initials without a logo, hidden from screen readers', () => {
        render(BankMark, { props: { name: 'BancoPosta', logo: null } });

        expect(screen.getByText('BP')).toHaveAttribute('aria-hidden', 'true');
        expect(screen.queryByRole('img')).not.toBeInTheDocument();
    });
});
