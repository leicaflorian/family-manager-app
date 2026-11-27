import { render, screen } from '@testing-library/vue';
import { Bell } from '@lucide/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmValueList from './FmValueList.vue';

const values: App.Data.CardValueData[] = [
    { label: 'Account', text: 'Carta Laura', href: null, tone: null },
    {
        label: 'Organization',
        text: 'Scuola Passo a Due',
        href: '/family/organizations/5',
        tone: null,
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

    it.each([false, true])(
        'writes a link in the primary colour (new tab: %s)',
        (newTab) => {
            render(FmValueList, { props: { values, newTab } });

            expect(
                screen.getByRole('link', { name: /Scuola Passo a Due/ }),
            ).toHaveClass('text-action');
            expect(screen.getByText('Carta Laura')).not.toHaveClass(
                'text-action',
            );
        },
    );

    it('shows the icon of a value before its text', () => {
        const { container } = render(FmValueList, {
            props: {
                values: [
                    {
                        label: 'Reminder',
                        text: 'Notice on 31/10',
                        href: null,
                        icon: Bell,
                    },
                    { label: 'Card', text: 'Polizza', href: null },
                ],
            },
        });

        expect(container.querySelectorAll('dd svg')).toHaveLength(1);
        expect(
            screen
                .getByText('Notice on 31/10')
                .closest('dd')
                ?.querySelector('svg'),
        ).not.toBeNull();
        expect(
            screen.getByText('Polizza').closest('dd')?.querySelector('svg'),
        ).toBeNull();
    });

    it('colours a status by its tone', () => {
        render(FmValueList, {
            props: {
                values: [
                    {
                        label: 'Status',
                        text: 'Sold',
                        href: null,
                        tone: 'closed',
                    },
                    {
                        label: 'Name',
                        text: 'Fiat Panda',
                        href: null,
                        tone: null,
                    },
                ],
            },
        });

        expect(screen.getByText('Sold')).toHaveClass('text-ink-soft');
        expect(screen.getByText('Fiat Panda')).not.toHaveClass('text-ink-soft');
    });
});
