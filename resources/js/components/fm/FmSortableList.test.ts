import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { beforeAll, describe, expect, it } from 'vite-plus/test';
import FmSortableList from './FmSortableList.vue';

const items = [
    { key: 'home', name: 'Home' },
    { key: 'health', name: 'Health' },
    { key: 'money', name: 'Money' },
];

const slot = `
    <template #item="{ item, moveUp, moveDown }">
        <span>{{ item.name }}</span>
        <button type="button" @click="moveUp">Up {{ item.name }}</button>
        <button type="button" @click="moveDown">Down {{ item.name }}</button>
    </template>`;

/** Renders the list with the row of the tests. */
function show(props: { disabled?: boolean } = {}) {
    return render(FmSortableList, {
        props: { items, label: 'Areas', ...props },
        slots: { item: slot },
    });
}

describe('FmSortableList', () => {
    beforeAll(() => {
        // jsdom has no `elementFromPoint`, which the library reads on every pointer press in a row
        document.elementFromPoint = () => null;
    });

    it('shows a named list with a row and a handle for each item', () => {
        show();

        expect(screen.getByRole('list', { name: 'Areas' })).toBeInTheDocument();
        expect(screen.getAllByRole('listitem')).toHaveLength(3);
        expect(screen.getAllByLabelText('Drag to move')).toHaveLength(3);
        expect(screen.getByText('Health')).toBeInTheDocument();
    });

    it('has no handles when it is disabled', () => {
        show({ disabled: true });

        expect(screen.queryByLabelText('Drag to move')).not.toBeInTheDocument();
        expect(screen.getByText('Home')).toBeInTheDocument();
    });

    it('moves an item down by swapping it with the next one', async () => {
        const view = show();

        await userEvent.click(
            screen.getByRole('button', { name: 'Down Home' }),
        );

        expect(view.emitted().sort).toEqual([[['health', 'home', 'money']]]);
    });

    it('moves an item up by swapping it with the one before', async () => {
        const view = show();

        await userEvent.click(screen.getByRole('button', { name: 'Up Money' }));

        expect(view.emitted().sort).toEqual([[['home', 'money', 'health']]]);
    });

    it('does nothing when the first item moves up or the last one moves down', async () => {
        const view = show();

        await userEvent.click(screen.getByRole('button', { name: 'Up Home' }));
        await userEvent.click(
            screen.getByRole('button', { name: 'Down Money' }),
        );

        expect(view.emitted().sort).toBeUndefined();
    });

    it('shows the new order when the parent sends the items again', async () => {
        const view = show();

        await view.rerender({ items: [items[2]!, items[0]!, items[1]!] });

        const names = screen
            .getAllByRole('listitem')
            .map((row) => row.textContent);

        expect(names[0]).toContain('Money');
        expect(names[2]).toContain('Health');
    });

    it('moves from the new order after the items change', async () => {
        const view = show();

        await view.rerender({ items: [items[2]!, items[0]!, items[1]!] });
        await userEvent.click(
            screen.getByRole('button', { name: 'Down Money' }),
        );

        expect(view.emitted().sort).toEqual([[['home', 'money', 'health']]]);
    });
});
