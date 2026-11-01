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
 * (the page stays as it is) and applied at once; the period is a visit to the
 * Agenda, because the server calculates it and remembers it there.
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
    function save(change: Partial<App.Data.AgendaFiltersData>): void {
        filters.value = { ...filters.value, ...change };
        router.put(saveFilters().url, { ...filters.value }, stay);
    }

    /**
     * Loads the entries of a period. The server remembers the period on this
     * visit, so no other request is needed.
     *
     * @param months - 1, 3, 6 or 12
     */
    function visit(months: number): void {
        filters.value = { ...filters.value, months };
        router.get(agenda().url, { months }, stay);
    }

    return {
        filters,
        toggleArea: (area) =>
            save({
                hidden_areas: filters.value.hidden_areas.includes(area)
                    ? filters.value.hidden_areas.filter((a) => a !== area)
                    : [...filters.value.hidden_areas, area],
            }),
        choosePerson: (id) => save({ person_id: id }),
        chooseMonths: visit,
        reset: () => {
            const months = filters.value.months;

            filters.value = { ...defaults, hidden_areas: [] };
            router.put(
                saveFilters().url,
                { ...filters.value },
                {
                    ...stay,
                    // One visit at a time: Inertia drops a visit still on its way when another starts.
                    onSuccess: () => {
                        if (months !== defaults.months) {
                            router.get(
                                agenda().url,
                                { months: defaults.months },
                                stay,
                            );
                        }
                    },
                },
            );
        },
    };
}
