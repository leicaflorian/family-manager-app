import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { fireEvent, screen, within } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, settle, withTrail } from '@/test/page';
import Calendar from './Calendar.vue';

const on: App.Data.CalendarSettingsData = {
    on: true,
    url: 'http://localhost/calendar/abc.ics',
    can_change: true,
    hidden: false,
};
const off: App.Data.CalendarSettingsData = {
    on: false,
    url: null,
    can_change: true,
    hidden: false,
};

describe('Settings, Calendar', () => {
    it('writes the trail Settings > Calendar into the top bar', () => {
        const { state, provide } = withTrail();
        renderPage(Calendar, { calendar: off }, provide);

        expect(state.pieces.value).toEqual([
            { label: 'Settings', href: '/settings' },
            { label: 'Calendar' },
        ]);
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

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

    it('says the address is hidden during a visit', () => {
        renderPage(Calendar, {
            calendar: { ...on, url: null, hidden: true },
        });

        expect(
            screen.queryByLabelText('Calendar address'),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText('The address is hidden during a visit.'),
        ).toBeInTheDocument();
        expect(
            screen.queryByText(/can't be shown any more/),
        ).not.toBeInTheDocument();
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

    it('forgets that the address was copied when the address changes', async () => {
        Object.assign(navigator, {
            clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
        });
        const view = renderPage(Calendar, { calendar: on });

        await fireEvent.click(screen.getByRole('button', { name: 'Copy' }));
        expect(await screen.findByText('Address copied')).toBeInTheDocument();

        await view.rerender({
            calendar: { ...on, url: 'http://localhost/calendar/new.ics' },
        });

        expect(screen.queryByText('Address copied')).not.toBeInTheDocument();
    });

    it('says so and selects the address when it cannot be copied', async () => {
        Object.assign(navigator, {
            clipboard: {
                writeText: vi.fn().mockRejectedValue(new Error('no')),
            },
        });
        renderPage(Calendar, { calendar: on });

        await fireEvent.click(screen.getByRole('button', { name: 'Copy' }));

        expect(
            await screen.findByText(/Could not copy: select the address/),
        ).toBeInTheDocument();
        expect(screen.queryByText('Address copied')).not.toBeInTheDocument();
        const input =
            screen.getByLabelText<HTMLInputElement>('Calendar address');
        expect(input.selectionStart).toBe(0);
        expect(input.selectionEnd).toBe(input.value.length);
    });

    it('puts the switch back when the server refuses', async () => {
        const post = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                setTimeout(() => options?.onError?.({}), 0);
            });
        renderPage(Calendar, { calendar: off });

        await userEvent.click(screen.getByLabelText('Calendar on'));
        await settle();

        expect(post).toHaveBeenCalled();
        await settle();
        expect(screen.getByLabelText('Calendar on')).not.toBeChecked();
    });
});
