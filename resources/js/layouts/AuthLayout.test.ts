import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import AuthLayout from './AuthLayout.vue';

describe('AuthLayout', () => {
    it('shows the title of the page, the blue panel and the other way', () => {
        render(AuthLayout, {
            props: {
                title: 'Welcome back',
                subtitle: 'Enter your family.',
                panel: {
                    title: 'Everything about the family, in one place.',
                    text: 'Everyone’s deadlines.',
                },
                otherWay: {
                    text: 'No Family Manager yet?',
                    link: 'Sign up your family',
                    href: '/register',
                },
            },
            slots: { default: '<form aria-label="Log in"></form>' },
        });

        expect(
            screen.getByRole('heading', { level: 1, name: 'Welcome back' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Enter your family.')).toBeInTheDocument();
        expect(
            screen.getByText('Everything about the family, in one place.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Sign up your family' }),
        ).toHaveAttribute('href', '/register');
        expect(
            screen.getByRole('form', { name: 'Log in' }),
        ).toBeInTheDocument();
    });

    it('lists the points of the panel', () => {
        render(AuthLayout, {
            props: {
                title: 'Sign up your family',
                panel: {
                    title: 'Start today.',
                    text: 'No card needed.',
                    points: ['The deadlines', 'The documents'],
                },
            },
        });

        expect(
            screen.getAllByRole('listitem').map((li) => li.textContent?.trim()),
        ).toEqual(['The deadlines', 'The documents']);
    });

    it('shows the default sentence of the panel without one', () => {
        render(AuthLayout, { props: { title: 'Log in' } });

        expect(
            screen.getByText('Everything about the family, in one place.'),
        ).toBeInTheDocument();
    });
});
