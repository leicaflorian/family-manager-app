import { router } from '@inertiajs/vue3';
import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { toast } from 'vue-sonner';
import { initializeFlashToast } from './flashToast';

vi.mock('vue-sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
        warning: vi.fn(),
    },
}));

/** The listener `initializeFlashToast` gave to the router. */
let listener: (event: Event) => void;

beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(router, 'on').mockImplementation(((
        _name: string,
        callback: (event: Event) => void,
    ) => {
        listener = callback;

        return () => {};
    }) as never);
    initializeFlashToast();
});

/**
 * Sends a flash to the listener as the router does.
 *
 * @param flash - what the server flashed
 */
function flash(flash: Record<string, unknown>): void {
    listener(new CustomEvent('flash', { detail: { flash } }));
}

describe('flashToast', () => {
    it('shows the message of the toast', () => {
        flash({ toast: { type: 'success', message: 'Paid: Rent' } });

        expect(toast.success).toHaveBeenCalledWith('Paid: Rent');
    });

    it('shows nothing without a toast', () => {
        flash({ similar: {} });

        expect(toast.success).not.toHaveBeenCalled();
    });

    it('adds Edit the filter when the message carries a filter', () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
        flash({
            toast: { type: 'success', message: 'Paid: Rent', filter_id: 7 },
        });

        expect(toast.success).toHaveBeenCalledWith(
            'Paid: Rent',
            expect.objectContaining({
                action: expect.objectContaining({ label: 'Edit the filter' }),
            }),
        );
        const options = vi.mocked(toast.success).mock.calls[0][1] as {
            action: { onClick: () => void };
        };
        options.action.onClick();
        expect(visit).toHaveBeenCalledWith('/budget/bank/filters/7/edit');
    });
});
