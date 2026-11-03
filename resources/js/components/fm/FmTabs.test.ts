import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmTabs from './FmTabs.vue';

const tabs = [
    { label: 'Deadlines', href: '/agenda', current: false },
    { label: 'Reminders', href: '/reminders', count: 10, current: true },
];

describe('FmTabs', () => {
    it('leads to each page of the set', () => {
        render(FmTabs, { props: { tabs, label: 'Agenda' } });

        expect(
            screen.getByRole('navigation', { name: 'Agenda' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Deadlines' })).toHaveAttribute(
            'href',
            '/agenda',
        );
        expect(screen.getByRole('link', { name: /Reminders/ })).toHaveAttribute(
            'href',
            '/reminders',
        );
    });

    it('marks the page that is open and only that', () => {
        render(FmTabs, { props: { tabs, label: 'Agenda' } });

        expect(screen.getByRole('link', { name: /Reminders/ })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(
            screen.getByRole('link', { name: 'Deadlines' }),
        ).not.toHaveAttribute('aria-current');
    });

    it('shows the number of a tab that has one', () => {
        render(FmTabs, { props: { tabs, label: 'Agenda' } });

        expect(
            screen.getByRole('link', { name: 'Reminders 10' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Deadlines' }),
        ).toBeInTheDocument();
    });

    it('draws the tabs as a grey pill with the open one in white', () => {
        render(FmTabs, { props: { tabs, label: 'Agenda', variant: 'pill' } });

        expect(screen.getByRole('navigation', { name: 'Agenda' })).toHaveClass(
            'bg-[#E6EAF0]',
        );
        expect(screen.getByRole('link', { name: /Reminders/ })).toHaveClass(
            'bg-white',
        );
        expect(screen.getByRole('link', { name: 'Deadlines' })).not.toHaveClass(
            'bg-white',
        );
    });

    it('shows a tab that cannot be chosen as a name without a link', () => {
        render(FmTabs, {
            props: {
                label: 'Files',
                tabs: [
                    { label: 'All', href: '/to-sort', count: 5, current: true },
                    {
                        label: 'By mail',
                        href: '/to-sort?source=mail',
                        count: 0,
                        current: false,
                        disabled: true,
                    },
                ],
            },
        });

        expect(
            screen.queryByRole('link', { name: /By mail/ }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('By mail')).toHaveAttribute(
            'aria-disabled',
            'true',
        );
        expect(screen.getByRole('link', { name: 'All 5' })).toBeInTheDocument();
    });
});
