import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { danceDeadline, lateDeadline, salaryDeadline } from '@/test/fixtures';
import FmDeadlineRow from './FmDeadlineRow.vue';

describe('FmDeadlineRow', () => {
    it('leads to the due date, with its card, its day and its amount', () => {
        render(FmDeadlineRow, {
            props: {
                deadline: danceDeadline,
                href: '/budget/planned/expense/4/2026-12-05',
            },
        });

        const link = screen.getByRole('link');
        expect(link).toHaveAttribute(
            'href',
            '/budget/planned/expense/4/2026-12-05',
        );
        expect(link).toHaveTextContent('Retta danza');
        expect(link).toHaveTextContent('Danza Giulia · 05/12/2026');
        expect(link).toHaveTextContent('45,00 €');
        expect(link).toHaveTextContent('60,00 €');
    });

    it('is red when late', () => {
        render(FmDeadlineRow, {
            props: { deadline: lateDeadline, href: '/x' },
        });

        expect(screen.getByText('Luce · 23/10/2026, late')).toHaveClass(
            'text-overdue',
        );
        expect(screen.getByText('~71,30 €')).toBeInTheDocument();
    });

    it('marks the due date on the page', () => {
        render(FmDeadlineRow, {
            props: { deadline: danceDeadline, href: '/x', current: true },
        });

        expect(screen.getByRole('link')).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('dated: the date is the title and where it stands below, without the title or the area', () => {
        const { container } = render(FmDeadlineRow, {
            props: {
                deadline: { ...lateDeadline, card: danceDeadline.card },
                href: '/x',
                dated: true,
            },
        });

        const link = screen.getByRole('link');
        expect(link).toHaveTextContent('23/10/2026');
        expect(screen.getByText(/^Overdue by \d+ days$/)).toHaveClass(
            'text-overdue',
        );
        expect(link).not.toHaveTextContent(lateDeadline.title);
        expect(container.querySelector('[data-dot]')).toBeNull();
    });

    it('writes an income with a plus', () => {
        render(FmDeadlineRow, {
            props: { deadline: salaryDeadline, href: '/x' },
        });

        expect(screen.getByText('+1.850,00 €')).toBeInTheDocument();
    });
});
