import { fireEvent, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vite-plus/test';
import { renderPage } from '@/test/page';
import ToSortSelectionBar from './ToSortSelectionBar.vue';

describe('ToSortSelectionBar', () => {
    it('shows how many files are chosen and the button that checks them', () => {
        renderPage(ToSortSelectionBar, { count: 3, canDelete: true });

        expect(screen.getByText('3 chosen')).toBeTruthy();
        expect(
            screen.getByRole('button', { name: 'Check 3 files' }),
        ).toBeTruthy();
    });

    it('emits check when Check is tapped', async () => {
        const checks: number[] = [];
        renderPage(ToSortSelectionBar, {
            count: 3,
            canDelete: true,
            onCheck: () => checks.push(1),
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Check 3 files' }),
        );

        expect(checks).toHaveLength(1);
    });

    it('emits delete from the bin when the person can delete', async () => {
        const deletes: number[] = [];
        renderPage(ToSortSelectionBar, {
            count: 3,
            canDelete: true,
            onDelete: () => deletes.push(1),
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Delete the 3 chosen files' }),
        );

        expect(deletes).toHaveLength(1);
    });

    it('has no bin when the person cannot delete', () => {
        renderPage(ToSortSelectionBar, { count: 3, canDelete: false });

        expect(screen.queryByRole('button', { name: /Delete/ })).toBeNull();
    });

    it('speaks in the singular with one file', () => {
        renderPage(ToSortSelectionBar, { count: 1, canDelete: true });

        expect(screen.getByText('1 chosen')).toBeTruthy();
        expect(
            screen.getByRole('button', { name: 'Check 1 file' }),
        ).toBeTruthy();
        expect(
            screen.getByRole('button', { name: 'Delete the chosen file' }),
        ).toBeTruthy();
    });

    it('cannot be tapped while processing', () => {
        renderPage(ToSortSelectionBar, {
            count: 2,
            canDelete: true,
            processing: true,
        });

        expect(
            screen
                .getByRole('button', { name: 'Check 2 files' })
                .hasAttribute('disabled'),
        ).toBe(true);
    });
});
