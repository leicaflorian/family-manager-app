# A new subscription creates its planned expense

Approved by the owner on 18/11/2026 (approach C).

## Intent

Creating a subscription (for example Hostinger) and then its planned expense means writing the same things twice:
name, supplier, holder, account. The owner wants the *New card* form of a subscription to ask, once, for the cost and
how often it is paid, create the planned expense linked to the card from them, and, if asked, record the first payment
on it.

## Goals

- Only **a new subscription** (`subscription` card type, *New card*, no card yet) shows a section **Spesa fissa** after
  the card fields, as the replaced policies of a new insurance policy do (`#after` slot of `FmCardForm`):
  - **Cost** (`currency`, optional: empty means no planned expense, and nothing else of the section is checked);
  - **Every** (number, default 1) and **How often** (`togglebuttons` week / month / year, default month), the fields of
    `EditPlanned`;
  - **First payment** (`datepicker`), starting from the *Activation* date of the card as long as the person has not
    changed it, today when there is no activation;
  - **Category** (`autocomplete` of the expense categories, optional);
  - **I already paid it** (`toggle`, default off).
- None of these values is stored on the subscription.
- On save, in the same transaction as the card, `SavePlanned` creates a planned expense: title = card name, amount =
  cost, recurring every *Every* *How often* from *First payment*, organization = supplier, person = holder, account =
  the *Account or card* of the subscription (the same `financial_accounts` row), otherwise the default account (what
  `SavePlanned` does with an empty account), category, `card_type`/`card_id` = the new card.
- With **I already paid it**, `RecordPayment` pays the first due date (the *First payment* date) with the cost, on the
  same account, no receipt.
- Validation errors of the section come back under its fields; nothing is saved if one fails.

## Non-goals

- Editing an existing subscription (the planned expense is changed from its own page).
- Other card types (policies, utilities).
- The commission of the planned expense (added later from the planned expense page).
- An end date.

## Approaches

- Start of the planned expense: **C, its own *First payment* date, from the activation (chosen)**; A, always the
  activation (old activations would show months of late due dates); B, the next renewal with a loose expense for the
  first payment (the first payment would not pay a due date).
- Where: the existing `#after` slot of the card form and extra request fields, as `replace_policies` (chosen), rather
  than new card fields that would be stored, or a second page after saving.

## Decided by the builder

- The section title, *Spesa fissa*, and the help line under it: *Facoltativa: con il costo creo anche la spesa fissa.*
- The category is optional and offered because a planned expense without one is hard to find in the budget.
- The canvas gets two boards, *Nuovo-abbonamento* and *Nuovo-abbonamento-pc*, next to *Nuova-polizza*.
