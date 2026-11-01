import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmNotice from './FmNotice.vue';

describe('FmNotice', () => {
    it('is a message the screen reader announces', () => {
        render(FmNotice, {
            slots: { default: 'I will remind you 3 days before.' },
        });

        expect(screen.getByRole('status')).toHaveTextContent(
            'I will remind you 3 days before.',
        );
    });

    it('is green in the ok tone, orange in the warning one', () => {
        const result = render(FmNotice, {
            props: { tone: 'ok' },
            slots: { default: 'Done' },
        });
        expect(screen.getByRole('status')).toHaveClass('text-[#14533F]');
        result.unmount();

        render(FmNotice, {
            props: { tone: 'warning' },
            slots: { default: 'The logbook is missing' },
        });
        expect(screen.getByRole('status')).toHaveClass('text-warning');
    });

    it('is red in the danger tone', () => {
        render(FmNotice, {
            props: { tone: 'danger' },
            slots: { default: 'Expired 3 days ago' },
        });

        expect(screen.getByRole('status')).toHaveClass('text-overdue');
    });

    it('shows a title above the text', () => {
        render(FmNotice, {
            props: { tone: 'warning', title: 'Due in 26 days' },
            slots: { default: 'We will remind you on 25/11.' },
        });

        expect(screen.getByText('Due in 26 days')).toBeInTheDocument();
        expect(screen.getByRole('status')).toHaveTextContent(
            'We will remind you on 25/11.',
        );
    });

    it('shows the action next to the text, as the person uses it', () => {
        render(FmNotice, {
            slots: {
                default: 'The logbook is missing.',
                action: '<a href="/documents/new">Upload it</a>',
            },
        });

        expect(screen.getByRole('link', { name: 'Upload it' })).toHaveAttribute(
            'href',
            '/documents/new',
        );
    });
});
