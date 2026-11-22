import { fireEvent, render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import FmDetailColumn from './FmDetailColumn.vue';

const base = {
    label: 'Busta paga',
    position: 1 as number | null,
    total: 4,
    previousLabel: 'Previous document',
    nextLabel: 'Next document',
};

describe('FmDetailColumn', () => {
    it('names itself and counts the rows', () => {
        render(FmDetailColumn, {
            props: base,
            slots: { default: '<p>Body</p>' },
        });

        expect(
            screen.getByRole('complementary', { name: 'Busta paga' }),
        ).toBeInTheDocument();
        expect(screen.getByText('1 of 4')).toBeInTheDocument();
        expect(screen.getByText('Body')).toBeInTheDocument();
    });

    it('says a dash when the row is not in the list', () => {
        render(FmDetailColumn, { props: { ...base, position: null } });

        expect(screen.getByText('– of 4')).toBeInTheDocument();
    });

    it('has no previous on the first row and no next on the last', () => {
        const first = render(FmDetailColumn, { props: base });
        expect(
            screen.getByRole('button', { name: 'Previous document' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Next document' }),
        ).toBeEnabled();
        first.unmount();

        render(FmDetailColumn, { props: { ...base, position: 4 } });
        expect(
            screen.getByRole('button', { name: 'Next document' }),
        ).toBeDisabled();
    });

    it('asks for the neighbours', async () => {
        const view = render(FmDetailColumn, {
            props: { ...base, position: 2 },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Previous document' }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Next document' }),
        );

        expect(view.emitted().previous).toHaveLength(1);
        expect(view.emitted().next).toHaveLength(1);
    });

    it('closes with the × and with Escape', async () => {
        const view = render(FmDetailColumn, { props: base });

        await fireEvent.click(screen.getByRole('button', { name: 'Close' }));
        await fireEvent.keyDown(document, { key: 'Escape' });
        expect(view.emitted().close).toHaveLength(2);

        const event = new KeyboardEvent('keydown', {
            key: 'Escape',
            cancelable: true,
        });
        event.preventDefault();
        document.dispatchEvent(event);
        expect(view.emitted().close).toHaveLength(2);
    });

    it('puts the actions before the ×', () => {
        render(FmDetailColumn, {
            props: base,
            slots: { actions: '<button type="button">More</button>' },
        });

        expect(
            screen.getByRole('button', { name: 'More' }),
        ).toBeInTheDocument();
    });
});
