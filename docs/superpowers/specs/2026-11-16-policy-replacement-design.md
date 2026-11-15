# A new policy replaces the old one

Approved by the owner on 16/11/2026.

## Intent

When a policy is renewed with another company, today the app does not know that the new policy replaces the old
one. The old one turns *Expired* by itself the day after its expiry and keeps the *Renew the policy* reminder open
forever, unless the person remembers to set it to *Cancelled* by hand; the planned expense of the premium stays on
the closed card. The owner's natural gesture is: the policy is about to expire, they buy it from a new company, then
they open the app and say they paid it (or that they renewed it). At that moment the app should ask whether they
kept the same policy or changed company, and do the rest.

## Goals

- In the two panels where the person confirms a renewal, a choice **Same company / I changed company** (plus
  **Only the payment** in C):
  - **A. *Renewed*** (`FmDeadlineDateSheet` with `renewed`, from the *Renew the policy* deadline in the Agenda and
    the Home);
  - **C. *I paid it*** (`FmPaySheet` / `FmPayForm`) of a planned expense linked to a policy.
- **B. Creating a policy** for the same covered card (vehicle, home or employment) and of the same type as a policy
  still *Active* or *Expired*: the form offers to cancel the old one.
- The same offer in ***Import***, in the box of the new policy that the reading proposes.
- Replacing never leaves things half done: the old policy is cancelled only when the new one is saved.

## Non-goals

- No link stored between the old and the new policy (no `replaced_by` column): the old one is simply *Cancelled*.
- No change for other card types (subscriptions, utilities…): only insurance policies.
- No automatic detection after the fact (policies already registered twice are not cleaned up).
- The documents of the old policy stay on the old policy.

## Anti-goals

- The old policy cancelled while no new policy exists (form abandoned, error).
- A question on every instalment of a premium paid in several parts.
- A second, smaller policy form inside a panel.

## Approaches

1. **The new policy is made with its own form, and saving it does the replacement** (chosen). *I changed company*
   opens *New policy* with presets and a ticked box «Cancel the policy X»; on save, one server action cancels the old
   one, closes its reminder and moves the planned expense (and the payment just made) to the new one. Abandoning the
   form leaves everything as it was. The same box serves B and Import. One mechanism for the three entry points.
2. **Everything inside the panel**: insurer, number and expiry fields in the renewal / payment panel, one save. Faster,
   but it is a second, poorer policy form (no notes, no attached document), and B and Import need their own code.
3. **Cancel first, then the form**: simplest, but abandoning the form leaves no active policy and the payment on the
   cancelled one.

The owner chose 1.

## The design

### The replacement (server)

One action (for example `App\Actions\Cards\ReplacePolicy`) called after the new policy is saved, in the same
transaction as the save, with the new policy, the list of old policies to cancel and, optionally, a payment to move:

- each old policy goes to `cancelled`;
- its open automatic reminder of `insurance_policies.expiry_date` is marked **done** (not deleted, as
  `UpdateDeadline` does with *Renewed*), so it leaves the Home and the Agenda;
- every planned expense linked to the old policy (`card_type = insurance_policy`, `card_id` = old) is linked to the
  new one; name, amount and repetition stay the same: the person corrects them if they changed. **Except** when the
  new policy already has a planned expense of its own (the import can create one): then the old ones are not moved
  but end at the old policy's expiry (`end_date`), so the premium is never counted twice (builder check, my call);
- the payment passed (an `Expense` of that planned expense, created by *I paid it*) is linked to the new policy too
  (`card_type`/`card_id`). Older payments stay on the old policy.
- Only old policies the user can `update` (so an `admin`), of the same family (the trait), different from the new one.
  The request validates them: a wrong id is a 404 / validation error, never a silent cancel.

The card form (`SaveCardRequest`, `CardController::store`) and the import (`ImportFile`, through `ImportChoices`)
pass their ticked old policies to this action; nothing else cancels.

### Which old policies are offered (B and Import)

A policy is offered for cancellation when, compared with the new one:

- same `type`;
- same covered card: `vehicle_id` for `car`, `home_id` for `home`, `employment_id` for `health`/`accident`/`fund`
  (the `showWhen` of `InsurancePolicyCard`); a type without a covered card (for example life) offers nothing in B;
- status `active` or `expired`.

Usually there is one; with more, one box each. The rule lives in one place (a query on `InsurancePolicy` or a small
service), used by the form and by the import.

### A. *Renewed*

The panel of the *Renew the policy* deadline (only for `insurance_policies.expiry_date`) gets a `togglebuttons`
**Same company / I changed company**, on *Same company* by default.

- *Same company*: today's behaviour (new date proposed a year later, reminder done, policy active again).
- *I changed company*: the date field hides, the button becomes *Continue*, and it opens *New policy*
  (`cards.create?card=insurance_policy`) with presets: `title`, `type`, `vehicle_id`/`home_id`/`employment_id`,
  `person_id` from the old one, `start_date` = the old expiry, and the old policy to replace (for example
  `replaces=<id>`), so the box «Cancel the policy X» is there and ticked. Insurer, number and expiry are left empty.
  Through `replaces` this works for any policy type, also one without a covered card.

### C. *I paid it*

The choice appears only when the planned expense is linked to a policy (`card_type = insurance_policy`) whose status
is `active` or `expired` and whose expiry is passed or within the next **30 days** (measured on the day of the
payment). Mid-year instalments of a premium paid in parts ask nothing.

Three choices (`togglebuttons`), **Only the payment** selected by default (decision of the owner): with a premium paid
in instalments, the last instalment of the old year also falls inside the window, and a default that renews would
renew by mistake.

- *Only the payment*: today's behaviour, the policy is not touched.
- *Same company, renewed*: the payment is recorded and the policy is renewed as by *Renewed*: a date field «New expiry date»
  prefilled with the expiry plus one year (as in A), the reminder of the old date done, the policy active again. It
  goes through `UpdateDeadline` (one place for a renewal), in the same transaction as `RecordPayment`. This follows the
  precedent of subscriptions, where paying the yearly fee moves the renewal (`RenewSubscription`).
- *I changed company*: the payment is recorded as today (still on the old policy for the moment), then the page opens
  *New policy* with the presets of A plus the payment just made (for example `payment=<expense id>`). Saving the new
  policy moves the planned expense and that payment to it (decision of the owner: the payment belongs to the new
  policy). Abandoning the form leaves the payment and the planned expense on the old policy, and the old policy as it
  was.

**A `member`** can record the payment but cannot update the policy nor create one by hand: for them the choice does
not appear and *I paid it* works as today (my call; unlike subscriptions, where the payment moves the renewal for
anyone, here renewing changes the policy card).

### B. *New policy* by hand

In the card form of a policy, when type and covered card match one or more old policies (rule above), a box per old
policy appears at the bottom: «Cancel the policy X» (title, number, insurer, expiry), **unticked** when the form was
opened by hand, **ticked** when it came from A or C (`replaces`). It follows the type and the covered card as the
person changes them. The question comes before saving, not after (a change from the first idea, agreed with the
owner with approach 1). Only on creation, not when editing a policy.

### Import

When the reading proposes a new policy (the policy box of *What I also do*, `RecordDraftData` of kind `Policy`) and
the rule finds old policies for it, the box shows «Cancel the policy X», **ticked**, only to users who can update the
old policy (a `member` does not see it; for them the import stays as today). Ticked, `ImportFile` calls the same
action after creating the policy. Expenses created by the import and linked to the new policy are already on it.

### Design first

Following `AGENTS.md`, the screens are drawn in the canvas (phone and wide versions) before the code: the *Renewed*
panel (`Agenda-scadenza`, `Cambia-scadenza`, and the Agenda panels that show it), the payment panel (`Agenda-pagamento`,
`Pagata`, with the choice and the date field), the new card form (`Nuova`) with the box, and the import box
(`Importa-scheda-pc`, `Proposta`). The builder checks the exact boards on the canvas. No new component expected: the
choice is a FormKit `togglebuttons`, the box a `checkbox`; if a new one is needed the reason goes in the plan's
*Decisioni prese*.

### Texts

English keys with the Italian in `lang/it.json` (for example *Same company* / «Stessa compagnia», *I changed company*
/ «Ho cambiato compagnia», *Cancel the policy :name* / «Disdici la polizza :name», *Continue* / «Continua»). Exact
wording to the builder, simple and informal.

## Constraints

- Family isolation through `BelongsToFamily`; an id of another family answers 404.
- Policies: cancelling needs `update` on the old policy; creating a policy by hand is already `admin` only; in the
  import a `member` creates the policy but does not cancel.
- Migrations: none expected.
- TDD, PHPStan 9, typed data classes and generated TypeScript, as in `AGENTS.md`.
- Another session is working on the card page (spec `2026-11-16-card-figures-design.md`): this work does not touch the
  card page, but check `git status` before touching shared files.

## Left to the builder

- Names of the action, the query parameters and the request fields.
- How the form receives the offered old policies (prop from the controller, recalculated when type or covered card
  change, or a small endpoint).
- Where exactly the payment panel learns that its planned expense is linked to a policy near its expiry (a field of the
  deadline data).
- Exact texts and help lines.
