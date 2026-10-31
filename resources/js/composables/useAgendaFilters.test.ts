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

describe('useAgendaFilters', () => {
    it('turns an area off and on again, saving each time', () => {
        const { put } = spies();
        const { filters, toggleArea } = useAgendaFilters(initial);

        toggleArea('work');
        expect(filters.value.hidden_areas).toEqual(['work']);
        expect(put).toHaveBeenLastCalledWith(
            '/agenda/filters',
            { months: 1, person_id: null, hidden_areas: ['work'] },
            { preserveState: true, preserveScroll: true },
        );

        toggleArea('work');
        expect(filters.value.hidden_areas).toEqual([]);
        expect(put).toHaveBeenLastCalledWith(
            '/agenda/filters',
            { months: 1, person_id: null, hidden_areas: [] },
            { preserveState: true, preserveScroll: true },
        );
    });

    it('chooses a person, or the whole family', () => {
        const { put } = spies();
        const { filters, choosePerson } = useAgendaFilters(initial);

        choosePerson(2);
        expect(filters.value.person_id).toBe(2);
        expect(put.mock.calls[0]?.[1]).toMatchObject({ person_id: 2 });

        choosePerson(null);
        expect(filters.value.person_id).toBeNull();
    });

    it('chooses the period and loads the entries of the new period', () => {
        const { put, get } = spies();
        const { filters, chooseMonths } = useAgendaFilters(initial);

        chooseMonths(3);

        expect(filters.value.months).toBe(3);
        expect(put.mock.calls[0]?.[1]).toMatchObject({ months: 3 });
        expect(get).toHaveBeenCalledWith(
            '/agenda',
            { months: 3 },
            { preserveState: true, preserveScroll: true },
        );
    });

    it('goes back to the defaults', () => {
        const { put, get } = spies();
        const { filters, reset } = useAgendaFilters({
            months: 6,
            person_id: 2,
            hidden_areas: ['work', 'home'],
        });

        reset();

        expect(filters.value).toEqual(initial);
        expect(put).toHaveBeenLastCalledWith('/agenda/filters', initial, {
            preserveState: true,
            preserveScroll: true,
        });
        expect(get).toHaveBeenCalledWith(
            '/agenda',
            { months: 1 },
            { preserveState: true, preserveScroll: true },
        );
    });

    it('does not load the entries again when the period stays the same', () => {
        const { get } = spies();

        useAgendaFilters(initial).reset();

        expect(get).not.toHaveBeenCalled();
    });
});
