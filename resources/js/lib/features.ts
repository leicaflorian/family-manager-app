/** The features of a family by name, as the server shares them (`family.features`). */
export type Features = Partial<Record<App.Enums.Feature, boolean>>;

/**
 * Whether a feature is on. `null` features (no family: public pages, tests) count as everything on, as `useFeature()`.
 *
 * @param features - the features of the family, or null without a family
 * @param name - the feature to ask for; undefined for a thing that does not depend on a feature
 * @returns true when the thing is available
 */
export function featureOn(
    features: Features | null,
    name: App.Enums.Feature | undefined,
): boolean {
    return name === undefined || features === null || (features[name] ?? false);
}
