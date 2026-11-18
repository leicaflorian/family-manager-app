# A new policy replaces the old one: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a policy is renewed with another company, the app cancels the old policy and moves its premium to the new one, from *Renewed*, *I paid it*, the new policy form and *Import*.

**Architecture:** One server action, `ReplacePolicy`, does the replacement. It runs only when a new policy is saved, from the card form (`CardController::store`) or from the import (`ImportFile`). *Renewed* and *I paid it* never cancel anything: with *I changed company* they lead to the card form, prefilled through the query, with the old policy (and the payment just made) to replace. One rule, `ReplaceablePolicies`, decides which old policies are offered.

**Tech Stack:** Laravel 12 / PHP 8.5, Pest, spatie/laravel-data with typescript-transformer, Inertia 3, Vue 3 + TypeScript, FormKit Pro, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-16-policy-replacement-design.md`

## Global Constraints

- Everything as in `AGENTS.md`: TDD, `declare(strict_types=1)`, `final`, PHPDoc and TSDoc on everything, PHPStan level 9 with no ignores, Rector and Pint clean, `vp check` clean.
- Code, comments and tests in English. Texts in English with the Italian added **by hand** to `lang/it.json`, before the final `}`; never rewrite the file with a JSON parser (duplicate key `"Not yet"`). `TranslationsTest` must pass.
- Italian texts: «Stessa compagnia», «Ho cambiato compagnia», «Solo il pagamento», «Stessa compagnia, rinnovata», «Disdici la polizza :name», «Continua», «Nuova scadenza». Informal, no technical words.
- An id of another family answers 404, never 403 and never data. Cancelling needs `update` on the old policy.
- No migration.
- After changing a data class: `sail artisan typescript:transform` and commit `resources/js/generated/generated.d.ts`.
- Before each commit only the checks of the touched files and of their users (rule of 15/11/2026). Pest with its own database: `docker exec -u sail -e DB_DATABASE=testing_policyreplace family-manager-app-laravel.test-1 php artisan test <files>`, written out in full.
- The window of *I paid it*: policy `active` or `expired`, expiry passed or within **30 days** after the day of the payment page.
- *I paid it* choice: `only` (default) | `same` | `changed`. *Renewed* choice: `same` (default) | `changed`.
- The new expiry proposed in *I paid it* with `same`: the old expiry plus one year.

## Review Focus

1. **A payment id that is not the one just made** (another family, another planned expense, a planned expense not linked to the old policy) sent to the card form: 404 for another family, otherwise ignored, never moved. Test in Task 3.
2. **The old policy id equal to the new one, already cancelled, or of another family**: 404 for another family, a validation error otherwise; nothing is cancelled. Test in Task 3.
3. **A stale payment page**: `renewal` sent `same` or `changed` for a due date that no longer has a renewal (policy renewed meanwhile, user became a member, planned expense no longer linked): 422, the payment is not recorded. Test in Task 5.
4. ***Same company, renewed* after the policy was already renewed in another tab** (the expiry is no longer the one the page showed): the payment is recorded and the policy is not moved a second time. Test in Task 5.
5. **The form changes type or covered card after opening from *Renewed*** (car → home): the box of the old car policy disappears and its id is not sent. Test in Task 6.

---

### Task 0: Canvas boards (coordinator, fork)

Following `AGENTS.md`, the screens are drawn before the code, by a fork of the coordinator (subagents cannot open the canvas). It can run while Tasks 1–3 are written.

**Files:** the canvas `https://claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr`. Copies saved to `.superpowers/sdd/policy-replacement/boards/`.

- [ ] **Step 1:** Download and find the boards of: the *Renewed* panel (`Cambia-scadenza`, `Cambia-scadenza-pc`, `Agenda-scadenza`, `Agenda-pannello-pc`), the payment panel (`Pagata`, `Pagata-pc`, `Agenda-pagamento`, `Agenda-pagamento-pc`), the card form (`Modifica`, `Modifica-pc`: check it is the card form), the import box (`Importa-scheda-pc`, `Proposta`, `Proposta-pc`). Confirm on the canvas which boards really show those panels.
- [ ] **Step 2:** Draw the choice (FormKit `togglebuttons`) in the *Renewed* panel (two choices; with *I changed company* no date and the button *Continue*) and in the payment panel (three choices; with *Same company, renewed* the field «New expiry date»). Draw the box «Cancel the policy X» (title · number · insurer, expiry) at the bottom of the card form and under the policy box of the import. Colours with `var(--…)`, phone and wide versions.
- [ ] **Step 3:** Publish (with `canvas.json` as `file_path` if heights change, see learnings), save the copies in the plan folder, and give the paths to Tasks 4–7.

### Task 1: The rule — which old policies a new one can replace

**Files:**
- Create: `app/Services/ReplaceablePolicies.php`
- Create: `app/Data/ReplaceablePolicyData.php`
- Test: `tests/Feature/Services/ReplaceablePoliciesTest.php`

**Interfaces:**
- Produces:
  - `ReplaceablePolicies::candidates(): Collection<int, InsurancePolicy>`: the policies of the family with status `active` or `expired` that the user can `update`, ordered by expiry. It reads the family through the trait (no `withoutGlobalScope`).
  - `ReplaceablePolicies::matching(array $values, ?int $except = null): Collection<int, InsurancePolicy>`, with `$values` = `array{type: ?string, vehicle_id: ?int, home_id: ?int, employment_id: ?int}`: the candidates with the same `type` and the same covered card. The covered column follows the type: `car` → `vehicle_id`, `home` → `home_id`, `health`/`accident`/`fund` → `employment_id`. Read the mapping from the `showWhen` of the fields of `InsurancePolicyCard` (one place). Other types → empty. A covered card left empty → empty.
  - `ReplaceablePolicyData::fromPolicy(InsurancePolicy $policy): self`, with `id: int`, `label: string` (title, then number and insurer when filled, joined with ` · `), `expiry_date: ?string` (`Y-m-d`), `type: string`, `vehicle_id: ?int`, `home_id: ?int`, `employment_id: ?int`. TypeScript: `App.Data.ReplaceablePolicyData`.

- [ ] **Step 1: Write the failing tests**

```php
it('offers the active and expired car policies of the same vehicle', function () { /* car policy A active, B expired on vehicle V; C cancelled on V; D on another vehicle */
    expect(app(ReplaceablePolicies::class)->matching(['type' => 'car', 'vehicle_id' => $v->id, 'home_id' => null, 'employment_id' => null])->pluck('id')->all())
        ->toEqualCanonicalizing([$a->id, $b->id]);
});
it('offers nothing for a type without a covered card', fn () => /* life */ expect(...->matching(['type' => 'life', ...]))->toBeEmpty());
it('offers nothing when the covered card is empty', ...);
it('leaves out the policy being saved', fn () => expect(...->matching([...], except: $a->id)->pluck('id'))->not->toContain($a->id));
it('offers nothing to a member', ...);              // actingAs member: candidates() empty
it('never offers a policy of another family', ...); // same vehicle id, other family
it('labels a policy with its number and insurer', fn () => expect(ReplaceablePolicyData::fromPolicy($p)->label)->toBe('RC Auto · 40/123 · Allianz'));
```

- [ ] **Step 2: Run** `… php artisan test tests/Feature/Services/ReplaceablePoliciesTest.php`. Expected: FAIL, class not found.
- [ ] **Step 3: Implement** the service and the data class as in Interfaces. Then run `typescript:transform`.
- [ ] **Step 4: Run** the tests again. Expected: PASS. Then PHPStan, Rector and Pint on the files.
- [ ] **Step 5: Commit** `Policies: the rule of the old policies a new one replaces`.

### Task 2: The replacement action

**Files:**
- Create: `app/Actions/Cards/ReplacePolicy.php`
- Test: `tests/Feature/Actions/ReplacePolicyTest.php`

**Interfaces:**
- Produces: `ReplacePolicy::handle(InsurancePolicy $new, Collection $old, ?Expense $payment = null): void`, with `$old` = `Collection<int, InsurancePolicy>`. The caller opens the transaction and has already checked permissions and family.
  For each old policy:
  1. it goes to `InsurancePolicyStatus::Cancelled`;
  2. its open automatic reminders of `insurance_policies.expiry_date` (`Reminder::query()->automaticFor(field, morph, [id])->where('status', Open)`) become `Done`, **before** the save (the reminder sync would otherwise remove them, see `UpdateDeadline`);
  3. if `$new` has no planned expense of its own (`card_type = insurance_policy`, `card_id = $new->id`), the old policy's planned expenses get `card_type`/`card_id` of the new one. Otherwise they get `end_date` = the old policy's expiry (or today if it has none), and keep their link.
  
  Last, `$payment` (when given) gets `card_type`/`card_id` of the new policy. Older payments are not touched.

- [ ] **Step 1: Write the failing tests**

```php
it('cancels the old policy and closes its renewal reminder', ...);   // status cancelled; reminder status done (not deleted)
it('moves the premium planned expense to the new policy', ...);      // planned card_id === new id, same title/amount/interval
it('moves only the payment given', ...);                             // payment card_id new; an older expense still old
it('ends the old premium when the new policy already has one', ...); // old planned end_date === old expiry, card_id still old
it('replaces two old policies at once', ...);
```

- [ ] **Step 2: Run.** Expected: FAIL, class not found.
- [ ] **Step 3: Implement** `handle` as in Interfaces.
- [ ] **Step 4: Run.** Expected: PASS, plus PHPStan, Rector and Pint.
- [ ] **Step 5: Commit** `Policies: the action that replaces an old policy with a new one`.

### Task 3: The card form saves the replacement; presets and the URL to it

**Files:**
- Modify: `app/Http/Requests/Cards/SaveCardRequest.php`, `app/Http/Controllers/CardController.php` (`create`, `store`)
- Create: `app/Support/PolicyReplacement.php`
- Test: `tests/Feature/Cards/ReplacePolicyFormTest.php`

**Interfaces:**
- Consumes: Task 1 `ReplaceablePolicies`, `ReplaceablePolicyData`; Task 2 `ReplacePolicy::handle`.
- Produces:
  - `PolicyReplacement::createUrl(InsurancePolicy $old, ?Expense $payment = null): string`: `route('cards.create', [...])` with `card=insurance_policy`, `title`, `type`, `vehicle_id`, `home_id`, `employment_id`, `person_id` of the old policy (empty ones left out), `start_date` = old `expiry_date` (`Y-m-d`, left out if empty), `replaces` = old id, `payment` = expense id when given.
  - Request fields (only for the type `insurance_policy`, only on create): `replace_policies` (`array`, `list`), `replace_policies.*` (`integer`), `replace_payment` (`nullable|integer`).
  - `SaveCardRequest::policiesToReplace(): Collection<int, InsurancePolicy>`: `InsurancePolicy::query()->findOrFail($ids)` (404 for another family). A validation error on `replace_policies` if one is not `active`/`expired` or the user cannot `update` it.
  - `SaveCardRequest::paymentToMove(): ?Expense`: `Expense::query()->findOrFail($id)` (404 for another family). It returns null (ignored) unless the expense's planned expense is linked to one of the policies to replace.
  - `CardController::store`: in `DB::transaction`, `SaveCard` then, for a policy with policies to replace, `ReplacePolicy::handle($card, $old, $payment)`. The new card is never in `$old` (it did not exist).
  - `CardController::create` for `insurance_policy` adds the props `replaceable: list<ReplaceablePolicyData>` (= `candidates()`) and `replaces: list<int>` (the `replaces` of the query, kept only if among the candidates) and `payment: ?int` (query `payment`). They are not page prop names that already exist in `HandleInertiaRequests::share()` (check).

- [ ] **Step 1: Write the failing tests**

```php
it('cancels the old policy when the new one is saved with it', ...);  // POST cards.store insurance_policy + replace_policies [old] → old cancelled, new active
it('moves the payment just made to the new policy', ...);           // + replace_payment
it('ignores a payment of another planned expense', ...);            // Review Focus 1: payment card_id unchanged
it('answers 404 for a policy of another family', ...);              // Review Focus 2: nothing saved
it('refuses a policy already cancelled', ...);                      // 422 on replace_policies; no new card
it('cancels nothing when the form sends no policy to replace', ...);
it('forbids a member', ...);                                        // store already 403 for a member: assert old untouched
it('prefills the new policy from the old one', ...);                // GET PolicyReplacement::createUrl($old) → values title/type/vehicle_id/person_id/start_date; replaces [old id]
it('offers only candidates', ...);                                  // replaces=<id of a cancelled policy> → replaces []
```

- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement** as in Interfaces. Then `typescript:transform`.
- [ ] **Step 4: Run** these tests and `tests/Feature/Cards/*`. Expected: PASS, plus PHPStan, Rector and Pint.
- [ ] **Step 5: Commit** `Card form: a new policy can cancel the old one and take its premium`.

### Task 4: *Renewed* in the Agenda and the Home offers *I changed company*

**Files:**
- Modify: `app/Data/AgendaEntryData.php`, `app/Services/Agenda.php` (near line 299, where `update_url` is built)
- Modify: `resources/js/components/fm/FmDeadlineDateSheet.vue`, `resources/js/components/fm/FmDeadlineDateSheet.test.ts`
- Modify: `lang/it.json`
- Test: the Agenda Pest test that covers `update_url` (find it: `grep -rln update_url tests/Feature`)

**Interfaces:**
- Consumes: Task 3 `PolicyReplacement::createUrl`.
- Produces: `AgendaEntryData::$replace_url: ?string` (last constructor parameter, default `null`): set only for the source `insurance_policies.expiry_date` when the user can `update` the policy and `create` `InsurancePolicy`. Otherwise null.
- Board: the copies from Task 0 (*Renewed* panel).

- [ ] **Step 1: Write the failing tests.** Pest: the policy deadline entry has `replace_url === PolicyReplacement::createUrl($policy)` for an admin, and null for a member and for a vehicle tax deadline. Vitest:

```ts
it('offers the choice of the company only for a policy', …)   // entry.replace_url set → radios 'Same company' (checked) and 'I changed company'; null → no radios
it('goes to the new policy form when the company changed', async () => { /* choose 'I changed company' → date field gone, button 'Continue'; submit → router.visit(entry.replace_url); router.put not called */ })
it('still renews with the same company', …)                    // default → router.put(update_url, { date, renewed: true })
```

- [ ] **Step 2: Run** Pest and `npm run test -- FmDeadlineDateSheet`. Expected: FAIL.
- [ ] **Step 3: Implement.** In the sheet, a FormKit `togglebuttons` `company` (`same` | `changed`, default `same`) shown when `renewed && entry.replace_url !== null`. With `changed`, hide the datepicker, the button says `$t('Continue')`, and the submit calls `router.visit(entry.replace_url)`. Reset to `same` when the sheet opens again. Add the Italian texts. Then `typescript:transform`.
- [ ] **Step 4: Run** the tests, plus those of `FmAgendaPanel`, `Agenda` and `Home`. Expected: PASS, plus types and lint.
- [ ] **Step 5: Commit** `Renewed: I changed company opens the new policy form`.

### Task 5: *I paid it* with the choice of the policy

**Files:**
- Create: `app/Support/PolicyRenewal.php`, `app/Data/PolicyRenewalData.php`
- Modify: `app/Data/DeadlineData.php` (`fromDeadline`), `app/Http/Requests/Budget/RecordPaymentRequest.php`, `app/Http/Controllers/Budget/DeadlineController.php` (`payment`)
- Modify: `resources/js/components/fm/FmPayForm.vue`, `resources/js/components/fm/FmPayForm.test.ts`, `lang/it.json`
- Test: `tests/Feature/Budget/PaymentRenewsPolicyTest.php`

**Interfaces:**
- Consumes: Task 3 `PolicyReplacement::createUrl`; `UpdateDeadline::handle(DeadlineSource, Model, CarbonImmutable, bool $renewed, CarbonImmutable $today)`; `DeadlineSources::find('insurance_policies.expiry_date')`.
- Produces:
  - `PolicyRenewal::policyFor(Deadline $deadline, CarbonImmutable $today): ?InsurancePolicy`: the policy when the planned item is a `PlannedExpense` with `card_type = insurance_policy`, the policy is `active` or `expired`, `expiry_date` is not null and `<= $today + 30 days`, and the user can `update` it and `create` `InsurancePolicy`. Otherwise null.
  - `PolicyRenewalData { policy_id: int, policy: string (label as ReplaceablePolicyData), expiry_date: string, proposed_expiry: string }`, with `proposed_expiry` = expiry + 1 year.
  - `DeadlineData::$renewal: ?PolicyRenewalData` (last parameter, default `null`), filled in `fromDeadline` from `PolicyRenewal::policyFor`. Both the due date page and the Agenda's `payment` prop go through `fromDeadline`.
  - Request: `renewal` (`sometimes`, in `only,same,changed`, default `only`), `new_expiry` (`required_if:renewal,same`, `date_format:Y-m-d`), `policy_expiry` (`required_unless:renewal,only`, `date_format:Y-m-d`: the expiry the page showed). With `same`/`changed` and `PolicyRenewal::policyFor` null → a validation error on `renewal` (Review Focus 3).
  - `DeadlineController::payment`, in one `DB::transaction`: `RecordPayment` as today, then:
    - `same`, and the policy's expiry still equals `policy_expiry` → `UpdateDeadline::handle($source, $policy, new_expiry, renewed: true, $today)`. With a different expiry, the policy is left alone (Review Focus 4).
    - `changed` → after the transaction, a redirect to `PolicyReplacement::createUrl($policy, $expense)` (also from the Agenda) with the toast «Payment recorded.».
    - `only` → today's behaviour.

- [ ] **Step 1: Write the failing tests**

```php
it('offers the renewal only near the expiry of the policy', ...); // expiry today+30 → renewal set; today+31 → null; expired last year → set; cancelled → null
it('offers nothing to a member', ...);                           // member: renewal null; posting renewal=same → 422 and no expense
it('records only the payment by default', ...);                  // no renewal field → policy expiry unchanged
it('renews the policy with the same company', ...);              // same + new_expiry → expense + expiry moved + status active + old reminder done
it('does not renew twice', ...);                                 // policy_expiry ≠ current expiry → expense recorded, expiry untouched
it('opens the new policy form when the company changed', ...);   // changed → redirect createUrl($policy, $expense); old policy still active/expired
it('refuses a renewal for a due date without one', ...);         // planned not linked to a policy + renewal=same → 422, no expense
```

Vitest (`FmPayForm.test.ts`):

```ts
it('shows the choice only with a renewal', …)                 // deadline.renewal null → no 'Only the payment'
it('starts on only the payment', …)                           // 'Only the payment' checked; submit body has renewal 'only'
it('asks the new expiry with the same company', …)            // choose 'Same company, renewed' → datepicker 'New expiry' = proposed_expiry; body { renewal: 'same', new_expiry, policy_expiry }
it('sends changed when the company changed', …)               // body renewal 'changed', policy_expiry
```

- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement** as in Interfaces. In `FmPayForm`, a `togglebuttons` `renewal` under the amount when `deadline.renewal`, plus a datepicker `new_expiry` with `same`. `reset()` puts back `only`. Add the Italian texts. Then `typescript:transform`.
- [ ] **Step 4: Run** the tests, plus those of `FmPaySheet`, `FmAgendaPanel`, `budget/Deadline`, `Agenda` and the Pest tests of `DeadlineController`. Expected: PASS, plus PHPStan, Rector, Pint, types and lint.
- [ ] **Step 5: Commit** `I paid it: same company renews the policy, another company opens the new policy form`.

### Task 6: The box «Cancel the policy X» in the card form

**Files:**
- Create: `resources/js/lib/policies.ts`, `resources/js/lib/policies.test.ts`
- Modify: `resources/js/components/fm/FmCardForm.vue` (+ test), `resources/js/pages/cards/EditCard.vue` (+ `EditCard.test.ts`), `lang/it.json`

**Interfaces:**
- Consumes: the props `replaceable`, `replaces`, `payment` of Task 3.
- Produces:
  - `replaceableFor(policies: App.Data.ReplaceablePolicyData[], values: Record<string, unknown>): App.Data.ReplaceablePolicyData[]`: the same rule as `ReplaceablePolicies::matching` (type, covered column by type, ids compared as numbers), plus every policy in `replaces` whatever its covered card (the *Renewed* path works for any type). Signature: `replaceableFor(policies, values, replaces: number[])`.
  - `FmCardForm` gets a scoped slot `after` with `{ values: Record<string, unknown> }` (the live values), drawn above the buttons. Changing a slot of a `fm/` component updates its board (Task 0 covers it).
  - `EditCard` (only when `card === null` and `type.type === 'insurance_policy'`): in the slot, a FormKit `checkbox` with one option per `replaceableFor(...)` policy, label `$t('Cancel the policy :name', { name: label })`, help with the expiry (`useFormat().date`). Ticked at the start: the ids in `replaces`. The payload adds `replace_policies` (only ids still offered: Review Focus 5) and `replace_payment` (`payment`, only when `replace_policies` is not empty).

- [ ] **Step 1: Write the failing tests**

```ts
// policies.test.ts
it('offers the policies of the same vehicle and type', …)
it('offers nothing when the type has no covered card', …)
it('keeps the policy to replace whatever the covered card', …)
// EditCard.test.ts
it('ticks the old policy when it comes from a renewal', …)       // replaces [7] → checkbox 'Cancel the policy RC Auto · …' checked
it('leaves the box unticked for a new policy by hand', …)
it('drops the old policy when the type changes', …)              // Review Focus 5: choose type 'home' → box gone; router.post body without replace_policies
it('sends the payment with the policy to replace', …)            // body { replace_policies: [7], replace_payment: 33 }
```

- [ ] **Step 2: Run** `npm run test -- policies EditCard FmCardForm`. Expected: FAIL.
- [ ] **Step 3: Implement** as in Interfaces, with the Italian texts.
- [ ] **Step 4: Run** the tests. Expected: PASS, plus types and lint.
- [ ] **Step 5: Commit** `New policy form: the box that cancels the old policy`.

### Task 7: *Import* offers to cancel the old policy

**Files:**
- Modify: `app/Data/Reading/RecordDraftData.php`, `app/Services/Reading/RecordDrafts.php` (`policy()`), `app/Data/Reading/ImportChoices.php`, `app/Actions/Reading/ImportFile.php`, the request that reads the import choices (`grep -rn "ImportChoices::fromArray" app`)
- Modify: `resources/js/components/documents/ImportAlsoDoBoxes.vue` (+ its test) and the page code that builds the import payload (follow where `records` is sent), `lang/it.json`
- Test: `tests/Feature/Reading/ImportReplacesPolicyTest.php`

**Interfaces:**
- Consumes: Task 1 `ReplaceablePolicies::matching`, `ReplaceablePolicyData`; Task 2 `ReplacePolicy::handle`.
- Produces:
  - `RecordDraftData::$replaces: list<ReplaceablePolicyData>` (last parameter, default `[]`): for a new policy (`existing === null`), `matching($values)` of the draft. It is empty for a member, because `candidates()` already filters on `update`.
  - `ImportChoices::$replacePolicies: list<int>` from raw `replace_policies` (ints only, like the other lists).
  - `ImportFile`: after `SaveRecords::before`, when a policy was created and `replacePolicies` is not empty, `ReplacePolicy::handle($policy, $old)` with `$old` = the ids that are both in `replacePolicies` and in the draft's `replaces`, read again with `matching` (a stale id is ignored). It runs in the import's transaction, and after `movement()`, so a planned expense the import just created for the new policy counts (Task 2, rule 3).
  - Page: under the policy box, one ticked checkbox per `replaces` entry, «Cancel the policy :name». The payload sends `replace_policies` with the ticked ids, only while the policy box itself is ticked.

- [ ] **Step 1: Write the failing tests**

```php
it('proposes to cancel the old policy of the same vehicle', ...);          // reading of a car policy, new number, vehicle with an active policy → draft replaces [old]
it('cancels the old policy when the box is ticked', ...);                 // import with replace_policies [old] → old cancelled, premium planned moved
it('ends the old premium when the import makes a new one', ...);          // import also creates a planned expense for the new policy → old planned end_date set
it('cancels nothing for a member', ...);                                  // member: draft replaces []; posting replace_policies → old untouched
it('ignores an id not proposed', ...);                                    // replace_policies [policy of another vehicle] → untouched
```

Vitest: `it('ticks the old policy under the new policy box', …)`, `it('sends no policy to cancel when the policy box is unticked', …)`.

- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement** as in Interfaces. A test sends the import as the page does (every field, `links: []`, see learnings). Then `typescript:transform`.
- [ ] **Step 4: Run** these tests, the other `tests/Feature/Reading/Import*` tests and the Vitest tests of the import page. Expected: PASS, plus all the checks.
- [ ] **Step 5: Commit** `Import: a new policy can cancel the old one of the same vehicle`.

### Task 8: Check in the browser, docs, learnings (coordinator)

- [ ] **Step 1:** Ask the owner for Vite if it is off. With headless Chromium and a Bianchi account, try A (Agenda → *Renew the policy* → *Renewed* → *I changed company* → form → save), C (*I paid it* on the premium, all three choices) and B. Compare with the Task 0 boards.
- [ ] **Step 2:** Run `project-docs`: the functional note of policies and Agenda, with what changes for the person.
- [ ] **Step 3:** Add to `.claude/learnings.md` what was found while working, if anything. Commit.
