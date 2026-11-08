import { router } from '@inertiajs/vue3';
import type { VisitOptions } from '@inertiajs/core';
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
import { areaSettings } from '@/test/fixtures';
import { renderPage, settle } from '@/test/page';
import Areas from './Areas.vue';

/** The default order of the areas, as `areaSettings` has them. */
const ORDER = areaSettings.areas.map((row) => row.area);

/** The rows of the list, in the order shown. */
function rows(): HTMLElement[] {
    return within(screen.getByRole('list', { name: 'Areas' })).getAllByRole(
        'listitem',
    );
}

/**
 * The row of an area.
 *
 * @param name - the name of the area
 */
function row(name: string): HTMLElement {
    return rows().find((item) => within(item).queryByText(name) !== null)!;
}

/**
 * Turns the switch *Show* of an area.
 *
 * @param name - the name of the area
 */
async function flip(name: string): Promise<void> {
    await userEvent.click(within(row(name)).getByLabelText('Show'));
    await settle();
}

/**
 * Chooses an entry of the menu of a row.
 *
 * @param name - the name of the area
 * @param entry - the entry of the menu
 */
async function move(name: string, entry: string): Promise<void> {
    await userEvent.click(
        screen.getByRole('button', { name: `More actions on ${name}` }),
    );
    await userEvent.click(await screen.findByRole('menuitem', { name: entry }));
}

describe('Settings, Areas', () => {
    /** The calls of `router.put`, with their options to finish them by hand. */
    let put: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        put = vi.spyOn(router, 'put').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    /**
     * The options of a call of `router.put`.
     *
     * @param index - the call
     */
    function options(index: number): VisitOptions {
        return put.mock.calls[index]![2] as VisitOptions;
    }

    it('shows the nine areas in the order of the family, with their colour, their cards and the switch', () => {
        renderPage(Areas, { settings: areaSettings });

        expect(
            screen.getByRole('heading', { name: 'Areas', level: 1 }),
        ).toBeInTheDocument();
        expect(rows()).toHaveLength(9);
        expect(rows()[0]).toHaveTextContent('Home');
        expect(rows()[8]).toHaveTextContent('Paperwork');
        expect(row('Work')).toHaveTextContent('2 cards');
        expect(row('Vehicles')).toHaveTextContent('1 card');
        expect(within(row('Work')).getByTestId('area-dot').className).toContain(
            'bg-work',
        );
        expect(within(row('Work')).getByLabelText('Show')).toBeChecked();
    });

    it('hides an area at once, says what happens to its cards and saves the whole choice', async () => {
        renderPage(Areas, { settings: areaSettings });

        await flip('Work');

        expect(put).toHaveBeenCalledTimes(1);
        expect(put).toHaveBeenCalledWith(
            '/settings/areas',
            { order: ORDER, hidden: ['work'] },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(within(row('Work')).getByLabelText('Show')).not.toBeChecked();
        expect(row('Work')).toHaveTextContent('Hidden');
        expect(row('Work')).toHaveTextContent(
            'Has 2 cards: they stay, with their deadlines.',
        );
    });

    it('writes the singular for an area with one card', async () => {
        renderPage(Areas, { settings: areaSettings });

        await flip('Vehicles');

        expect(row('Vehicles')).toHaveTextContent(
            'Has 1 card: it stays, with its deadlines.',
        );
    });

    it('says nothing of cards for an empty area', async () => {
        renderPage(Areas, { settings: areaSettings });

        await flip('School and activities');

        expect(row('School and activities')).toHaveTextContent('Hidden');
        expect(row('School and activities')).not.toHaveTextContent('Has');
    });

    it('moves an area down from the menu of its row', async () => {
        renderPage(Areas, { settings: areaSettings });

        await move('Home', 'Move down');

        expect(put).toHaveBeenCalledWith(
            '/settings/areas',
            {
                order: ['vehicles', 'home', ...ORDER.slice(2)],
                hidden: [],
            },
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(rows()[0]).toHaveTextContent('Vehicles');
        expect(rows()[1]).toHaveTextContent('Home');
    });

    it('offers Move up only below the first and Move down only above the last', async () => {
        renderPage(Areas, { settings: areaSettings });

        await userEvent.click(
            screen.getByRole('button', { name: 'More actions on Home' }),
        );
        const menu = await screen.findByRole('menu');

        expect(
            within(menu)
                .getAllByRole('menuitem')
                .map((item) => item.textContent?.trim()),
        ).toEqual(['Move down']);
    });

    it('sends two changes one after the other, each with the choice of when it starts', async () => {
        renderPage(Areas, { settings: areaSettings });

        await flip('Work');
        await move('Home', 'Move down');

        expect(put).toHaveBeenCalledTimes(1);

        options(0).onFinish?.({} as never);

        expect(put).toHaveBeenCalledTimes(2);
        expect(put.mock.calls[1]![1]).toEqual({
            order: ['vehicles', 'home', ...ORDER.slice(2)],
            hidden: ['work'],
        });
    });

    it('puts the list back as the server has it when the save fails', async () => {
        renderPage(Areas, { settings: areaSettings });

        await move('Home', 'Move down');
        await flip('Work');
        options(0).onError?.({});
        options(0).onFinish?.({} as never);
        options(1).onError?.({});
        options(1).onFinish?.({} as never);
        await settle();

        expect(rows()[0]).toHaveTextContent('Home');
        expect(within(row('Work')).getByLabelText('Show')).toBeChecked();
        expect(row('Work')).not.toHaveTextContent('Hidden');
    });

    it('goes back to the default order', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        renderPage(Areas, { settings: areaSettings });

        await userEvent.click(
            screen.getByRole('button', { name: 'Back to the default order' }),
        );

        expect(post).toHaveBeenCalledWith(
            '/settings/areas/reset',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows the order the server sends after going back to the default', async () => {
        const hidden: App.Data.AreaSettingsData = {
            ...areaSettings,
            areas: areaSettings.areas.map((area) => ({
                ...area,
                hidden: area.area === 'work',
            })),
        };
        const view = renderPage(Areas, { settings: hidden });
        vi.spyOn(router, 'post').mockImplementation((_url, _data, opts) => {
            void view
                .rerender({ settings: structuredClone(areaSettings) })
                .then(() => {
                    opts?.onSuccess?.({} as never);
                    opts?.onFinish?.({} as never);
                });
        });

        expect(row('Work')).toHaveTextContent('Hidden');
        await userEvent.click(
            screen.getByRole('button', { name: 'Back to the default order' }),
        );
        await settle();

        expect(row('Work')).not.toHaveTextContent('Hidden');
        expect(within(row('Work')).getByLabelText('Show')).toBeChecked();
    });

    it('shows a member the order without handles, switches, menus or the way back to the default', () => {
        renderPage(Areas, {
            settings: {
                ...areaSettings,
                can_change: false,
                areas: areaSettings.areas.map((area) => ({
                    ...area,
                    hidden: area.area === 'work',
                })),
            },
        });

        expect(rows()).toHaveLength(9);
        expect(screen.queryByLabelText('Show')).toBeNull();
        expect(screen.queryByRole('img', { name: 'Drag to move' })).toBeNull();
        expect(
            screen.queryByRole('button', { name: /More actions/ }),
        ).toBeNull();
        expect(
            screen.queryByRole('button', { name: 'Back to the default order' }),
        ).toBeNull();
        expect(row('Work')).toHaveTextContent('Hidden');
    });
});
