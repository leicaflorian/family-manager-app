/**
 * The notices of the menu of the bell, as the server sends them in the shared prop `bell_notices`.
 * In a `.ts` file, not in `FmBell.vue`, so that Oxlint can read the type where it is imported.
 */
export type BellNotices = {
    /** The notices not seen yet, the most recent first. */
    unread: App.Data.ReminderNoticeData[];
    /** The notices already seen, the most recent first. */
    seen: App.Data.ReminderNoticeData[];
};
