import { render, screen } from '@testing-library/vue';
import { CalendarDays, List } from '@lucide/vue';
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
            'bg-segment',
        );
        expect(screen.getByRole('link', { name: /Reminders/ })).toHaveClass(
            'bg-card',
        );
        expect(screen.getByRole('link', { name: 'Deadlines' })).not.toHaveClass(
            'bg-card',
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

    it('shows a number of late things as a red badge, with its words for screen readers', () => {
        render(FmTabs, {
            props: {
                label: 'Agenda views',
                variant: 'pill',
                tabs: [
                    {
                        label: 'To do',
                        href: '/agenda',
                        count: 3,
                        countLabel: 'To do, 3 late',
                        current: true,
                    },
                    {
                        label: 'Done',
                        href: '/agenda?view=done',
                        current: false,
                    },
                ],
            },
        });

        const tab = screen.getByRole('link', { name: 'To do, 3 late' });
        const badge = screen.getByText('3');
        expect(tab).toContainElement(badge);
        expect(badge).toHaveAttribute('aria-hidden', 'true');
        expect(badge).toHaveClass('bg-overdue');
    });

    it('shows a number to look at as a blue badge, with its words for screen readers', () => {
        render(FmTabs, {
            props: {
                label: 'Budget',
                tabs: [
                    {
                        label: 'From the bank',
                        href: '/budget/bank',
                        count: 6,
                        countLabel: 'From the bank, 6 to sort',
                        countInfo: true,
                        current: true,
                    },
                ],
            },
        });

        const tab = screen.getByRole('link', {
            name: 'From the bank, 6 to sort',
        });
        const badge = screen.getByText('6');
        expect(tab).toContainElement(badge);
        expect(badge).toHaveAttribute('aria-hidden', 'true');
        expect(badge).toHaveClass(
            'bg-action',
            'text-on-action',
            'h-5',
            'min-w-5',
            'rounded-full',
            'text-badge',
        );
        expect(badge).not.toHaveClass('bg-overdue');
    });

    it('shows only the icon of a tab with one, named for screen readers', () => {
        render(FmTabs, {
            props: {
                label: 'How to see the Agenda',
                variant: 'pill',
                tabs: [
                    {
                        label: 'List',
                        href: '/agenda',
                        icon: List,
                        current: true,
                    },
                    {
                        label: 'Calendar',
                        href: '/agenda?calendar=1',
                        icon: CalendarDays,
                        current: false,
                    },
                ],
            },
        });

        const list = screen.getByRole('link', { name: 'List' });
        expect(list).toHaveAttribute('title', 'List');
        expect(list).toHaveTextContent('');
        expect(list.querySelector('svg')).not.toBeNull();
        expect(list).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByRole('navigation', { name: 'How to see the Agenda' }),
        ).toHaveClass('flex-none');
    });
});
