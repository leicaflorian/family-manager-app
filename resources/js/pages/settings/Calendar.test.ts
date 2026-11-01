import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { fireEvent, screen, within } from '@testing-library/vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import Calendar from './Calendar.vue';

const on: App.Data.CalendarSettingsData = {
    on: true,
    url: 'http://localhost/calendar/abc.ics',
    can_change: true,
};
const off: App.Data.CalendarSettingsData = {
    on: false,
    url: null,
    can_change: true,
};

describe('Settings, Calendar', () => {
    it('turns the calendar on with the switch', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Calendar, { calendar: off });

        expect(
            screen.queryByLabelText('Calendar address'),
        ).not.toBeInTheDocument();
        await userEvent.click(screen.getByLabelText('Calendar on'));
        await settle();

        expect(post).toHaveBeenCalledWith(
            '/settings/calendar',
            {},
            expect.anything(),
        );
    });

    it('turns the calendar off with the switch', async () => {
        const destroy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        renderPage(Calendar, { calendar: on });

        await userEvent.click(screen.getByLabelText('Calendar on'));
        await settle();

        expect(destroy).toHaveBeenCalledWith(
            '/settings/calendar',
            expect.anything(),
        );
    });

    it('shows the address, copies it and says so', async () => {
        const copy = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText: copy } });
        renderPage(Calendar, { calendar: on });

        expect(screen.getByLabelText('Calendar address')).toHaveValue(
            'http://localhost/calendar/abc.ics',
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Copy' }));

        expect(copy).toHaveBeenCalledWith('http://localhost/calendar/abc.ics');
        expect(await screen.findByText('Address copied')).toBeInTheDocument();
    });

    it('explains how to add it to Google Calendar', () => {
        renderPage(Calendar, { calendar: on });

        expect(screen.getAllByRole('listitem')).toHaveLength(3);
        expect(screen.getByText(/Don't download the file/)).toBeInTheDocument();
    });

    it('asks before changing the address', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        post.mockClear();
        renderPage(Calendar, { calendar: on });

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Change the address (the old one stops working)',
            }),
        );
        expect(post).not.toHaveBeenCalled();

        const dialog = await screen.findByRole('dialog');
        await fireEvent.click(
            within(dialog).getByRole('button', { name: 'Change the address' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/settings/calendar/reset',
            {},
            expect.anything(),
        );
    });

    it('offers to change the address when it cannot be read any more', () => {
        renderPage(Calendar, { calendar: { ...on, url: null } });

        expect(
            screen.queryByLabelText('Calendar address'),
        ).not.toBeInTheDocument();
        expect(screen.getByText(/can't be shown any more/)).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: 'Change the address (the old one stops working)',
            }),
        ).toBeInTheDocument();
    });

    it('lets a member see and copy the address, nothing else', () => {
        renderPage(Calendar, { calendar: { ...on, can_change: false } });

        expect(screen.getByLabelText('Calendar address')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Copy' }),
        ).toBeInTheDocument();
        expect(screen.queryByLabelText('Calendar on')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Change the address/ }),
        ).not.toBeInTheDocument();
    });
});
