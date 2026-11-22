import { router } from '@inertiajs/vue3';
import { screen, within } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { renderPage, screenWidth } from '@/test/page';
import Gallery from './Gallery.vue';

vi.mock('@inertiajs/vue3', async (original) => ({
    ...(await original<typeof import('@inertiajs/vue3')>()),
    Head: { render: () => null },
    usePage: () => ({
        url: '/documents/gallery',
        // A member's role: what an admin may do comes from canManageCategories, not from here.
        props: { auth: { user: { name: 'Laura', role: 'member' } } },
    }),
}));

/**
 * A drawing as the server sends it.
 *
 * @param id - the id
 * @param extra - what differs
 * @returns the drawing
 */
function drawing(
    id: number,
    extra: Partial<App.Data.DrawingData> = {},
): App.Data.DrawingData {
    return {
        id,
        title: `Drawing ${id}`,
        notes: null,
        person_id: 1,
        person_name: 'Giulia',
        age: 10,
        uploaded_on: '2026-11-14',
        pages: null,
        is_favorite: false,
        image_url: `/files/${id}/image`,
        file_url: `/files/${id}`,
        download_url: `/files/${id}/download`,
        document_url: `/documents/${id}`,
        can_favorite: true,
        ...extra,
    };
}

const years = [
    { year: 2026, count: 2, drawings: [drawing(1), drawing(2)] },
    { year: 2025, count: 1, drawings: [drawing(3)] },
];

const base = {
    years,
    people: [{ id: 1, name: 'Giulia' }],
    filters: { filter: 'all', person: null },
    selected: null,
    total: 3,
    canAdd: true,
    canManageCategories: true,
    hasGallery: true,
    addOptions: { people: [{ id: 1, name: 'Giulia' }] },
};

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('Gallery', () => {
    it('shows the years with their counts', () => {
        renderPage(Gallery, base);

        const year = screen.getByRole('region', { name: '2026' });
        expect(within(year).getByText('2 drawings')).toBeInTheDocument();
        expect(
            within(screen.getByRole('region', { name: '2025' })).getByText(
                '1 drawing',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('3 drawings and crafts of Giulia'),
        ).toBeInTheDocument();
    });

    it('the person filter appears only with more than one person', () => {
        const one = renderPage(Gallery, base);
        expect(screen.queryByRole('link', { name: 'Marco' })).toBeNull();
        one.unmount();

        renderPage(Gallery, {
            ...base,
            people: [
                { id: 1, name: 'Giulia' },
                { id: 2, name: 'Marco' },
            ],
        });
        expect(screen.getByRole('link', { name: 'Marco' })).toHaveAttribute(
            'href',
            '/documents/gallery?person=2',
        );
    });

    it('a gallery filtered by its only child shows her chip, which clears the filter', () => {
        vi.spyOn(router, 'visit').mockImplementation(() => {});
        renderPage(Gallery, { ...base, filters: { filter: 'all', person: 1 } });

        const chip = screen.getByRole('link', { name: 'Giulia' });
        expect(chip).toHaveAttribute('href', '/documents/gallery');
        expect(
            screen.getByRole('link', { name: 'Favourites' }),
        ).toHaveAttribute(
            'href',
            '/documents/gallery?filter=favorites&person=1',
        );
    });

    it('a gallery filtered by a child without drawings can still be cleared from All', () => {
        renderPage(Gallery, {
            ...base,
            years: [],
            total: 0,
            filters: { filter: 'favorites', person: 7 },
        });

        expect(screen.getByRole('link', { name: 'All' })).toHaveAttribute(
            'href',
            '/documents/gallery',
        );
    });

    it('says whose the drawings are only when every drawing is of that child', () => {
        renderPage(Gallery, {
            ...base,
            years: [
                {
                    year: 2026,
                    count: 2,
                    drawings: [
                        drawing(1),
                        drawing(2, { person_id: null, person_name: null }),
                    ],
                },
            ],
            total: 2,
        });

        expect(screen.getByText('2 drawings and crafts')).toBeInTheDocument();
        expect(screen.queryByText(/of Giulia/)).toBeNull();
    });

    it('Add starts on the child the gallery is filtered by', async () => {
        vi.spyOn(router, 'visit').mockImplementation(() => {});
        const two = [
            { id: 1, name: 'Giulia' },
            { id: 2, name: 'Marco' },
        ];
        renderPage(Gallery, {
            ...base,
            people: two,
            addOptions: { people: two },
            filters: { filter: 'all', person: 2 },
        });

        await userEvent.click(screen.getByRole('button', { name: 'Add' }));
        await screen.findByRole('dialog', { name: 'Add a drawing' });

        expect(screen.getByLabelText(/Title/)).toHaveAttribute(
            'placeholder',
            'Drawing by Marco',
        );
    });

    it('opening a tile asks only selected', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Gallery, base);

        await userEvent.click(
            screen.getByRole('button', { name: 'Drawing 2' }),
        );

        expect(get).toHaveBeenCalledWith(
            '/documents/gallery?drawing=2',
            {},
            expect.objectContaining({
                only: ['selected'],
                preserveState: true,
                preserveScroll: true,
            }),
        );
        expect(get.mock.calls[0]![2]?.replace).not.toBe(true);
    });

    it('next and previous follow the order of the gallery', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        renderPage(Gallery, { ...base, selected: drawing(2) });

        await userEvent.click(
            screen.getByRole('button', { name: 'Next drawing' }),
        );
        expect(get.mock.calls[0]![0]).toBe('/documents/gallery?drawing=3');
        // Back closes the viewer instead of stepping back through the drawings.
        expect(get.mock.calls[0]![2]).toEqual(
            expect.objectContaining({ replace: true }),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Previous drawing' }),
        );
        expect(get.mock.calls[1]![0]).toBe('/documents/gallery?drawing=1');
    });

    it('does not go past the first and the last drawing', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => {});
        const first = renderPage(Gallery, { ...base, selected: drawing(1) });
        expect(
            screen.getByRole('button', { name: 'Previous drawing' }),
        ).toBeDisabled();
        await userEvent.click(
            screen.getByRole('button', { name: 'Previous drawing' }),
        );
        first.unmount();

        renderPage(Gallery, { ...base, selected: drawing(3) });
        expect(
            screen.getByRole('button', { name: 'Next drawing' }),
        ).toBeDisabled();
        await userEvent.click(
            screen.getByRole('button', { name: 'Next drawing' }),
        );

        expect(get).not.toHaveBeenCalled();
    });

    it('a drawing that left the list shows no place and no working arrows', () => {
        renderPage(Gallery, { ...base, selected: drawing(9) });

        expect(screen.queryByText(/ of 3/)).toBeNull();
        expect(
            screen.getByRole('button', { name: 'Next drawing' }),
        ).toBeDisabled();
    });

    it('shows the viewer beside the grid on the computer', () => {
        screenWidth(true);
        renderPage(Gallery, { ...base, selected: drawing(2) });

        expect(screen.queryByRole('dialog')).toBeNull();
        expect(screen.getByText('2 of 3')).toBeInTheDocument();
    });

    it('the heart sends a put and then a delete', async () => {
        const put = vi.spyOn(router, 'put').mockImplementation(() => {});
        const del = vi.spyOn(router, 'delete').mockImplementation(() => {});
        const view = renderPage(Gallery, { ...base, selected: drawing(2) });

        await userEvent.click(
            screen.getByRole('button', { name: 'Add to favourites' }),
        );
        expect(put).toHaveBeenCalledWith(
            '/documents/2/favorite',
            {},
            expect.objectContaining({ only: ['years', 'selected', 'total'] }),
        );

        await view.rerender({ selected: drawing(2, { is_favorite: true }) });
        await userEvent.click(
            screen.getByRole('button', { name: 'Remove from favourites' }),
        );
        expect(del).toHaveBeenCalledWith(
            '/documents/2/favorite',
            expect.objectContaining({ only: ['years', 'selected', 'total'] }),
        );
    });

    it('the empty gallery shows Add', () => {
        renderPage(Gallery, { ...base, years: [], total: 0 });

        expect(screen.getByText('No drawings yet')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Add a drawing' }),
        ).toBeInTheDocument();
    });

    it('a page without gallery category says to choose one in Categories (who manages them) or to ask (the others)', () => {
        const admin = renderPage(Gallery, {
            ...base,
            years: [],
            total: 0,
            hasGallery: false,
            canAdd: false,
        });
        expect(
            screen.getByRole('link', { name: 'Go to Categories' }),
        ).toBeInTheDocument();
        admin.unmount();

        renderPage(Gallery, {
            ...base,
            years: [],
            total: 0,
            hasGallery: false,
            canAdd: false,
            canManageCategories: false,
        });
        expect(
            screen.queryByRole('link', { name: 'Go to Categories' }),
        ).toBeNull();
        expect(
            screen.getByText(
                'Ask an admin of the family to choose the category of the gallery.',
            ),
        ).toBeInTheDocument();
    });
});
