export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    /** A bank filter the server saved with the message: the toast offers *Edit the filter*. */
    filter_id?: number;
};

/**
 * The flash `similar` the server sends after the person sorts by hand one movement of a group: how many movements
 * like it are still to sort (`count`), up to twenty of them (`rows`), what the filter for them would do (`action`,
 * and the filter itself as data in `filter`), why they are alike (`reason`: the mandate or the words), their amounts
 * (`min`, `max`, without sign), their days (`from`, `to`, `yyyy-mm-dd`), whether they are `expense` or `income` and
 * the address of the form of that filter.
 */
export type FlashSimilar = {
    count: number;
    kind: 'expense' | 'income';
    rows: App.Data.BankTransactionData[];
    action: 'pay' | 'create' | 'transfer';
    filter: App.Data.BankFilterData;
    reason: { kind: 'mandate' | 'words'; name: string } | null;
    min: number;
    max: number;
    from: string;
    to: string;
    url: string;
};
