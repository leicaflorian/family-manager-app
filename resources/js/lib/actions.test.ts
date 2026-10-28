import { describe, expect, it } from 'vite-plus/test';
import { visibleActionCount } from './actions';

/** Three buttons of 100 px, the ⋯ of 44 px and the gap of 10 px. */
const sizes = { widths: [100, 100, 100], menuWidth: 44, gap: 10 };

describe('visibleActionCount', () => {
    it('shows every button when they fit and nothing goes in the menu', () => {
        expect(
            visibleActionCount({ ...sizes, available: 320, menuOnly: false }),
        ).toBe(3);
    });

    it('shows at most three buttons, the others go in the menu', () => {
        expect(
            visibleActionCount({
                ...sizes,
                widths: [100, 100, 100, 100, 100],
                available: 2000,
                menuOnly: false,
            }),
        ).toBe(3);
    });

    it('leaves room for the ⋯ when some actions are only in the menu', () => {
        // 3 buttons + 2 gaps = 320, with the ⋯ = 374
        expect(
            visibleActionCount({ ...sizes, available: 340, menuOnly: true }),
        ).toBe(2);
        expect(
            visibleActionCount({ ...sizes, available: 374, menuOnly: true }),
        ).toBe(3);
    });

    it('drops the last buttons one at a time as the room shrinks', () => {
        // 2 buttons + ⋯ = 100 + 10 + 100 + 10 + 44 = 264
        expect(
            visibleActionCount({ ...sizes, available: 264, menuOnly: false }),
        ).toBe(2);
        expect(
            visibleActionCount({ ...sizes, available: 263, menuOnly: false }),
        ).toBe(1);
    });

    it('hides every button when only the ⋯ fits', () => {
        expect(
            visibleActionCount({ ...sizes, available: 60, menuOnly: false }),
        ).toBe(0);
        expect(
            visibleActionCount({ ...sizes, available: 0, menuOnly: false }),
        ).toBe(0);
    });

    it('shows the buttons that fit without the ⋯ when there is no menu', () => {
        expect(
            visibleActionCount({
                ...sizes,
                widths: [100],
                available: 100,
                menuOnly: false,
            }),
        ).toBe(1);
    });
});
