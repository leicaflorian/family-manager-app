export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    /** A bank filter the server saved with the message: the toast offers *Edit the filter*. */
    filter_id?: number;
};

/**
 * The flash `similar` the server sends after the person sorts by hand one movement of a group: how many movements
 * like it are still to sort, the first five of them, what the filter for them would do (`pay`, `create` or
 * `transfer`) and the address of the form of that filter.
 */
export type FlashSimilar = {
    count: number;
    rows: App.Data.BankTransactionData[];
    action: 'pay' | 'create' | 'transfer';
    url: string;
};
