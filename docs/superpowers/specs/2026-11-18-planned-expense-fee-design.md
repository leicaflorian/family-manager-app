# A commission on planned expenses, added to the amount

Approved by the owner on 18/11/2026.

## Intent

The rent costs 650 € and the bank charges 1 € for each transfer, so 651 € leave the account every month. Today only an
expense has a commission (`expenses.fee_amount`), and *I paid it* creates the expense without one: the owner has to
edit every payment by hand. The owner wants to write the commission once on the planned expense and get it on every
payment. Later they also want to see how much each bank account cost in commissions in a year, to judge whether the
account is worth keeping, so every payment carries its commission on its own.

The owner thinks of a commission as added to the cost (650 + 1), not as a part of it. On 18/11/2026 they wrote two
expenses that way (650 + 1, 70 + 0,80), while the expense form treated the commission as already included, so the two
expenses counted 650 € and 70 €. They were corrected in production to 651 € and 70,80 € the same evening.

## Goals

- **What is stored does not change**: `expenses.amount` is the total that left the account (651), `fee_amount` the
  part of it that is commission (1). Every total of the budget keeps adding `amount`. The reading of receipts already
  stores it this way (`ExpenseChanges`).
- **Forms speak in cost + commission.** In the expense form (`FmMovementPanel`) and in *I paid it* (`FmPayForm`),
  *Amount* is the cost without the commission (650) and *Commission* is optional and added to it (1); the help line
  under *Commission* becomes *It is added to the amount.* The form shows `amount − fee` and sends `amount + fee` as the
  amount, with `fee_amount`.
- **A planned expense has an optional *Commission*** (`planned_expenses.fee_amount`), next to its *Amount*, with the
  same help line. The planned amount is the cost (650).
- **A due date of that planned expense is worth the total** (651) wherever the app shows or adds it up (due date rows,
  Home, Agenda, Budget forecasts, card budgets, search), because `Occurrence::$amount` becomes the total.
- *I paid it* starts from cost 650 and commission 1, both editable; the expense it creates has `amount` 651 and
  `fee_amount` 1.
- An expense linked to a planned expense in any other way (`SaveMovement` when the planned item is chosen, the import)
  gets the commission of the planned expense when it has none of its own. The amount it was given stays the total.
- A change of a single due date (`amount_override`) changes the cost only: *Cambia questa scadenza* shows and edits the
  cost, and the commission is added to it.
- **Rows show the total with the commission under it**: a movement row and a due date row with a commission show 651 €
  and, under it in small, *of which 1,00 € commission* (`FmAmount` gets a `fee` prop).

## Non-goals

- The yearly commissions per account: a later piece of work.
- Commissions on incomes, planned incomes and loan installments.
- Filling the commission of payments already made.

## Anti-goals

- A total that differs between the due date row and the payment it proposes.
- A budget total that leaves out the commissions.

## Approaches

- **A. Separate commission on the planned expense, added to the cost (chosen).** The owner writes the numbers as they
  think of them (650 + 1); a rent rise changes the amount only.
- B. Total on the planned expense with "of which commission": a rent rise means remembering to write 671.
- C. No new field, *I paid it* repeats the last commission: due dates and forecasts stay wrong.

For the expenses, keeping `amount` as the total and converting in the forms was chosen over storing the cost alone,
which would have meant adding `fee_amount` in every sum of the budget.

## Decided

- `planned_expenses.fee_amount` decimal like `amount`, nullable (safe for rolling deploys); validation
  `nullable|numeric|min:0|max:99999999`, expenses only.
- `DeadlineData` gets `fee` (0 when none) so the forms can split the total.
- Boards: *Spesa-fissa-modifica* (+pc), *Pagata* (+pc), *Cambia-scadenza* (+pc), the expense form board
  (*Movimento*, +pc) and the `FmAmount` card of *Cifre-e-file*.
