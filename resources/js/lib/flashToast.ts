import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { toast } from 'vue-sonner';
import { edit } from '@/routes/budget/bank/filters';
import type { FlashToast } from '@/types/ui';

/**
 * Shows the messages the server flashes (`toast`). A message that carries a `filter_id` (a bank filter saved by
 * itself after a *Yes*) also has *Edit the filter*, which opens that filter.
 */
export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        if (data.filter_id === undefined) {
            toast[data.type](data.message);

            return;
        }

        const filterId = data.filter_id;

        toast[data.type](data.message, {
            action: {
                label: trans('Edit the filter'),
                onClick: () => router.visit(edit(filterId).url),
            },
        });
    });
}
