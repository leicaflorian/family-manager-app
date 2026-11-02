import { render } from '@testing-library/vue';
import { afterEach, describe, expect, it } from 'vite-plus/test';
import { defineComponent, nextTick, reactive } from 'vue';
import { answerLeave, hasChanges, leaveQuestion } from '@/lib/unsavedChanges';
import { useUnsavedChanges } from './useUnsavedChanges';

/**
 * Mounts a form with a title, and gives back its values and what the
 * composable gives it.
 */
function mountForm(): {
    values: { title: string };
    changes: ReturnType<typeof useUnsavedChanges>;
    unmount: () => void;
} {
    const values = reactive({ title: 'Gas' });
    let changes!: ReturnType<typeof useUnsavedChanges>;

    const view = render(
        defineComponent({
            setup() {
                changes = useUnsavedChanges(() => values);

                return (): null => null;
            },
        }),
    );

    return { values, changes, unmount: () => view.unmount() };
}

describe('useUnsavedChanges', () => {
    afterEach(() => {
        if (leaveQuestion.open) {
            answerLeave(false);
        }
    });

    it('has changes when a value is not the one the form opened with', async () => {
        const { values, changes, unmount } = mountForm();
        await nextTick();

        expect(changes.dirty.value).toBe(false);
        expect(hasChanges()).toBe(false);

        values.title = 'Luce';

        expect(changes.dirty.value).toBe(true);
        expect(hasChanges()).toBe(true);
        unmount();
    });

    it('has none after saving: the values saved are the new start', async () => {
        const { values, changes, unmount } = mountForm();
        await nextTick();

        values.title = 'Luce';
        changes.reset();

        expect(changes.dirty.value).toBe(false);
        unmount();
    });

    it('leaves the app when the form closes', async () => {
        const { values, unmount } = mountForm();
        await nextTick();
        values.title = 'Luce';

        unmount();

        expect(hasChanges()).toBe(false);
    });

    it('closes a panel without asking when nothing changed', async () => {
        const { changes, unmount } = mountForm();
        await nextTick();

        expect(await changes.confirmClose()).toBe(true);
        expect(leaveQuestion.open).toBe(false);
        unmount();
    });

    it('asks before closing a panel with changes, and stays if the person says so', async () => {
        const { values, changes, unmount } = mountForm();
        await nextTick();
        values.title = 'Luce';

        const close = changes.confirmClose();
        expect(leaveQuestion.open).toBe(true);
        answerLeave(false);

        expect(await close).toBe(false);
        unmount();
    });

    it('lets a panel close when the person leaves without saving', async () => {
        const { values, changes, unmount } = mountForm();
        await nextTick();
        values.title = 'Luce';

        const close = changes.confirmClose();
        answerLeave(true);

        expect(await close).toBe(true);
        // The changes are thrown away: the page does not ask again
        expect(hasChanges()).toBe(false);
        unmount();
    });

    it('has no changes while its panel is closed', async () => {
        const values = reactive({ title: 'Gas' });
        const panel = reactive({ open: true });
        let changes!: ReturnType<typeof useUnsavedChanges>;
        const view = render(
            defineComponent({
                setup() {
                    changes = useUnsavedChanges(() => values, {
                        active: () => panel.open,
                    });

                    return (): null => null;
                },
            }),
        );
        await nextTick();
        values.title = 'Luce';

        panel.open = false;

        expect(changes.dirty.value).toBe(false);
        expect(hasChanges()).toBe(false);
        view.unmount();
    });
});
