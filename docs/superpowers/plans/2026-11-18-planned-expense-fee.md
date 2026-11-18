# Commission on planned expenses — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A planned expense carries an optional commission added to its cost; due dates are worth the total; forms
speak in cost + commission; rows show the total with "of which … commission".

**Architecture:** `expenses.amount` stays the total. `Schedule`/`Occurrence` learn the fee, so every reader of
`Occurrence::$amount` gets the total for free. The forms split and join `amount`/`fee_amount`. `FmAmount` shows the fee.

**Tech Stack:** Laravel 13, Pest, spatie/laravel-data, Vue 3 + FormKit Pro, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-18-planned-expense-fee-design.md`

## Global Constraints

- Code, comments, test names in English; Italian in `lang/it.json` (added by hand before the final `}`).
- `declare(strict_types=1)`, PHPDoc on every method, PHPStan 9, Rector, Pint; TSDoc, no `any`, `cn()` outside templates.
- Pest with its own database: `docker exec -u sail -e DB_DATABASE=testing_fee family-manager-app-laravel.test-1 php artisan test …`.
- After a data class change: `sail artisan typescript:transform`; after the migration: `ide-helper:models --write --reset </dev/null` (commit only `PlannedExpense`).
- Copy: `Commission`, `It is added to the amount.` («Si aggiunge all'importo.»), `of which :fee commission` («di cui :fee di commissione»).

## Review Focus

- A planned expense with no commission behaves exactly as today (fee 0, no "of which" line, totals unchanged).
- An amount override of 700 with fee 1 → due date 701, base shown 651.
- Planned incomes never get a fee (no field, fee 0).
- The pay form sending an empty commission → expense without fee and amount = cost.
- Editing an old expense (amount 651, fee 1) shows 650 + 1 and saves back 651 + 1 unchanged.

---

### Task 1: Planned expense column and form data (backend)

**Files:** migration `database/migrations/2026_11_18_000001_add_fee_amount_to_planned_expenses_table.php`;
`app/Models/PlannedExpense.php` (fillable, `casts` `decimal:2`, PHPDoc); `app/Http/Requests/Budget/SavePlannedRequest.php`
(rule in the expense branch); `app/Support/PlannedPreset.php` (`defaults` `fee_amount => null` for expenses, `of`);
test `tests/Feature/Budget/SavePlannedTest.php`, `PlannedPagesTest.php`.

- [ ] Test: saving a planned expense with `fee_amount: 1` stores `1.00`; an income ignores it; `-1` is a 422; the edit page values carry `fee_amount`.
- [ ] Run, see them fail. Implement. Run, pass. Commit.

### Task 2: Due dates are worth cost + fee

**Files:** `app/Support/Schedule.php` (`public float $fee = 0.0` last, filled from `fee_amount` of a `PlannedExpense`);
`app/Support/Occurrence.php` (`public float $fee = 0.0` last); `app/Services/Occurrences.php` (amount = (override ?? schedule amount) + fee, base = schedule amount + fee when overridden);
`app/Data/DeadlineData.php` (`public readonly float $fee = 0.0`); tests in `tests/Feature/Budget/DeadlinesTest.php`.

- [ ] Tests: fee 1 on 650 → `amount` 651, `fee` 1; override 700 → 701 with `base_amount` 651; no fee → unchanged.
- [ ] Implement, pass, `typescript:transform`, commit.

### Task 3: Payments carry the fee

**Files:** `app/Support/PlannedKind.php` (`paymentColumns` expense: `fee_amount`); `app/Actions/Budget/RecordPayment.php`
(`handle(..., ?UploadedFile $receipt, ?float $fee = null)`, expense `fee_amount = $fee`); `RecordPaymentRequest`
(`fee_amount` nullable numeric min 0); `DeadlineController` passes it; `ImportFile::PLANNED_GIVES` adds `fee_amount`;
tests `RecordPaymentTest.php`, `SaveMovementTest.php`.

- [ ] Tests: paying with amount 651 fee 1 → expense 651/1; without fee → null; choosing a planned item with fee 1 in `SaveMovement` without a fee → 1, with fee 2 → 2.
- [ ] Implement, pass, commit.

### Task 4: `FmAmount` shows the fee

**Files:** `resources/js/components/fm/FmAmount.vue` (`fee?: number | null`; when > 0 a second line `text-meta text-ink-soft` with `of which :fee commission`, right-aligned); test `FmAmount.test.ts`; `Movements.vue` (passes `movement.fee_amount`), `FmDeadlineRow.vue` (passes `deadline.fee`).

- [ ] Tests: fee 1 → «di cui 1,00 € di commissione» text; fee 0/null → none.
- [ ] Implement, pass, commit.

### Task 5: Forms in cost + commission

**Files:** `resources/js/lib/money.ts` or the existing amount helpers (`splitFee(total, fee)`, `joinFee(cost, fee)` with cents arithmetic); `FmMovementPanel.vue` (start from `amount − fee`, send `amount + fee`, help `It is added to the amount.`);
`FmPayForm.vue` (expense: *Commission* field starting from `deadline.fee || ''`, amount starts from `deadline.amount − deadline.fee`, sends `amount + fee` and `fee_amount`);
`FmChangeDeadlineSheet.vue` (planned and prefilled amounts are costs: minus `deadline.fee`); `EditPlanned.vue` (*Commission* for expenses); `lib/budget.ts` (`fee_amount?`); tests next to each, fixtures with `fee: 0`.

- [ ] Tests per form: the values shown and the payload sent (651/1 ↔ 650 + 1).
- [ ] Implement, pass, `lang/it.json`, commit.

### Task 6: Boards, docs

- [ ] Boards updated by a fork (list in the spec), published, copies in `.superpowers/sdd/planned-fee/boards/`.
- [ ] `project-docs` for the vault. Checks on touched files: Pest, Vitest, PHPStan, Rector, Pint, types, lint.
