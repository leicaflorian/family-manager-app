# Agenda: *I paid it* inside the panel of the entry

## Why

In the Agenda, paying a due date takes a step too many: the row opens the panel
of the entry, *I paid it* closes it and opens a second panel (`FmPaySheet`) with
the form. The owner wants one panel: the form is already there, and in the usual
case one tap records the payment.

## Goals

- In the panel of an open payment or income (the *To do* view), under the
  details, the form of the payment is already open and filled: *When* (today),
  *How much* (the expected amount), the account (the one of the due date), the
  receipt (optional), and for an income what was expected and what it becomes.
- The button at the bottom is the submit: *I paid it* (income: *It arrived*).
  *Open the due date* stays under it. No *Cancel*: the × of the panel closes it.
- The same on the computer (side panel) and on the phone (sheet from below).
- Once recorded, the panel closes and the entry leaves *To do*, as today after
  the second panel.

## Non-goals

- The page of the due date (`budget/Deadline.vue`) keeps `FmPaySheet` as it is.
- The *Done* view, tasks, deadlines of the cards: unchanged.

## Approaches

- **A, chosen by the owner (also on the phone)**: the form open in the panel,
  one tap in the usual case. The panel gets longer.
- B: *I paid it* swaps the details for the form in the same panel, with a back
  arrow. No second panel, but still two taps.

## Design

- **Boards first**: `Agenda-pannello-pc` (computer) and `Agenda-pagamento`
  (phone) are redrawn with the form in the panel, from the field boards
  (*Campi*), then the code follows them.
- **No cloned code**: the form of `FmPaySheet` (fields, income texts, sending,
  server messages) moves to a new `FmPayForm` (`@group panels`), with a prop for
  the label of the submit and whether it shows *Cancel*. `FmPaySheet` becomes the
  sheet around `FmPayForm`; `FmAgendaPanel` shows `FmPayForm` in place of the
  *I paid it* button. Its row in `boards.json` and its card in the board.
- **Data**: opening the panel of a payment or income asks the server for
  `payment` and `accounts` (the same partial reload that *I paid it* does now,
  `preserveUrl`). Until they arrive the panel shows the details and the button
  disabled; the second panel and its state in `Agenda.vue` go away.
- **Unsaved changes**: the form guards its changes while the panel is open
  (`useUnsavedChanges` with `active`), so closing the panel after changing the
  amount asks first; opening another entry starts again from its due date.
- Permissions: unchanged (a `member` can pay, the server checks).

## Left to the builder

- The exact look of the form in the panel, from the boards.
- Tests: `FmPayForm` (from `FmPaySheet`'s tests), `FmAgendaPanel` (the form for
  an open payment, not in *Done*), `Agenda` (one tap records with the values of
  the due date and closes the panel).
