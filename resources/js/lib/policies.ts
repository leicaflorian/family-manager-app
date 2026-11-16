/** The column of the card each policy type covers; the types not listed cover none. */
// Same rule as `ReplaceablePolicies::coveredColumn` on the server, which reads
// it from the `showWhen` of `InsurancePolicyCard`: change both together.
const COVERED_COLUMN: Record<string, 'vehicle_id' | 'home_id' | 'employment_id'> =
    {
        car: 'vehicle_id',
        home: 'home_id',
        health: 'employment_id',
        accident: 'employment_id',
        fund: 'employment_id',
    };

/**
 * The old policies a new one can replace with the values of the form: those of
 * the same type that cover the same card (ids compared as numbers), plus every
 * policy in `replaces` whatever its card, because a renewal works for any type.
 *
 * @param policies - the policies the server offers
 * @param values - the live values of the form
 * @param replaces - the ids to replace from the start
 * @returns the policies to offer, in the order given
 */
export function replaceableFor(
    policies: App.Data.ReplaceablePolicyData[],
    values: Record<string, unknown>,
    replaces: number[],
): App.Data.ReplaceablePolicyData[] {
    const type = typeof values.type === 'string' ? values.type : null;
    const column = type === null ? undefined : COVERED_COLUMN[type];
    const raw = column === undefined ? null : values[column];
    const covered =
        raw === null || raw === undefined || raw === '' ? null : Number(raw);

    return policies.filter(
        (policy) =>
            replaces.includes(policy.id) ||
            (column !== undefined &&
                covered !== null &&
                policy.type === type &&
                policy[column] === covered),
    );
}
