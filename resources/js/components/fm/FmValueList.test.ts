import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmValueList from './FmValueList.vue';

const values: App.Data.CardValueData[] = [
    { label: 'Account', text: 'Carta Laura', href: null },
    {
        label: 'Organization',
        text: 'Scuola Passo a Due',
        href: '/family/organizations/5',
    },
];

describe('FmValueList', () => {
    it('lists the labels with their values, and a value with an address as a link', () => {
        render(FmValueList, { props: { values } });

        expect(screen.getByText('Account')).toBeInTheDocument();
        expect(screen.getByText('Carta Laura')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Scuola Passo a Due' }),
        ).toHaveAttribute('href', '/family/organizations/5');
        expect(
            screen.getByRole('link', { name: 'Scuola Passo a Due' }),
        ).not.toHaveAttribute('target');
    });

    it('opens the link in a new tab when asked', () => {
        render(FmValueList, { props: { values, newTab: true } });

        expect(
            screen.getByRole('link', { name: /Scuola Passo a Due/ }),
        ).toHaveAttribute('target', '_blank');
    });
});
