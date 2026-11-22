import userEvent from '@testing-library/user-event';
import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmDrawingTile from './FmDrawingTile.vue';

/** A drawing as the server sends it, with what the test changes. */
function drawingTileFixture(
    changes: Partial<App.Data.DrawingData> = {},
): App.Data.DrawingData {
    return {
        id: 7,
        title: 'The dragon',
        notes: null,
        person_id: 1,
        person_name: 'Giulia',
        age: 10,
        uploaded_on: '2026-11-14',
        pages: 1,
        is_favorite: false,
        image_url: '/drawings/7/image',
        file_url: '/files/7',
        download_url: '/files/7/download',
        document_url: '/documents/7',
        can_favorite: true,
        ...changes,
    };
}

describe('FmDrawingTile', () => {
    it('shows the image with the title as alt', () => {
        render(FmDrawingTile, { props: { drawing: drawingTileFixture() } });

        const image = screen.getByRole('img', { name: 'The dragon' });
        expect(image).toHaveAttribute('src', '/drawings/7/image');
        expect(image).toHaveAttribute('loading', 'lazy');
    });

    it('shows the pages badge only above one page', async () => {
        const view = render(FmDrawingTile, {
            props: { drawing: drawingTileFixture({ pages: 1 }) },
        });
        expect(screen.queryByText('2 pages')).not.toBeInTheDocument();

        await view.rerender({ drawing: drawingTileFixture({ pages: 2 }) });

        expect(screen.getByText('2 pages')).toBeInTheDocument();
    });

    it('emits open on click', async () => {
        const view = render(FmDrawingTile, {
            props: { drawing: drawingTileFixture() },
        });

        await userEvent.click(screen.getByRole('button'));

        expect(view.emitted().open).toEqual([[7]]);
    });

    it('says when it is a favourite and when it is the open one', () => {
        render(FmDrawingTile, {
            props: {
                drawing: drawingTileFixture({ is_favorite: true }),
                selected: true,
            },
        });

        expect(screen.getByText('Favourite')).toBeInTheDocument();
        expect(screen.getByRole('button')).toHaveAttribute(
            'aria-current',
            'true',
        );
    });
});
