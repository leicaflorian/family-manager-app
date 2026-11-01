import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { useAgendaFilters } from './useAgendaFilters';

const initial: App.Data.AgendaFiltersData = {
    months: 1,
    person_id: null,
    hidden_areas: [],
};

afterEach(() => {
    vi.restoreAllMocks();
});

/** Spies on the visits, which do not leave the test. */
function spies(): {
    put: ReturnType<typeof vi.spyOn>;
    get: ReturnType<typeof vi.spyOn>;
} {
    return {
        put: vi.spyOn(router, 'put').mockImplementation(() => {}),
        get: vi.spyOn(router, 'get').mockImplementation(() => {}),
    };
}

/**
 * Ends the save as Inertia does when the server answers.
 *
 * @param put - the spy on `router.put`
 */
function finishSave(put: ReturnType<typeof vi.spyOn>, index = 0): void {
    const options = put.mock.calls[index]?.[2] as { onFinish: () => void };

    options.onFinish();
}

describe('useAgendaFilters', () => {
    it('turns an area off and on again, saving each time', () => {
        const { put } = spies();
        const { filters, toggleArea } = useAgendaFilters(initial);

        toggleArea('work');
        expect(filters.value.hidden_areas).toEqual(['work']);
        expect(put).toHaveBeenLastCalledWith(
            '/agenda/filters',
            { months: 1, person_id: null, hidden_areas: ['work'] },
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
            }),
        );

        finishSave(put);
        toggleArea('work');
        expect(filters.value.hidden_areas).toEqual([]);
        expect(put).toHaveBeenLastCalledWith(
            '/agenda/filters',
            { months: 1, person_id: null, hidden_areas: [] },
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    });

    it('chooses a person, or the whole family', () => {
        const { put } = spies();
        const { filters, choosePerson } = useAgendaFilters(initial);

        choosePerson(2);
        expect(filters.value.person_id).toBe(2);
        expect(put.mock.calls[0]?.[1]).toMatchObject({ person_id: 2 });

        finishSave(put);
        choosePerson(null);
        expect(filters.value.person_id).toBeNull();
    });

    it('chooses the period with one visit to the agenda and no other request', () => {
        const { put, get } = spies();
        const { filters, chooseMonths } = useAgendaFilters(initial);

        chooseMonths(3);

        expect(filters.value.months).toBe(3);
        expect(put).not.toHaveBeenCalled();
        expect(get).toHaveBeenCalledWith(
            '/agenda',
            { months: 3 },
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    });

    it('goes back to the defaults, and loads the period only when the save is done', () => {
        const { put, get } = spies();
        const { filters, reset } = useAgendaFilters({
            months: 6,
            person_id: 2,
            hidden_areas: ['work', 'home'],
        });

        reset();

        expect(filters.value).toEqual(initial);
        expect(put.mock.calls[0]?.[1]).toEqual(initial);
        expect(get).not.toHaveBeenCalled();

        finishSave(put);

        expect(get).toHaveBeenCalledWith(
            '/agenda',
            { months: 1 },
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
            }),
        );
    });

    it('does not load the entries again when the period stays the same', () => {
        const { put, get } = spies();

        const { reset } = useAgendaFilters(initial);
        reset();
        finishSave(put);

        expect(get).not.toHaveBeenCalled();
    });

    it('never runs two visits at once: a period chosen during a save waits for it', () => {
        const { put, get } = spies();
        const { filters, toggleArea, chooseMonths } = useAgendaFilters(initial);

        toggleArea('work');
        chooseMonths(3);

        expect(filters.value).toEqual({
            months: 3,
            person_id: null,
            hidden_areas: ['work'],
        });
        expect(get).not.toHaveBeenCalled();

        finishSave(put);

        expect(get).toHaveBeenCalledTimes(1);
        expect(get).toHaveBeenCalledWith(
            '/agenda',
            { months: 3 },
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('saves a change made during a period visit after it, with the new period', () => {
        const { put, get } = spies();
        const { toggleArea, chooseMonths } = useAgendaFilters(initial);

        chooseMonths(3);
        toggleArea('work');

        expect(put).not.toHaveBeenCalled();

        const options = get.mock.calls[0]?.[2] as { onFinish: () => void };
        options.onFinish();

        expect(put).toHaveBeenCalledTimes(1);
        expect(put.mock.calls[0]?.[1]).toEqual({
            months: 3,
            person_id: null,
            hidden_areas: ['work'],
        });
    });
});
