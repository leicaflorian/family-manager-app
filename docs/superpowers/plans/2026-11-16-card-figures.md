# The figures of a card in one box — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On a card page the figures above *Data* are one box (dates, status, amounts), and what it shows leaves *Data*.

**Architecture:** The server adds a status figure (`CardNumberData` with `text` and `tone`) to `CardDetailData` and leaves the shown dates and status out of `values`; `FmCardBudget` draws one box with the tiles first, the tiles drawn `inset`.

**Tech Stack:** Laravel 13, spatie/laravel-data, Pest; Vue 3 + TypeScript, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-16-card-figures-design.md`

## Global Constraints

- English code and keys, Italian in `lang/it.json` (added by hand before the final `}`).
- Text styles from the design system only (`text-meta-strong`, `text-subtitle`, `text-body-sm`); classes outside the template through `cn()`.
- `sail artisan typescript:transform` after changing a data class; commit `resources/js/generated/generated.d.ts`.
- Only the touched files' checks before each commit (Pest, Vitest, PHPStan, Rector, Pint, types, lint).

## Review Focus

- A card whose status is empty: no status figure, and no empty box when it has no dates and no amounts either.
- A type without a status (vehicle): no status figure, *Data* unchanged except for the shown dates.
- A date left empty stays out of the tiles and was never in *Data*: nothing disappears that was not shown.
- *Expired* reached by itself is red; *Expired* on a type without an automatic expiry (none today) falls back to grey.
- `CardPayments.vue` keeps standalone `FmNumberTile` tiles (no `inset`).

---

### Task 1: Server — status figure and no duplicates

**Files:**
- Modify: `app/Data/CardNumberData.php`, `app/Data/CardDetailData.php`, `app/Services/CardDetails.php`, `app/Cards/Types/InsurancePolicyCard.php` (`dates()`: `start_date` first)
- Test: `tests/Feature/Cards/CardDetailsTest.php`

**Interfaces:**
- Produces: `CardNumberData(..., ?string $text = null, ?string $tone = null)` (`tone`: `'open' | 'overdue' | 'closed'`); `CardDetailData::$status: ?CardNumberData` (label «Status», `text` the enum label, `amount` null).

- [ ] **Step 1: Failing tests** in `CardDetailsTest`:
  - `it shows the status as a figure, coloured by where it stands` — dataset: active → `open`, cancelled → `closed`, expired (set with a past expiry: the observer turns it expired) → `overdue`; asserts `status->text`, `status->tone`, `status->label === 'Status'`.
  - `the dates of the tiles and the status leave the data` — the policy with start, expiry and status: `values` has no label `Start`, `Expiry`, `Status`; still has `Policy number`.
  - `a card without a status has no status figure` — vehicle Fiat Panda: `status` null.
  - `the tiles of a policy start with the start` — `dates[0]->label === 'Start'`.
- [ ] **Step 2:** run, see them fail.
- [ ] **Step 3:** implement. One private `CardDetails::closedForGood(CardType, Model): bool` (closed and not the `Expiries::for()` `to` status) used by both `dates()` and the status tone; `values()` skips `status` when the figure is shown and the columns of the filled tiles.
- [ ] **Step 4:** tests pass, with `CardPagesTest`; PHPStan, Rector, Pint; `typescript:transform`.
- [ ] **Step 5:** commit.

### Task 2: Frontend — one box

**Files:**
- Modify: `resources/js/components/fm/FmNumberTile.vue` (+ test), `FmDateTile.vue` (+ test), `FmCardBudget.vue` (+ test), `resources/js/pages/cards/Card.vue` (+ test)

**Interfaces:**
- Consumes: `card.status: App.Data.CardNumberData | null`, `CardNumberData.text`, `.tone`.
- Produces: prop `inset?: boolean` on `FmNumberTile` and `FmDateTile` (no `rounded-card bg-card p-4 shadow-card`).

- [ ] **Step 1: Failing tests:**
  - `FmNumberTile`: `writes a word figure in the colour of its tone` (`text: 'Attiva', tone: 'open'` → text shown, class `text-paid`; `closed` → `text-ink-soft`; `overdue` → `text-overdue`); `inset drops the card around it` (no `shadow-card` class).
  - `FmDateTile`: `inset drops the card around it`.
  - `FmCardBudget`: `puts the tiles and the figures in one box, the tiles first` (one element with `shadow-card` contains both, tile text before figure text).
  - `Card.test.ts`: the policy shows `Status` with its label in the box, and *Data* no longer lists it.
- [ ] **Step 2:** run, see them fail.
- [ ] **Step 3:** implement: the grid of `FmCardBudget` becomes `grid grid-cols-2 gap-x-3 gap-y-4 rounded-card bg-card p-4 shadow-card`, slot `tiles` first, numbers `inset`; `Card.vue` passes `inset` to `FmDateTile` and renders `<FmNumberTile v-if="card.status" inset :number="card.status" />` after the dates.
- [ ] **Step 4:** tests pass (also `CardPayments.test.ts`, `resources/js/design/`), types, lint, `vp check --fix`.
- [ ] **Step 5:** commit.

### Task 3: Canvas (a fork of the coordinator, in parallel with Task 1)

- [ ] `Polizza` and `Polizza-pc`: figures Start, Expiry, Status, Premium (Start replaces Insurer); *Data* drops Start.
- [ ] *Cifre-e-file*: `FmNumberTile` gets the word case with the three tones and the `inset` case; `FmDateTile` the `inset` case.
- [ ] Publish, save copies in `.superpowers/sdd/card-figures/boards/`.
