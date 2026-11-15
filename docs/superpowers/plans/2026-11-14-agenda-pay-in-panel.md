# Agenda: *I paid it* inside the panel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** In the Agenda, the panel of an open payment or income already holds the filled payment form; its submit (*I paid it* / *It arrived*) records the payment in one tap, with no second panel.

**Architecture:** The form of `FmPaySheet` moves to a new `FmPayForm`; `FmPaySheet` becomes the sheet around it (the due date page keeps it). `FmAgendaPanel` shows `FmPayForm` for open payments and incomes; `Agenda.vue` asks the server for `payment` and `accounts` when such a panel opens, and drops its second panel.

**Tech Stack:** Vue 3 + TypeScript, Inertia, FormKit Pro, Vitest + Testing Library (`vite-plus/test`).

**Spec:** `docs/superpowers/specs/2026-11-14-agenda-pay-in-panel-design.md`

## Global Constraints

- Rules of `AGENTS.md`: TDD, TSDoc on component/props/emits/functions, `@group` in the TSDoc, texts in English with Italian in `lang/it.json`, classes outside the template through `cn()`, text styles of the design system, `h-control` for fields and text buttons.
- Boards before code: `Agenda-pannello-pc` and `Agenda-pagamento` are redrawn first; where code and board differ, the board wins.
- Tests run only on what is touched (`docker exec -u sail family-manager-app-laravel.test-1 npx vp test run <files>`); the whole suites, build, types, PHPStan, Rector, Pint once before the final commit.
- Existing English keys are reused: `I paid it`, `It arrived`, `Open the due date`, `When`, `How much`, `With what`, `Into which account`, `Cancel`, `Confirm`.

## Review Focus

- The server refuses the payment (422, e.g. empty amount): the messages show under the fields in the panel and the panel stays open. → test in Task 2.
- The person changes the amount, then taps another row: the new panel starts from the new due date, not from the old values. → test in Task 3.
- The person changes the amount, then closes the panel with the × (side panel or sheet): it asks *Leave without saving?* first. → test in Task 3.
- `payment` still holds the due date of the previous entry when a new one opens: the form must not show the old one (match `payment` to the entry key). → test in Task 3.
- A `member` opens a payment: the form is there and works (payments are open to members). Nothing to change; no extra test.

---

### Task 1: Boards

**Files:**
- Canvas `https://claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr`: `project/Agenda-pannello-pc.dc.html`, `project/Agenda-pagamento.dc.html`, the card of `FmAgendaPanel` in `project/Pannelli.dc.html`.
- Copies for the next tasks: `.superpowers/sdd/agenda-pay/boards/`.

- [ ] **Step 1:** Download the three boards and *Campi* (`Artifact read`, `paths`), read how `FmPaySheet` draws the form today (date + amount in two columns, the account as segments, the dashed receipt button, the income notice).
- [ ] **Step 2:** Redraw the payment panel: under the values (*Bank account*, *Planned expense*) the form, filled (today, the amount, the account), then the wide primary *I paid it* and the *Open the due date* link; no *Cancel*. Phone: the same in the sheet. Colours with `var(--…)`, radii in px, text styles of the tokens.
- [ ] **Step 3:** Publish to the canvas URL (with `root` on the download folder), save the copies in `.superpowers/sdd/agenda-pay/boards/`, look at the published boards.

### Task 2: `FmPayForm`, and `FmPaySheet` around it

**Files:**
- Create: `resources/js/components/fm/FmPayForm.vue`, `resources/js/components/fm/FmPayForm.test.ts`
- Modify: `resources/js/components/fm/FmPaySheet.vue`, `resources/js/components/fm/FmPaySheet.test.ts`, `resources/design/boards.json` (`"FmPayForm": "pages"`)

**Interfaces:**
- Produces: `FmPayForm` (`@group panels`), props `{ deadline: App.Data.DeadlineData; accounts: App.Data.OptionData[]; action: string; submitLabel?: string /* default trans('Confirm') */; cancellable?: boolean /* default false: shows Cancel, which emits cancel */; active?: boolean /* default true: guards unsaved changes */ }`, emits `{ saved: []; cancel: [] }`, `defineExpose({ confirmClose: () => Promise<boolean>, reset: () => void })`. `reset()` puts back today, the amount and the account of `deadline`, clears the file and the messages. It also resets by itself when `deadline` changes (watch on `deadline.planned_id` + `deadline.original_date`).
- `FmPaySheet` keeps its props, emits and behaviour (title, the line with title and amount, *Cancel* closes without asking, × asks with changes, resets on reopen).

- [ ] **Step 1: Failing tests** in `FmPayForm.test.ts`: move the form cases of `FmPaySheet.test.ts` (today/amount/account, other account, receipt, income becomes, month of the original date) to `FmPayForm`, plus:
  - `it('names its button with submitLabel')`: `submitLabel: 'I paid it'` → `getByRole('button', { name: 'I paid it' })`, no `Cancel` button.
  - `it('shows the messages of the server and stays as it is')`: `router.post` mocked calling `options.onError({ amount: 'The amount is required.' })` → text visible, `emitted().saved` undefined.
  - `it('tells it was saved')`: `onSuccess` called → `emitted().saved` length 1.
  - `it('starts again from the new due date')`: change amount, `rerender({ deadline: other })` → amount field shows the other amount.
- [ ] **Step 2:** Run `npx vp test run resources/js/components/fm/FmPayForm.test.ts` → FAIL (no component).
- [ ] **Step 3:** Write `FmPayForm.vue` from the form part of `FmPaySheet.vue` (values, problems, processing, picker, income texts, `save()` with `forceFormData` and `preserveScroll`); `useUnsavedChanges(() => values, { active: () => active })`. Make `FmPaySheet.vue` an `FmBottomSheet` with the line above and `<FmPayForm ref cancellable :active="open" @saved="open = false" @cancel="open = false" />`, `:before-close` from the form's `confirmClose`, `reset()` on reopen. Add the board row.
- [ ] **Step 4:** Run `FmPayForm.test.ts FmPaySheet.test.ts resources/js/pages/budget/Deadline.test.ts resources/js/design/boards.test.ts` → PASS.
- [ ] **Step 5:** Commit `FmPayForm: the form of the payment on its own, the sheet around it`.

### Task 3: The form in the Agenda panel

**Files:**
- Modify: `resources/js/components/fm/FmAgendaPanel.vue`, `FmAgendaPanel.test.ts`, `resources/js/pages/Agenda.vue`, `resources/js/pages/Agenda.test.ts`

**Interfaces:**
- Consumes: `FmPayForm` of Task 2.
- `FmAgendaPanel`: new props `payment?: App.Data.DeadlineData | null` (the due date of this entry, or null while loading), `accounts?: App.Data.OptionData[]`, `payAction?: string`; emit `pay` replaced by `paid: []`; `defineExpose({ confirmClose })` (true when there is no form). For `entry.kind` `payment`/`income` and not `done`: with `payment` it shows `FmPayForm` with `submitLabel` *I paid it* / *It arrived*, `:active` true, `@saved` → `emit('paid')`; without it a disabled wide button with the same label. *Open the due date* stays under.
- `Agenda.vue`: `openPanel(entry)` for an open payment/income also does the partial reload (`only: ['payment', 'accounts']`, `data: { payment: entry.key }`, `preserveUrl: true`); the panel gets `payment` only when `payment-key === entry.key` (`payment:${planned_id}:${original_date}`), `accounts`, `payAction` (the current `payAction`). `@paid` → `closePanel()`. Closing (× of `FmSidePanel`, `before-close` of `FmBottomSheet`) asks the panel's `confirmClose()` first. `pay()`, `paying`, `payOpen` and the second `FmPaySheet` go away.

- [ ] **Step 1: Failing tests.**
  - `FmAgendaPanel.test.ts`: replace the *I paid it emits pay* part: with `payment` and `accounts`, `getByLabelText('How much')` has `84,20 €` and the `I paid it` button submits (`router.post` to `payAction`); without `payment` the `I paid it` button is disabled; in `done` view no form. An income → button `It arrived`.
  - `Agenda.test.ts`: replace `asks the server for the due date with I paid it, then opens the payment` with `it('opening a payment asks for its due date and records it in one tap')`: click the row → `router.reload` with `only: ['payment','accounts']`, `data: { payment: 'payment:7:2026-10-23' }`; rerender with `payment: lateDeadline, accounts` → in the panel click `I paid it` → `router.post` to `/budget/planned/expense/7/occurrences/2026-10-23/payment?from_agenda=1`; no second dialog named `I paid it`. Plus `it('asks before closing a payment panel with changes')` (change *How much*, click `Close` → the confirm of `useUnsavedChanges` appears) and `it('does not show the due date of another payment')` (open another row while `payment` is still `lateDeadline` → `I paid it` disabled).
- [ ] **Step 2:** Run `npx vp test run resources/js/components/fm/FmAgendaPanel.test.ts resources/js/pages/Agenda.test.ts` → FAIL.
- [ ] **Step 3:** Implement as in Interfaces, following the boards of Task 1 (`.superpowers/sdd/agenda-pay/boards/`).
- [ ] **Step 4:** Run the two test files and `FmPayForm.test.ts` → PASS. Look at the Agenda in headless Chromium (account of the Bianchis, 1440×900 and 390×844): one tap records, the panel closes, the entry leaves *To do*.
- [ ] **Step 5:** Commit `Agenda: I paid it inside the panel of the entry, without a second panel`.

### Task 4: Before the commit

- [ ] Whole Vitest + Pest (`--parallel --processes=6`), PHPStan, Rector, Pint, build, types, lint; `npx vp check --fix`.
- [ ] Update the functional documentation of the Agenda in the vault (`project-docs`).
