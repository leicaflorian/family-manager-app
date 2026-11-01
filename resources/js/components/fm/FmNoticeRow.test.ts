import { fireEvent, render, screen } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { describe, expect, it, vi } from 'vite-plus/test';
import type { NoticeItem } from '@/lib/reminders';
import FmNoticeRow from './FmNoticeRow.vue';

const notice: NoticeItem = {
    id: 1,
    title: 'Renew the policy · Panda car insurance',
    due_text: 'Due on 21/11, in 26 days',
    sent_at: '2026-01-05T06:00:00+01:00',
    read: false,
    area: 'insurance',
    state: null,
    href: '/cards/insurance/2',
};

describe('FmNoticeRow', () => {
    it('shows the title, the deadline and when it went out', () => {
        render(FmNoticeRow, { props: { notice } });

        expect(
            screen.getByRole('link', {
                name: /Renew the policy · Panda car insurance/,
            }),
        ).toHaveAttribute('href', '/cards/insurance/2');
        expect(
            screen.getByText('Due on 21/11, in 26 days'),
        ).toBeInTheDocument();
        expect(screen.getByText('05/01 at 6:00')).toBeInTheDocument();
    });

    it('marks a notice that is new and only that', () => {
        const first = render(FmNoticeRow, { props: { notice } });
        expect(screen.getByLabelText('New notice')).toBeInTheDocument();
        first.unmount();

        render(FmNoticeRow, { props: { notice: { ...notice, read: true } } });
        expect(screen.queryByLabelText('New notice')).not.toBeInTheDocument();
    });

    it('says whether the reminder is late or done today', () => {
        const first = render(FmNoticeRow, {
            props: { notice: { ...notice, state: 'overdue' } },
        });
        expect(screen.getByText('Overdue')).toBeInTheDocument();
        first.unmount();

        render(FmNoticeRow, {
            props: { notice: { ...notice, state: 'done' } },
        });
        expect(screen.getByText('Done')).toBeInTheDocument();
    });

    it('sends open when it is tapped', async () => {
        vi.spyOn(router, 'visit').mockImplementation(() => {});
        const view = render(FmNoticeRow, { props: { notice } });

        await fireEvent.click(screen.getByRole('link'));

        expect(view.emitted()).toHaveProperty('open');
    });
});
