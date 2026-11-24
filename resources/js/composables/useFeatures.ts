import { usePage } from '@inertiajs/vue3';
import type { ComputedRef } from 'vue';
import { computed } from 'vue';

/**
 * The parts of the app the family has (`FamilyFeatures` on the server), as it shares them in `family.features`.
 *
 * @returns the features by name, or null without a family (a guest, a test)
 */
export function useFeatures(): ComputedRef<Partial<
    Record<App.Enums.Feature, boolean>
> | null> {
    const page = usePage();

    return computed(
        (): Partial<Record<App.Enums.Feature, boolean>> | null =>
            page.props.family?.features ?? null,
    );
}

/**
 * Whether the family has one feature. Without a family (public pages, tests) every feature counts as on: a
 * page that is not behind the sign-in has no feature to hide.
 *
 * @param name - the feature, for example `consumption`
 * @returns whether the feature is on
 */
export function useFeature(name: App.Enums.Feature): ComputedRef<boolean> {
    const features = useFeatures();

    return computed(
        (): boolean =>
            features.value === null || (features.value[name] ?? false),
    );
}

/**
 * Whether this server can offer the mail for the bills (`FamilyFeatures::mailAvailable()`, shared as
 * `family.mail_available`). When it cannot, the Welcome, *What you use* and *To begin* do not show the mail at all.
 * Without a family (public pages, tests) it counts as available.
 *
 * @returns whether the mail can be offered
 */
export function useMailAvailable(): ComputedRef<boolean> {
    const page = usePage();

    return computed((): boolean => page.props.family?.mail_available ?? true);
}
