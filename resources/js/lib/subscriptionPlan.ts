/**
 * The values of the section *Planned expense* of a new subscription: what the
 * planned expense created with the card costs and how often it is paid. None
 * of them is stored on the card.
 */
export type SubscriptionPlan = {
    /** The cost of each payment; null means no planned expense. */
    amount: number | null;
    /** Every how many units it is paid. */
    interval: number;
    /** The unit of how often. */
    interval_unit: 'week' | 'month' | 'year';
    /** The first due date, `yyyy-mm-dd`. */
    start_date: string | null;
    /** The expense category, as the id the menu gives. */
    category_id: string | null;
    /** The first due date is already paid. */
    paid: boolean;
};

/**
 * The section before the person writes anything: every month, no cost.
 *
 * @returns a new empty plan
 */
export function emptyPlan(): SubscriptionPlan {
    return {
        amount: null,
        interval: 1,
        interval_unit: 'month',
        start_date: null,
        category_id: null,
        paid: false,
    };
}
