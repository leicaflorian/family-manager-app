import { fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { formPlugins } from '@/lib/formPlugins';
import FmCalendarLink from './FmCalendarLink.vue';

const on: App.Data.CalendarSettingsData = {
    on: true,
    url: 'http://localhost/calendar/abc.ics',
    can_change: true,
    hidden: false,
};

/** Renders the block with the plugins of the app. */
function show(
    calendar: App.Data.CalendarSettingsData,
    settingsHref?: string,
): ReturnType<typeof render> {
    return render(FmCalendarLink, {
        props: { calendar, settingsHref },
        global: { plugins: formPlugins },
    });
}

describe('FmCalendarLink', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('shows the address read only, with the privacy line', () => {
        show(on);

        expect(screen.getByLabelText('Calendar address')).toHaveValue(
            'http://localhost/calendar/abc.ics',
        );
        expect(screen.getByLabelText('Calendar address')).toHaveAttribute(
            'readonly',
        );
        expect(
            screen.getByText(
                'Anyone with the address can read the dates, so keep it for the family.',
            ),
        ).toBeInTheDocument();
    });

    it('copies the address and says so', async () => {
        const copy = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText: copy } });
        show(on);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Copy the address' }),
        );

        expect(copy).toHaveBeenCalledWith('http://localhost/calendar/abc.ics');
        expect(await screen.findByText('Address copied')).toBeInTheDocument();
    });

    it('says so and selects the address when it cannot be copied', async () => {
        Object.assign(navigator, {
            clipboard: {
                writeText: vi.fn().mockRejectedValue(new Error('no')),
            },
        });
        show(on);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Copy the address' }),
        );

        expect(
            await screen.findByText(/Could not copy: select the address/),
        ).toBeInTheDocument();
        const input =
            screen.getByLabelText<HTMLInputElement>('Calendar address');
        expect(input.selectionEnd).toBe(input.value.length);
    });

    it('starts with the steps of Google and changes them with the choice', async () => {
        show(on);

        expect(
            screen.getByText(/Paste the address and tap/),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Google updates it by itself/),
        ).toBeInTheDocument();
        expect(screen.getByText(/next to «Other calendars»/)).toBeVisible();

        await fireEvent.click(screen.getByRole('button', { name: 'Outlook' }));

        expect(
            await screen.findByText(/Subscribe from web/),
        ).toBeInTheDocument();
        expect(
            screen.queryByText(/next to «Other calendars»/),
        ).not.toBeInTheDocument();

        await fireEvent.click(
            screen.getByRole('button', { name: 'iPhone and Mac' }),
        );

        expect(
            await screen.findByText(/Add Subscribed Calendar/),
        ).toBeInTheDocument();
    });

    it('warns not to download the file', () => {
        show(on);

        expect(screen.getByText(/Don't download the file/)).toBeInTheDocument();
    });

    it('links to the settings only for who can change them', () => {
        const view = show(on, '/settings/calendar');

        expect(
            screen.getByRole('link', {
                name: 'Change the address or turn off the calendar in Settings',
            }),
        ).toHaveAttribute('href', '/settings/calendar');

        view.unmount();
        show({ ...on, can_change: false }, '/settings/calendar');

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });

    it('has no link without the address of the settings', () => {
        show(on);

        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });

    it('says the address is hidden during a visit', () => {
        show({ ...on, url: null, hidden: true });

        expect(
            screen.getByText('The address is hidden during a visit.'),
        ).toBeInTheDocument();
        expect(
            screen.queryByLabelText('Calendar address'),
        ).not.toBeInTheDocument();
    });

    it('says the address cannot be shown any more', () => {
        show({ ...on, url: null });

        expect(screen.getByText(/can't be shown any more/)).toBeInTheDocument();
    });

    it('offers to turn on a calendar that is off, to who can', () => {
        show({ ...on, on: false, url: null }, '/settings/calendar');

        expect(screen.getByText('The calendar is off.')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Turn on the calendar' }),
        ).toHaveAttribute('href', '/settings/calendar');
    });

    it('tells a member who turns it on, with no button', () => {
        show(
            { ...on, on: false, url: null, can_change: false },
            '/settings/calendar',
        );

        expect(
            screen.getByText(
                'Whoever manages the family can turn it on in Settings.',
            ),
        ).toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
