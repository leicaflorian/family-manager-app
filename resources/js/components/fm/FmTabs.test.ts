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
});
