# The figures of a card in one box

Approved by the owner on 16/11/2026 (small change).

## Intent

On the page of a card, the figures above *Data* are one box with several figures, as on the canvas
(`Polizza`, `Polizza-pc`, `Scheda`), not one small card per figure. For a policy the figures are
Start, Expiry, Status and Premium. What the box shows leaves the *Data* list below, so nothing is
written twice. The status is a coloured word, not a badge.

## Decisions

- **Every card type**, not only the policy: `FmCardBudget` draws one box (`rounded-card bg-card p-4
  shadow-card`, a two-column grid) with the tiles of the card first, then the figures of the payments.
- **Order**: the dates (start before expiry: `InsurancePolicyCard::dates()` puts `start_date` first),
  then the status, then the amounts.
- **Status figure**: only for a type with a status and a card whose status is filled. Tone:
  - `open` (green, `text-paid`) for an open status (*Active*, *In progress*…);
  - `overdue` (red) for the status a card takes by itself past its date (`Expiries::for()` `to`: a
    policy *Expired*, the renewal still to do);
  - `closed` (grey, `text-ink-soft`) for the other closed statuses (*Cancelled*, *Sold*…).
  The same rule decides whether a past deadline tile is red (14e4580), so it lives in one place.
- **No duplicates**: the filled dates shown as tiles and the status shown as a figure leave
  `CardDetailData::values`.
- **Components**: no new component. `FmNumberTile` and `FmDateTile` get `inset` (no background, padding or
  shadow, for a figure inside the box); `CardNumberData` gets `text` (a figure that is a word) and `tone`.
  The status is a `CardNumberData` with `text` and `tone`.
- **Canvas first**: in `Polizza` and `Polizza-pc` the fourth figure becomes Start (in place of Insurer),
  the order is Start, Expiry, Status, Premium, and *Data* drops Start; the boards of `FmNumberTile` and
  `FmDateTile` (*Cifre-e-file*) get the `inset` case and the status case.

## Approaches

One sensible way: the box was already a single grid in `FmCardBudget`, so turning that grid into one card
and the tiles into inset figures keeps one place for the figures of every card. A separate status
component was dropped because `FmNumberTile` already draws label, value and line below.

## Non-goals

No badge; no change to the list of cards (`CardSummaries`) or to the agenda.
