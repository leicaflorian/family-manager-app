import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';
import type { BellNotices } from '@/lib/notices';

/**
 * The notices of the bell, from the shared props: how many are new, the ones of the menu (sent only
 * when the menu asks) and the call that asks for them. The only place that reloads `bell_notices`.
 *
 * @returns the unread count, the notices and `loadNotices`
 */
export function useBellNotices(): {
    unread: ComputedRef<number>;
    notices: ComputedRef<BellNotices | undefined>;
    loadNotices: () => void;
} {
    const page = usePage();

    return {
        unread: computed((): number => page.props.unread_notices ?? 0),
        notices: computed(
            (): BellNotices | undefined => page.props.bell_notices,
        ),
        /** Asks the server for the notices of the bell, without changing the address. */
        loadNotices: (): void => {
            router.reload({ only: ['bell_notices'], preserveUrl: true });
        },
    };
}
