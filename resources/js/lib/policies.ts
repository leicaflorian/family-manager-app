import { trans } from 'laravel-vue-i18n';
import { formatDate } from '@/lib/format';

/** The column of the card each policy type covers; the types not listed cover none. */
// Same rule as `ReplaceablePolicies::coveredColumn` on the server, which reads
// it from the `showWhen` of `InsurancePolicyCard`: change both together.
const COVERED_COLUMN: Record<
    string,
    'vehicle_id' | 'home_id' | 'employment_id'
> = {
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

/** One box *Cancel the policy …* of a checkbox: the old policy, its name and its expiry. */
export type ReplaceOption = {
    value: number;
    label: string;
    help: string | null;
};

/**
 * The boxes *Cancel the policy …*, one per old policy, with its expiry as help.
 * The help also says the premium moves to the new policy only when that is
 * sure (the card form); *Import* may plan a premium of its own for the new
 * policy, and then the old one ends instead.
 *
 * @param policies - the old policies offered
 * @param options - `premiumMoves`: whether the help says the premium moves
 * @returns the options of the checkbox
 */
export function replaceOptions(
    policies: App.Data.ReplaceablePolicyData[],
    options: { premiumMoves: boolean },
): ReplaceOption[] {
    return policies.map((policy) => ({
        value: policy.id,
        label: trans('Cancel the policy :name', { name: policy.label }),
        help:
            policy.expiry_date === null
                ? null
                : trans(
                      options.premiumMoves
                          ? 'Expires on :date. Its planned expense moves to the new one.'
                          : 'Expires on :date.',
                      { date: formatDate(policy.expiry_date) },
                  ),
    }));
}
