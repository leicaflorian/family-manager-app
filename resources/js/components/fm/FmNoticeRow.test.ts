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
        render(FmNoticeRow, { props: { notice: { ...notice, read: true } } });

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

    it('is one POST to the read address when a new notice is tapped', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        render(FmNoticeRow, { props: { notice, readHref: '/notices/1/read' } });

        await fireEvent.click(screen.getByRole('button'));

        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit.mock.calls[0]?.[0]).toBe('/notices/1/read');
        expect(visit.mock.calls[0]?.[1]).toMatchObject({ method: 'post' });
    });

    it('is a plain link to the page when the notice was seen', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        render(FmNoticeRow, {
            props: {
                notice: { ...notice, read: true },
                readHref: '/notices/1/read',
            },
        });

        await fireEvent.click(screen.getByRole('link'));

        expect(visit).toHaveBeenCalledTimes(1);
        expect(visit.mock.calls[0]?.[0]).toBe('/cards/insurance/2');
        expect(visit.mock.calls[0]?.[1]).not.toMatchObject({ method: 'post' });
    });
});
