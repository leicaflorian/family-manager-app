import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { Ref } from 'vue';
import { agenda } from '@/routes';
import { filters as saveFilters } from '@/routes/agenda';

/** What `useAgendaFilters` gives to the page. */
export type UseAgendaFilters = {
    /** The filters in use: period, person and the areas turned off. */
    filters: Ref<App.Data.AgendaFiltersData>;
    /** Turns an area off, or on if it was off. */
    toggleArea: (area: string) => void;
    /** Chooses a person, or the whole family with `null`. */
    choosePerson: (id: number | null) => void;
    /** Chooses the period (1, 3, 6 or 12 months) and loads its entries. */
    chooseMonths: (months: number) => void;
    /** Goes back to the defaults. */
    reset: () => void;
};

/** The filters of a new account: one month, the whole family, every area. */
const defaults: App.Data.AgendaFiltersData = {
    months: 1,
    person_id: null,
    hidden_areas: [],
};

/** Visits that leave the page where it is. */
const stay = { preserveState: true, preserveScroll: true };

/**
 * The filters of the Agenda. Every change is saved in the account with Inertia
 * (the page stays as it is) and applied at once; the period also loads the
 * entries of the new period, because the server calculates it.
 *
 * @param initial - the filters saved, as the server sends them
 * @returns the filters and the functions that change them
 */
export function useAgendaFilters(
    initial: App.Data.AgendaFiltersData,
): UseAgendaFilters {
    const filters = ref<App.Data.AgendaFiltersData>({
        ...initial,
        hidden_areas: [...initial.hidden_areas],
    });

    /**
     * Applies a change and saves the filters.
     *
     * @param change - the fields that change
     */
    function update(change: Partial<App.Data.AgendaFiltersData>): void {
        const months = filters.value.months;

        filters.value = { ...filters.value, ...change };
        router.put(saveFilters().url, { ...filters.value }, stay);

        if (filters.value.months !== months) {
            router.get(agenda().url, { months: filters.value.months }, stay);
        }
    }

    return {
        filters,
        toggleArea: (area) =>
            update({
                hidden_areas: filters.value.hidden_areas.includes(area)
                    ? filters.value.hidden_areas.filter((a) => a !== area)
                    : [...filters.value.hidden_areas, area],
            }),
        choosePerson: (id) => update({ person_id: id }),
        chooseMonths: (months) => update({ months }),
        reset: () => update({ ...defaults, hidden_areas: [] }),
    };
}
