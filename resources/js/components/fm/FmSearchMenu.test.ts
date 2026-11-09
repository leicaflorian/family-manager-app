import { render, screen } from '@testing-library/vue';
import { nextTick, ref } from 'vue';
import { describe, expect, it, vi } from 'vite-plus/test';
import { router } from '@inertiajs/vue3';
import FmSearchMenu from './FmSearchMenu.vue';

/**
 * A result as the server sends it.
 *
 * @param key - its key
 * @param group - its group
 * @param title - its title
 * @returns a search result
 */
function row(
    key: string,
    group: App.Enums.SearchGroup,
    title: string,
): App.Data.Search.SearchResultData {
    return {
        key,
        group,
        title,
        subtitle: null,
        date: null,
        amount: null,
        href: `/${key}`,
        area: null,
        icon: 'file-text',
        tone: 'neutral',
        flag: null,
        due_in: null,
    };
}

const results: App.Data.Search.SearchResultsData = {
    query: 'Pan',
    type: null,
    total: 10,
    groups: [
        {
            group: 'cards',
            label: 'Cards',
            count: 3,
            results: [
                row('c1', 'cards', 'Fiat Panda'),
                row('c2', 'cards', 'Pan 2'),
            ],
        },
        {
            group: 'documents',
            label: 'Documents',
            count: 3,
            results: [row('d1', 'documents', 'Libretto Panda')],
        },
        {
            group: 'movements',
            label: 'Expenses and income',
            count: 4,
            results: [row('m1', 'movements', 'Tagliando Panda')],
        },
    ],
};

describe('FmSearchMenu', () => {
    it('shows the groups with their name and the link to all the results', () => {
        render(FmSearchMenu, { props: { results, query: 'Pan' } });

        expect(screen.getByRole('listbox')).toBeInTheDocument();
        expect(
            screen
                .getAllByRole('group')
                .map((g) => g.getAttribute('aria-label')),
        ).toEqual(['Cards', 'Documents', 'Expenses and income']);
        expect(screen.getAllByRole('option')).toHaveLength(4);
        expect(
            screen
                .getByRole('listbox')
                .contains(screen.getByRole('link', { name: /See all/ })),
        ).toBe(false);
        expect(
            screen.getByRole('link', { name: /See all 10 results/ }),
        ).toHaveAttribute('href', '/search?q=Pan');
    });

    it('moves the choice with move() and opens it with open()', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        const menu = ref<InstanceType<typeof FmSearchMenu> | null>(null);
        render({
            components: { FmSearchMenu },
            setup: () => ({ menu, results }),
            template:
                '<FmSearchMenu ref="menu" :results="results" query="Pan" />',
        });

        expect(menu.value?.open()).toBe(false);
        expect(menu.value?.activeId).toBeUndefined();
        expect(
            document.querySelectorAll('[aria-selected="true"]'),
        ).toHaveLength(0);

        menu.value?.move(1);
        menu.value?.move(1);
        await nextTick();

        const options = screen.getAllByRole('option');
        expect(options[1]).toHaveAttribute('aria-selected', 'true');
        expect(options[1]).toHaveAttribute('id', 'search-option-c2');
        expect(menu.value?.activeId).toBe('search-option-c2');
        expect(options[0]).toHaveAttribute('aria-selected', 'false');

        expect(menu.value?.open()).toBe(true);
        expect(visit).toHaveBeenCalledWith('/c2');

        menu.value?.move(-1);
        await nextTick();
        expect(screen.getAllByRole('option')[0]).toHaveAttribute(
            'aria-selected',
            'true',
        );
    });
});
