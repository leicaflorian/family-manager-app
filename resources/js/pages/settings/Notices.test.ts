import { router } from '@inertiajs/vue3';
import userEvent from '@testing-library/user-event';
import { screen, within } from '@testing-library/vue';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { renderPage, settle } from '@/test/page';
import { hasChanges } from '@/lib/unsavedChanges';
import Notices from './Notices.vue';

const people: App.Data.ReminderPersonData[] = [
    { id: 1, name: 'Marco', initials: 'MB', photo: null },
    { id: 2, name: 'Laura', initials: 'LB', photo: null },
];

const groups: App.Data.ReminderGroupData[] = [
    {
        id: 10,
        name: 'Vehicles',
        is_default: false,
        deadline_types: [
            'vehicles.road_tax_due_date',
            'vehicles.inspection_due_date',
        ],
        rules: [
            {
                name: 'Promemoria',
                days: 45,
                timing: 'before',
                channels: 'both',
                recipients: ['all'],
            },
            {
                name: 'Promemoria',
                days: 15,
                timing: 'before',
                channels: 'app',
                recipients: [1],
            },
        ],
        example:
            'With inspection – Fiat Panda – of 09/01/2027 the notices go out on 25/11 and 25/12.',
    },
    {
        id: 11,
        name: 'Policies',
        is_default: false,
        deadline_types: ['insurance_policies.expiry_date'],
        rules: [],
        example: null,
    },
    {
        id: 12,
        name: 'Others',
        is_default: true,
        deadline_types: [],
        rules: [
            {
                name: 'Promemoria',
                days: 0,
                timing: 'before',
                channels: 'app',
                recipients: ['all'],
            },
        ],
        example: null,
    },
];

const kinds: App.Data.OptionData[] = [
    {
        value: 'vehicles.road_tax_due_date',
        label: 'Road tax',
        help: null,
    },
    {
        value: 'vehicles.inspection_due_date',
        label: 'Inspection',
        help: null,
    },
    {
        value: 'insurance_policies.expiry_date',
        label: 'Policy renewal',
        help: null,
    },
];

/** The props the server sends, for an administrator unless changed. */
function props(
    changes: Partial<App.Data.NoticeSettingsData> = {},
): Record<string, unknown> {
    return {
        settings: {
            groups,
            kinds,
            people,
            without_account: ['Giulia'],
            can_change: true,
            ...changes,
        },
    };
}

/** The card of a group. */
function card(name: string): HTMLElement {
    return screen.getByRole('region', { name });
}

/** The groups the last save sent. */
function sentGroups(put: ReturnType<typeof vi.spyOn>): {
    id: number | null;
    name: string;
    is_default: boolean;
    deadline_types: string[];
    notifications: {
        days: number;
        timing: string;
        channels: string;
        recipients: (string | number)[];
    }[];
}[] {
    const call = put.mock.calls[put.mock.calls.length - 1]!;

    return (call[1] as { groups: never }).groups;
}

/** Makes the page believe it is on a computer, or on a phone. */
function screenWidth(computer: boolean): void {
    vi.stubGlobal('matchMedia', (query: string) => ({
        matches:
            query.includes('prefers-reduced-motion') ||
            (computer && query.includes('min-width')),
        media: query,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
    }));
}

describe('Settings, Notices', () => {
    beforeEach(() => {
        screenWidth(true);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('shows a card for each group with its kinds and its notices', () => {
        renderPage(Notices, props());

        const vehicles = card('Vehicles');
        expect(
            within(vehicles).getByText('Road tax, inspection'),
        ).toBeVisible();
        expect(
            within(vehicles).getByText(
                '45 days before · App and email · Everyone',
            ),
        ).toBeVisible();
        expect(
            within(vehicles).getByText('15 days before · App only · Marco'),
        ).toBeVisible();
        expect(within(vehicles).getByText(/Fiat Panda/)).toBeVisible();
        expect(within(card('Others')).getByText('Default')).toBeVisible();
        expect(
            within(card('Others')).getByText(
                'Reminders written by hand and all the rest',
            ),
        ).toBeVisible();
    });

    it('says who gets no notice', () => {
        renderPage(Notices, props());

        expect(
            screen.getByText(
                'Notices go out every morning at 6:00. They arrive only to those with an account: Giulia gets none.',
            ),
        ).toBeVisible();
    });

    it('leaves a member to look: no edit, no new group, no save', () => {
        renderPage(Notices, props({ can_change: false }));

        expect(
            screen.queryByRole('button', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'New group' }),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Save' }),
        ).not.toBeInTheDocument();
        expect(card('Vehicles')).toBeVisible();
    });

    it('adds a notice with 7 days before, app and email, everyone', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        await userEvent.click(
            within(card('Policies')).getByRole('button', {
                name: 'Add a notice',
            }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put).toHaveBeenCalledWith(
            '/settings/notices',
            expect.anything(),
            expect.anything(),
        );
        expect(
            sentGroups(put).find((group) => group.id === 11)?.notifications,
        ).toMatchObject([
            {
                days: 7,
                timing: 'before',
                channels: 'both',
                recipients: ['all'],
            },
        ]);
    });

    it('takes Everyone away when a person is chosen', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Vehicles')).getByRole('button', { name: 'Edit' }),
        );
        await userEvent.type(
            within(card('Vehicles')).getAllByLabelText('To whom')[0]!,
            'Lau',
        );
        await userEvent.click(
            await screen.findByRole('option', { name: 'Laura' }),
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(sentGroups(put)[0]!.notifications[0]!.recipients).toEqual([2]);
    });

    it('saves every group together', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Vehicles')).getByRole('button', { name: 'Edit' }),
        );
        await userEvent.click(
            within(card('Vehicles')).getAllByRole('button', {
                name: 'Remove this notice',
            })[0]!,
        );
        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        const name = within(card('Policies')).getByLabelText(
            'Name of the group',
        );
        await userEvent.clear(name);
        await userEvent.type(name, 'Insurance');
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(put).toHaveBeenCalledTimes(1);
        expect(sentGroups(put).map((group) => group.name)).toEqual([
            'Vehicles',
            'Insurance',
            'Others',
        ]);
        expect(sentGroups(put)[0]!.notifications).toHaveLength(1);
    });

    it('makes another group the default', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        await userEvent.click(
            within(card('Policies')).getByRole('button', {
                name: 'Make default',
            }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(
            sentGroups(put).map((group) => [group.name, group.is_default]),
        ).toEqual([
            ['Vehicles', false],
            ['Policies', true],
            ['Others', false],
        ]);
    });

    it('deletes a group, never the default one', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Others')).getByRole('button', { name: 'Edit' }),
        );
        expect(
            within(card('Others')).queryByRole('button', {
                name: 'Delete group',
            }),
        ).not.toBeInTheDocument();

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        await userEvent.click(
            within(card('Policies')).getByRole('button', {
                name: 'Delete group',
            }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(sentGroups(put).map((group) => group.name)).toEqual([
            'Vehicles',
            'Others',
        ]);
    });

    it('tells how to add the deadlines of a group', async () => {
        renderPage(Notices, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'New group' }),
        );

        expect(
            screen.getByText(
                'Choose from the list, or start typing to search.',
            ),
        ).toBeVisible();
    });

    it('scrolls the page to the new group', async () => {
        // jsdom has no scrolling: the spy stands in for the page
        const scroll = vi.fn();
        Element.prototype.scrollIntoView = scroll;
        renderPage(Notices, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'New group' }),
        );

        const added = screen.getByRole('region', { name: 'New group' });
        expect(scroll).toHaveBeenCalledOnce();
        expect(scroll.mock.contexts[0]).toContain(added);
    });

    it('starts a new group with the notices empty', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Notices, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'New group' }),
        );
        await userEvent.type(
            screen.getByLabelText('Name of the group'),
            'Home',
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        const home = sentGroups(put)[3]!;
        expect(home).toMatchObject({
            id: null,
            name: 'Home',
            is_default: false,
            deadline_types: [],
            notifications: [],
        });
    });

    it('leaves the old group a kind chosen in another one', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        await userEvent.type(
            within(card('Policies')).getByLabelText('For which deadlines'),
            'Road',
        );
        await userEvent.click(
            await screen.findByRole('option', { name: /Road tax/ }),
        );
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        const sent = sentGroups(put);
        expect(sent[0]!.deadline_types).toEqual([
            'vehicles.inspection_due_date',
        ]);
        expect(sent[1]!.deadline_types).toContain('vehicles.road_tax_due_date');
    });

    it('shows what the server refused', async () => {
        vi.spyOn(router, 'put').mockImplementation(((
            _url: string,
            _data: unknown,
            options: { onError?: (errors: Record<string, string>) => void },
        ) => {
            options.onError?.({
                groups: 'There must always be a default group.',
            });
        }) as never);
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(
            await screen.findByText('There must always be a default group.'),
        ).toBeVisible();
    });

    it('opens the group full screen on the phone, with Cancel and Save', async () => {
        screenWidth(false);
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );

        const screenOfGroup = screen.getByTestId('group-screen');
        expect(
            within(screenOfGroup).getByLabelText('Name of the group'),
        ).toBeVisible();
        // The only Save is the one of the screen
        expect(screen.getAllByRole('button', { name: 'Save' })).toHaveLength(1);
        expect(
            within(screenOfGroup).getByRole('button', { name: 'Cancel' }),
        ).toBeVisible();

        await userEvent.click(
            within(screenOfGroup).getByRole('button', { name: 'Save' }),
        );
        expect(put).toHaveBeenCalledTimes(1);
    });

    it('drops the changes with Cancel on the phone', async () => {
        screenWidth(false);
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        const name = screen.getByLabelText('Name of the group');
        await userEvent.clear(name);
        await userEvent.type(name, 'Changed');
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(screen.queryByTestId('group-screen')).not.toBeInTheDocument();
        expect(card('Policies')).toBeVisible();
    });

    it('edits in place on the computer, without a screen of its own', async () => {
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );

        expect(screen.queryByTestId('group-screen')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Cancel' }),
        ).not.toBeInTheDocument();
    });

    it('undoes only the open group with Cancel on the phone', async () => {
        // A screen that can turn from a computer into a phone while the page is open
        let computer = true;
        const listeners: ((event: MediaQueryListEvent) => void)[] = [];
        vi.stubGlobal('matchMedia', (query: string) => ({
            get matches() {
                return (
                    query.includes('prefers-reduced-motion') ||
                    (computer && query.includes('min-width'))
                );
            },
            media: query,
            addEventListener: (
                _type: string,
                listener: (event: MediaQueryListEvent) => void,
            ) => listeners.push(listener),
            removeEventListener: () => {},
            addListener: () => {},
            removeListener: () => {},
        }));
        renderPage(Notices, props());

        await userEvent.click(
            within(card('Vehicles')).getByRole('button', { name: 'Edit' }),
        );
        const vehicles = within(card('Vehicles')).getByLabelText(
            'Name of the group',
        );
        await userEvent.clear(vehicles);
        await userEvent.type(vehicles, 'Cars');
        await settle();
        await userEvent.click(
            within(card('Cars')).getByRole('button', { name: 'Done editing' }),
        );

        computer = false;
        listeners.forEach((listener) => listener({} as MediaQueryListEvent));
        await settle();

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        const policies = screen.getByLabelText('Name of the group');
        await userEvent.clear(policies);
        await userEvent.type(policies, 'Changed');
        await settle();
        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(card('Cars')).toBeVisible();
        expect(card('Policies')).toBeVisible();
        expect(screen.queryByRole('region', { name: 'Changed' })).toBeNull();
    });

    it('removes a new group with Cancel on the phone', async () => {
        screenWidth(false);
        renderPage(Notices, props());

        await userEvent.click(
            screen.getByRole('button', { name: 'New group' }),
        );
        expect(screen.getByTestId('group-screen')).toBeVisible();
        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(screen.queryByTestId('group-screen')).not.toBeInTheDocument();
        expect(screen.getAllByRole('region')).toHaveLength(3);
    });
});

describe('Changes not saved', () => {
    it('has them once a notice is added, and none once saved', async () => {
        const view = renderPage(Notices, props());
        expect(hasChanges()).toBe(false);

        await userEvent.click(
            within(card('Policies')).getByRole('button', { name: 'Edit' }),
        );
        expect(hasChanges()).toBe(false);
        await userEvent.click(
            within(card('Policies')).getByRole('button', {
                name: 'Add a notice',
            }),
        );
        expect(hasChanges()).toBe(true);

        // The save redirects here with the groups as the server keeps them, a new list
        await view.rerender(props({ groups: structuredClone(groups) }));
        expect(hasChanges()).toBe(false);
    });
});
