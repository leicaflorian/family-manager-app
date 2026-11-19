# A person with many documents — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** *A person* (`family/Person.vue`) follows the boards *Persona-tanti* and *Persona-tanti-pc*: reminders in a *To do* box, every list a few rows at a time with *Show N more · N left*.

**Architecture:** The two document lists come from the server a page at a time (`DocumentRows::page()`, `Inertia::merge`, partial reload with `preserveUrl: true`, as *Link a document* does). Reminders and linked records are already sent whole and are revealed in the browser 5 / 3 at a time. A new `FmShowMore` (the outline button in the footer of a box) and a `count` prop of `FmCard` serve all four lists.

**Tech Stack:** Laravel 12 + Inertia 3 + Vue 3 (TS), Pest, Vitest (`vite-plus/test`), Testing Library.

**Spec:** `docs/superpowers/specs/2026-11-19-person-many-documents-design.md`

**Boards** (downloaded by the coordinator, read them before writing UI):
`/private/tmp/claude-501/-Users-florianleica-Dev-family-manager-app/20ef599f-05aa-42ef-9646-bccba04754b5/scratchpad/artifact-files/fa8386b2-5a87-4a4e-b051-5d03c327ad7b/project/` → `Persona-tanti-pc.dc.html`, `Persona-tanti.dc.html`, `Persona-pc.dc.html`, `Basi.dc.html`.

## Global Constraints

- Rules of `AGENTS.md`: TDD, `declare(strict_types=1)`, PHPDoc/TSDoc on everything, English in code, every visible text through `$t`/`transChoice` with the Italian in `lang/it.json` (added as text before the final `}`, never rewritten with a JSON parser), text styles of the design system (no `font-*`/`leading-*` next to a `text-*` style), `edge-soft` borders, classes outside the template through `cn()`.
- **No *Pinned* (*In primo piano*)**: the board has it, the code does not (decision of the owner, 19/11/2026).
- Page sizes: To do **5**, Personal documents **5**, Other documents **10**, Links **3** per tab.
- Button text, exactly as the board (Italian): `Mostra altre 5 · ne restano 19` (tasks, cards), `Mostra altri 5 · ne restano 31` (documents). The number shown is `min(step, left)`; with one left `ne resta 1`.
- Only the checks of touched files before each commit (Pest of the touched tests with `-e DB_DATABASE=testing_<name>`, Vitest of touched files, PHPStan, Rector, Pint, types, lint). Another session has open changes in `resources/js/components/fm/FmMovementPanel.vue` and `pages/budget/Movement*`: commit with paths (`git commit -- <files>`), never `git add -A`.

## Review Focus

- **A *Show more* while the list is short**: with ≤ step rows no button at all (lists of 0, exactly 5, exactly 10).
- **The last page**: after the last *Show more* the button disappears and no row is duplicated (`Inertia::merge` appends; `next` null).
- **Switching tab in Links** starts the new tab from its first 3 rows, not from what the previous tab had revealed.
- **Another family's person** still answers 404, also with `?personal_page=2`.
- **Empty personal documents** still shows the box with «No personal documents yet.» and the count `0` is not drawn.

---

### Task 1: The server sends the documents a page at a time

**Files:**
- Modify: `app/Queries/DocumentRows.php` (`page()`)
- Modify: `app/Http/Controllers/PersonController.php` (`show()`)
- Modify: `app/Services/LinkableDocuments.php` (only the `@return` shape if PHPStan asks)
- Test: `tests/Feature/Family/PeopleOrganizationsTest.php`, `tests/Unit/…DocumentRowsTest` if one exists (else in the feature test)

**Interfaces:**
- Produces: `DocumentRows::page(array $filters, int $page, int $perPage): array{documents: list<DocumentData>, next: ?int, total: int}`.
- Produces: props of `family/Person`: `personal` (merge, 5 per page, request param `personal_page`), `personalNext: ?int`, `personalTotal: int`, `documents` (merge, 10 per page, param `documents_page`), `documentsNext: ?int`, `documentsTotal: int`; `reminders`, `remindersCount`, `links`, `person` unchanged; **`expiring` removed**. Constants `PersonController::PERSONAL_PER_PAGE = 5`, `DOCUMENTS_PER_PAGE = 10`. Each document prop is a closure so a partial reload computes only what it asks for.

- [ ] **Step 1: Write the failing tests** in `PeopleOrganizationsTest.php`:
  - update `the page of Giulia shows …`: no `expiring` (`->missing('expiring')`), `personalTotal` 2, `personalNext` null, `documentsTotal` 2, `documentsNext` null.
  - `test('the personal documents of a person come 5 at a time, the next page on request')`: give Giulia 7 personal documents (factory, `person_id`, no link) → first visit `has('personal', 5)`, `personalNext` 2, `personalTotal` 7; a partial reload (`X-Inertia-Partial-Data: personal,personalNext,personalTotal`, `?personal_page=2`) → `has('personal', 2)`, `personalNext` null, and the 2 are not among the first 5.
  - `test('the other documents of a person come 10 at a time')`: 12 linked documents → `has('documents', 10)`, `documentsNext` 2, `documentsTotal` 12.
  - `test('a person of another family answers 404 also with a page')`: `?personal_page=2` on the other family's person → 404.
- [ ] **Step 2: Run** `docker exec -u sail -e DB_DATABASE=testing_person family-manager-app-laravel.test-1 php artisan test tests/Feature/Family/PeopleOrganizationsTest.php` — expect FAIL (`expiring` present, no `personalTotal`).
- [ ] **Step 3: Implement**: `total` from the paginator in `page()`; in `show()` use `max(1, $request->integer('personal_page'))` (learning: `integer('x', 1)` gives 0 on empty) and `Inertia::merge(fn () => …)`; drop the `expiring` prop and its `byExpiry` use only if nothing else calls it (`grep -rn byExpiry app`).
- [ ] **Step 4: Run** the same command plus `tests/Feature` tests that use `LinkableDocuments` (`grep -rln linkable tests`) — PASS. PHPStan, Rector, Pint on the touched files.
- [ ] **Step 5: Commit** `People: the documents of a person come a page at a time`.

### Task 2: `FmShowMore` and the number beside the title of `FmCard`

**Files:**
- Create: `resources/js/components/fm/FmShowMore.vue`, `FmShowMore.test.ts`
- Modify: `resources/js/components/fm/FmCard.vue`, `FmCard.test.ts`
- Modify: `resources/design/boards.json` (`"FmShowMore": "Basi"`)
- Modify: `lang/it.json`

**Interfaces:**
- Produces: `FmShowMore` props `{ label: string; loading?: boolean }`, emits `more: []`. Draws the board's footer: a block with `px-4 py-3` and a top `border-divider` line, inside an `FmButton variant="outline"` full width with the `ChevronDown` icon and `label`; `loading` disables it. `@group basics`. It does not compose the text: the caller passes it (the Italian gender changes).
- Produces: `FmCard` prop `count?: number` — drawn after the title in `text-body-sm text-ink-soft` (board: 14 px, 400), aligned on the baseline; not drawn when `undefined` or `0`.
- Produces translation keys (English key → Italian), used by Tasks 3–4 with `transChoice(key, left, { shown })`:
  - `{1} Show :shown more · 1 left|[2,*] Show :shown more · :count left` → `{1} Mostra altre :shown · ne resta 1|[2,*] Mostra altre :shown · ne restano :count` (tasks and cards)
  - `{1} Show :shown more documents · 1 left|[2,*] Show :shown more documents · :count left` → `{1} Mostra altri :shown · ne resta 1|[2,*] Mostra altri :shown · ne restano :count`
  - and a helper `showMoreLabel(left: number, step: number, documents?: boolean): string` in `resources/js/lib/showMore.ts` with its test.

- [ ] **Step 1: Write the failing tests**: `FmShowMore` shows a button with the given name, emits `more` on click, is disabled with `loading`; `FmCard` with `count: 42` shows «42» next to the heading inside the `h2`, with `count: 0` shows nothing; `showMoreLabel(19, 5)` → `Show 5 more · 19 left`, `showMoreLabel(3, 5, true)` → `Show 3 more documents · 3 left`, `showMoreLabel(1, 5)` → `Show 1 more · 1 left`.
- [ ] **Step 2: Run** `docker exec -u sail family-manager-app-laravel.test-1 npx vp test run resources/js/components/fm/FmShowMore.test.ts resources/js/components/fm/FmCard.test.ts resources/js/lib/showMore.test.ts` — FAIL.
- [ ] **Step 3: Implement** the component, the prop, the helper, the `boards.json` row and the Italian lines.
- [ ] **Step 4: Run** the same plus `resources/js/design/` (boards, text styles, edges) and `TranslationsTest` (`php artisan test tests/Feature/TranslationsTest.php`) — PASS; types and lint.
- [ ] **Step 5: Commit** `Components: FmShowMore, and the number beside the title of a card`.

### Task 3: Links three at a time

**Files:**
- Modify: `resources/js/components/fm/FmLinkedRecords.vue`, `FmLinkedRecords.test.ts`

**Interfaces:**
- Consumes: `FmShowMore`, `showMoreLabel` (Task 2).
- Produces: no new prop. Shows the first 3 records of the open tab; `FmShowMore` reveals 3 more; switching tab resets to 3. Applies to *An organization* too.

- [ ] **Step 1: Write the failing tests**: with a tab of 8 records 3 links are shown and the button «Show 3 more · 5 left»; after a click 6 links and «Show 2 more · 2 left»; after another all 8 and no button; switching to another tab and back shows 3 again; a tab of exactly 3 has no button.
- [ ] **Step 2: Run** the file — FAIL.
- [ ] **Step 3: Implement** (a `shown` ref reset in a `watch` on `chosen`).
- [ ] **Step 4: Run** it plus `pages/family/Person.test.ts` and `Organization.test.ts` — PASS.
- [ ] **Step 5: Commit** `Links: three records at a time`.

### Task 4: The page of a person in boxes

**Files:**
- Modify: `resources/js/pages/family/Person.vue`, `Person.test.ts`
- Modify: `resources/js/test/fixtures.ts` only if a new fixture is needed
- Modify: `lang/it.json`

**Interfaces:**
- Consumes: props of Task 1, `FmCard count`, `FmShowMore`, `showMoreLabel`.
- Layout, from the boards (main column, top to bottom): the **first late reminder** as the red `FmNotice` (tone `danger`, link *Agenda* as today); **To do** (`FmCard` title `To do`, `count` = `remindersCount`, `FmSectionLink` *Open in the Agenda* → `remindersIndex({ query: { person } })`, rows `FmRow` with the area tile, title, subtitle = `reminderText()`, link to the same Agenda; 5 at a time); **Personal documents** (`count` = `personalTotal`, `FmSectionLink` *Add* → the upload page `upload()`, open to every account; rows `FmDocumentRow`; `FmShowMore` when `personalNext`); **Links**. Side column: data, the notice of no account, **Other documents** (`count` = `documentsTotal`, *Open in the Archive* → documents index with `?person=`; `FmShowMore` when `documentsNext`), Notes. *Expiring* goes. No box *To do* without reminders.
- Loading more: `router.reload({ data: { personal_page: personalNext }, only: ['personal', 'personalNext', 'personalTotal'], preserveUrl: true })` (same for `documents`), with `loading` true until `onFinish`. *N left* = total − shown.
- New keys: `To do` (exists? `grep '"To do"' lang/it.json`), `Open in the Agenda` → `Apri nell'Agenda`, `Open in the Archive` → `Apri nell'Archivio`, `Add` (likely exists).

- [ ] **Step 1: Write the failing tests** (props as the controller sends them, no `expiring`): 7 reminders → one red notice for the late one, a *To do* region with «7», 5 rows and «Show 2 more · 2 left», after the click 7 rows; *Personal documents* with `personalTotal: 7`, `personalNext: 2` shows «7» and «Show 2 more documents · 2 left», and the click calls `router.reload` with `data: { personal_page: 2 }`, `only` of the three props and `preserveUrl: true` (spy `router.reload`); *Other documents* «Open in the Archive» links to `/documents?person=<id>`; no *Expiring* heading; with no reminders no *To do* box; empty personal documents shows «No personal documents yet.» and no number. Update the existing tests that pass `expiring` or expect it.
- [ ] **Step 2: Run** `npx vp test run resources/js/pages/family/Person.test.ts` — FAIL.
- [ ] **Step 3: Implement** following `Persona-tanti-pc.dc.html` (wide) and `Persona-tanti.dc.html` (phone); update the TSDoc at the top of the page.
- [ ] **Step 4: Run** it, `TranslationsTest`, `resources/js/design/`, types, lint — PASS; `sail artisan typescript:transform` is not needed (no Data class changed).
- [ ] **Step 5: Commit** `People: the page of a person in boxes, a few rows at a time`.

### Task 5 (coordinator): boards, browser, docs

- [ ] A fork updates the board *Basi* (card of `FmShowMore`, `count` in the card of `FmCard`) and republishes the canvas.
- [ ] Browser check with Playwright on a person with many documents (Grazia in the dev database, or a seeded person on a test database), wide and phone: boxes, *Show more* to the end, no duplicates.
- [ ] *Decisions taken* below; functional docs with `project-docs`.

## Decisions taken

- *In primo piano* of *Persona-tanti* not in the code (owner, 19/11/2026); the board keeps it.
- The block *Expiring* goes, as in the boards: renewals are in *To do*.
- `FmShowMore` is a new component: `FmButton` alone would repeat the footer block and the label in four lists.
- *Add* is shown to every account: every account can upload.
