# A new subscription creates its planned expense Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** *New card* of a subscription asks for cost, frequency, first payment, category and «I already paid it», and
creates the linked planned expense (and its first payment) with the card.

**Architecture:** Extra request fields `planned.*` on `SaveCardRequest`, read only for a new subscription, like
`replace_policies` for a new policy. A new action `StartSubscriptionPlanned` runs inside the transaction of
`CardController::store` and uses `SavePlanned`, `Deadlines::find` and `RecordPayment`. On the page a new form section
`FmSubscriptionPlanned` sits in the `#after` slot of `FmCardForm`, keeps its own values and `EditCard` adds them to the
request as `planned`.

**Tech Stack:** Laravel 13 / PHP 8.5, Pest, Inertia + Vue 3, FormKit Pro, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-18-subscription-planned-expense-design.md`

## Global Constraints

- English code and keys, Italian in `lang/it.json` (added by hand before the final `}`, never through a JSON parser).
- `declare(strict_types=1)`, PHPDoc on every class and method, PHPStan level 9, Rector, Pint.
- Vue: `<script setup lang="ts">`, typed props/emits, TSDoc with `@group forms`, a `*.test.ts` beside the component,
  a row `"FmSubscriptionPlanned": "pages"` in `resources/design/boards.json`.
- FormKit Pro fields: `currency`, `number`, `togglebuttons`, `datepicker`, `autocomplete`, `toggle`.
- Pest with its own database: `docker exec -u sail -e DB_DATABASE=testing_subplan family-manager-app-laravel.test-1 php artisan test …`.

## Review Focus

- Cost empty but other section fields filled → no planned expense, no validation error from the section.
- Cost filled, first payment missing → 422 on `planned.start_date`, card not saved.
- A changed card that sends `planned` (edit, not new) → ignored, nothing created.
- Another card type (policy) sending `planned` → ignored.
- A category of another family → whatever `OfFamily` answers elsewhere, and the card is not saved (test in Task 1).
- «I already paid it» without a cost → nothing created, nothing paid.

---

### Task 1: Server — the request, the action and the store

**Files:**
- Create: `app/Actions/Cards/StartSubscriptionPlanned.php`
- Modify: `app/Http/Requests/Cards/SaveCardRequest.php` (rules + `subscriptionPlanned()`)
- Modify: `app/Http/Controllers/CardController.php` (`store`, `create` props)
- Modify: `lang/it.json`
- Test: `tests/Feature/Cards/SubscriptionPlannedTest.php`

**Interfaces:**
- Request fields (only for a new `subscription`): `planned.amount` (nullable numeric 0..99999999),
  `planned.interval` (nullable int 1..1000), `planned.interval_unit` (nullable `IntervalUnit`),
  `planned.start_date` (`required_with:planned.amount`, `date_format:Y-m-d`), `planned.category_id`
  (nullable, `OfFamily('expense_categories')`), `planned.paid` (boolean).
- `SaveCardRequest::subscriptionPlanned(): ?array{amount: float, interval: int, interval_unit: string, start_date: string, category_id: ?int, paid: bool}` — null unless new subscription with an amount.
- `StartSubscriptionPlanned::handle(Subscription $card, array $plan): PlannedExpense` (shape above).
- `CardController::create` adds `'expense_categories' => BudgetFormOptions::categories()` for the subscription type
  only (check the name against the keys of `HandleInertiaRequests::share()`).

- [x] **Step 1: Write the failing tests** in `SubscriptionPlannedTest` (as `SaveCardTest` posts a card):
  - `it creates the planned expense of a new subscription`: post name «Hostinger», supplier, holder,
    `financial_account_id`, `planned` = amount 35.88, interval 1, unit year, start 2026-11-15, category → one
    `PlannedExpense` with title «Hostinger», amount 35.88, `is_recurring` true, interval 1, unit year, start
    2026-11-15, same organization/person/account, category, `card_type` `subscription`, `card_id` the card; no expense.
  - `it records the first payment when asked`: same with `paid` true → one `Expense` with `planned_expense_id`,
    `occurrence_date` 2026-11-15, `date` 2026-11-15, amount 35.88.
  - `it creates nothing without a cost`: `planned` with interval, unit and start but no amount → no planned expense,
    card saved.
  - `it asks for the first payment when there is a cost`: amount without start → errors on `planned.start_date`, no card.
  - `it ignores the section when changing a subscription` (PUT with `planned`) and `for another card type` (insurance
    policy) → no planned expense.
  - `it offers the expense categories only on a new subscription`: GET `cards.create?card=subscription` has
    `expense_categories`; `?card=insurance_policy` has not.
- [x] **Step 2: Run** — expected FAIL (no planned expense / missing prop).
- [x] **Step 3: Implement** the rules (in a `plannedRules()` like `replacementRules()`, empty unless
  `canPlan()` = type `subscription` and no card), `subscriptionPlanned()`, the action (columns: title from card
  `name`, `amount`, `is_recurring` true, `is_estimated` false, `interval`, `interval_unit`, `start_date`,
  `organization_id`, `person_id`, `financial_account_id` from the card, `category_id`, `card_type` `subscription`,
  `card_id`; then if `paid`: `RecordPayment::handle($deadlines->find($planned, $start), $start, $amount, null, null)`),
  and in `store` call it inside the transaction when `$card instanceof Subscription` and the plan is not null.
- [x] **Step 4: Run** the new test and `SaveCardTest`, `ReplacePolicyFormTest`, `CardPagesTest` — PASS.
- [x] **Step 5:** PHPStan, Rector `--dry-run`, Pint on the touched files; `typescript:transform` not needed (no Data class).
- [x] **Step 6: Commit** «A new subscription creates its planned expense (server)».

### Task 2: The section on the page

**Files:**
- Create: `resources/js/components/fm/FmSubscriptionPlanned.vue`, `FmSubscriptionPlanned.test.ts`
- Modify: `resources/js/pages/cards/EditCard.vue`, `EditCard.test.ts`, `resources/design/boards.json`, `lang/it.json`

**Interfaces:**
- Consumes: prop `expense_categories` from Task 1, request field `planned`.
- `FmSubscriptionPlanned` props: `categories: App.Data.OptionData[]`, `activation: string | null` (the live
  `activation_date`); `v-model` of type
  `SubscriptionPlan = { amount: number | null; interval: number; interval_unit: 'week' | 'month' | 'year'; start_date: string | null; category_id: string | null; paid: boolean }`
  (type in `resources/js/lib/subscriptionPlan.ts`, with `emptyPlan(): SubscriptionPlan`).

- [x] **Step 1: Write the failing tests**
  - Component: shows the section title *Planned expense* and help *Optional: with the cost the planned expense is
    created too.*; fields *Cost*, *Every* (1), *How often* (Month chosen), *First payment*, *Category*, *I already
    paid it*; *First payment* follows `activation` (rerender with a new activation changes it) until the person
    picks a date, then stays; with no activation it is today.
  - Page: a new subscription shows the section; a new insurance policy and an edited subscription do not; saving a
    new subscription with a cost sends `planned` with the values (spy `router.post`, as the policy tests do); without
    a cost it sends no `planned`.
- [x] **Step 2: Run** `npx vp test run resources/js/components/fm/FmSubscriptionPlanned.test.ts resources/js/pages/cards/EditCard.test.ts` — FAIL.
- [x] **Step 3: Implement** the component (fields as in `EditPlanned`: `grid-cols-[7rem_minmax(0,1fr)]` for every /
  how often, units from the same labels), the boards row, and in `EditCard` the `#after` slot for
  `!card && type.type === 'subscription'` passing `live.activation_date`, plus `...(plan.amount !== null && { planned: plan })` in `save`.
- [x] **Step 4: Run** the two tests, `boards.test.ts`, `text-styles.test.ts`, `edges.test.ts` — PASS; types, oxlint,
  `vp check` on touched files; `TranslationsTest`.
- [x] **Step 5: Commit** «A new subscription creates its planned expense (page)».

### Task 3: Canvas boards (fork, in parallel with Task 1)

- [x] Draw *Nuovo-abbonamento* and *Nuovo-abbonamento-pc* from *Nuova-polizza* / *Nuova-polizza-pc* with the section
  of Task 2, in the row of the new cards, `var(--…)` colours; publish the canvas; save copies in
  `.superpowers/sdd/subscription-planned/boards/`. Task 2 compares the component with them.
