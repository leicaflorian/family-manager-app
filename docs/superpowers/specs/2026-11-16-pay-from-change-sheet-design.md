# «I paid it» from *Change this due date*

Approved by the owner on 16/11/2026 (small change, option 1 of 3).

## Intent

On *A planned expense* an `admin` who taps an open due date gets *Change this due date* only; to mark it paid they
had to open the due date's page. The panel now offers the payment too.

## Decisions

- `FmChangeDeadlineSheet` gets the prop `canPay` (default false) and the emit `pay`. With `canPay`, and for a due date
  that is not skipped, the footer has on the left an outline button with the check icon, «I paid it» («It arrived» for
  an income); *Cancel* and *Save* stay on the right. It closes the panel (unsaved changes are dropped, as *Cancel*
  does) and emits `pay`.
- Any open due date, not only late ones: paying early happens too.
- `PlannedItem.vue` passes `canPay` from `detail.can_pay` and on `pay` opens `FmPaySheet` for that due date
  (`accounts` already reaches the page; action `deadlineUrls(deadline).payment`). *A due date* does not pass `canPay`:
  its own button is beside *Change this due date*.
- Canvas first: the boards `Cambia-scadenza` and `Cambia-scadenza-pc` get the button.

## Approaches

Option 1 chosen over a row that opens the due date's page (one more step to change) and a menu on the row (a menu the
lists do not have). The plan is this note: tests in `FmChangeDeadlineSheet.test.ts` (button, `pay` emitted, hidden
without `canPay` and for a skipped due date) and `PlannedItem.test.ts` (the button opens the payment panel).
