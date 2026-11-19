# The page of a movement with its documents — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** an expense or income opens on its own page on every layout, with the documents column of the cards beside the form (link, unlink, upload through *Import*) and the fields in pairs.

**Architecture:** the server side of card documents is shared with movements: a request concern for files and link/unlink, a service for the *Link a document* pages, and `PresetLink` learns movements for uploads and *Import*. On the client `FmCardFiles` loses its card-only prop and is used by the movement page; `FmMovementPanel` loses its column modes and sends files and links with the movement.

**Tech Stack:** Laravel 12, PHP 8.5, Pest; Vue 3 + Inertia 3, FormKit Pro, Vitest + Testing Library.

**Spec:** `docs/superpowers/specs/2026-11-18-movement-page-documents-design.md`

**Boards:** `.superpowers/sdd/2026-11-18-movement-page/boards/project/` — `Movimento.dc.html`, `Movimento-pc.dc.html`, `Nuova-spesa.dc.html`, `Nuova-spesa-pc.dc.html`, `Entrata.dc.html`, `Movimenti-pc.dc.html`, `Movimenti-filtri-pc.dc.html`, `Movimenti-filtri-dettaglio-pc.dc.html`, and `Modifica-pc.dc.html` / `Modifica.dc.html` (the card form, reference of the column).

## Global Constraints

- AGENTS.md rules hold: TDD, `declare(strict_types=1)`, PHPDoc/TSDoc on everything, English code with Italian in `lang/it.json` (added as text before the final `}`, never through a JSON parser), PHPStan 9, no `any`, classes through `cn()` outside templates, `wide:` / `not-wide:` variants (never `lg:`), `h-control` for controls.
- Pest with its own database: `docker exec -u sail -e DB_DATABASE=testing_movpage family-manager-app-laravel.test-1 php artisan test <files>` (written in full), one Pest at a time in the main folder.
- Only the checks of the touched files before each commit (tests of the file and of its users, PHPStan, Rector, Pint, `npm run types`, `npm run lint`).
- A form that sends files sends a change as `POST` with `_method` (`withMethod()`).
- A document of another family answers 404 before anything is saved; a movement of another family answers 404.
- Helper functions in Pest files have a name unique in all `tests/` (start with the file name).

## Review Focus

1. A receipt uploaded from an expense and imported with the proposal unchanged must not create a second expense: the import's movement target for a file uploaded from a movement is that movement. (Task 1)
2. After *Save* the person lands back on the list they came from, with its filters (`?from=`), not on the bare month. (Task 2, Task 5)
3. Unsaved files or links count as unsaved changes: leaving the page with a chosen file asks *Leave without saving?*. (Task 4)
4. An income has no card: its page shows the column too, and the import from it goes back to the income page. (Task 1, Task 5)
5. A `member` links, unlinks and uploads from an expense; a document of another family in `link_documents` answers 404 and saves nothing. (Task 2)

---

### Task 1: Uploads and *Import* know a movement as where files come from

**Files:**
- Modify: `app/Support/PresetLink.php`, `app/Http/Controllers/ImportController.php`, `app/Http/Controllers/UploadController.php`, `app/Data/PendingFileData.php`, `app/Support/ImportPage.php` (only if it calls `PresetLink::query`), the class that resolves the `auto` movement target of an import (find it from `ImportChoices::$targets['movement']` → `ImportFile`/`MovementDraft`)
- Modify: `resources/js/lib/documents.ts`, `resources/js/pages/documents/Import.vue`, `resources/js/pages/documents/Upload.vue`
- Test: `tests/Unit/Support/PresetLinkTest.php` (create or extend), `tests/Feature/Reading/ImportControllerTest.php` (or the existing import feature test), `resources/js/lib/documents.test.ts`, `resources/js/pages/documents/Import.test.ts`

**Interfaces:**
- Produces: `PresetLink::movement(Expense|Income $movement): array{type: string, id: int}` (type `expense`/`income`); `PresetLink::fromUpload(mixed $link): ?array{type: string, id: int}` (a card of the family as `from()`, or an expense/income of the family); `PresetLink::findUpload(string $type, int $id): ?array`; `PresetLink::route(array{type: string, id: int} $link): string` (URL of `cards.show` or `budget.movements.show` with `kind`/`id`). `PresetLink::from()` / `find()` stay card-only (the card of an expense uses them).
- Produces (TS): `presetUrl(link: DocumentLink): string` in `resources/js/lib/documents.ts` — `budget.movements.show` for `expense`/`income`, `cards.show` otherwise.

- [ ] **Step 1: Write the failing tests**
  - `PresetLinkTest`: `fromUpload('expense:<id>')` of the family → `['type' => 'expense', 'id' => <id>]`; of another family → null; `fromUpload('income:<id>')` works; `from('expense:<id>')` stays null; `route(['type' => 'income', 'id' => 5])` ends with `/budget/movements/income/5`.
  - Import feature test: `GET import?files=<id>&link=expense:<id>` gives `presetLinks` `[{type: 'expense', id}]` and the file's `links` (a file stored with that link) contain it; `POST import?link=expense:<id>` with everything imported redirects to `budget.movements.show` of that expense; it does not offer *Create the card* (`offersCard` false).
  - Review Focus 1: a file stored with link `expense:<id>`, imported with the movement target left `auto`, links the document to that expense and the count of expenses does not change.
  - `documents.test.ts`: `presetUrl({type: 'expense', id: 3})` → `/budget/movements/expense/3`; `presetUrl({type: 'vehicle', id: 2})` → the card URL.
  - `Import.test.ts`: with `presetLinks: [{type: 'income', id: 4}]` the × leads to `/budget/movements/income/4`.
- [ ] **Step 2: Run them, see them fail** (Pest with `testing_movpage`, `npx vp test run <files>` in the container).
- [ ] **Step 3: Implement.** `ofFile()` and `query()` use `findUpload()`/`fromUpload()`; `ImportController` and `UploadController` read the link with `fromUpload()` and the store of the import redirects with `PresetLink::route()`; the `auto` target of the movement is the file's movement when its `link_type` is `expense`/`income` and the kind matches. `Import.vue`/`Upload.vue` use `presetUrl()` instead of `showCard.url(preset)`.
- [ ] **Step 4: Run the tests, see them pass**; PHPStan, Rector, Pint on the touched files; `npm run types`, `npm run lint`.
- [ ] **Step 5: Commit** `Import: files can come from an expense or an income`.

### Task 2: The server saves a movement with its documents and opens on its own page

**Files:**
- Create: `app/Http/Requests/Concerns/LinksDocuments.php` (trait: rules `link_documents`, `link_documents.*`, `unlink_documents`, `unlink_documents.*`; `documentsToLink()`, `documentsToUnlink()`, moved from `SaveCardRequest`), `app/Services/LinkableDocuments.php` (moved from `CardController::linkable()`, with `LINKABLE_PER_PAGE`)
- Modify: `app/Http/Requests/Cards/SaveCardRequest.php`, `app/Http/Controllers/CardController.php`, `app/Actions/Cards/LinkCardDocuments.php` (rename to `app/Actions/Documents/LinkDocuments.php`, same `handle(Model $record, Collection $link, Collection $unlink)`, works on any model with `documents()`), `app/Http/Requests/Budget/SaveMovementRequest.php` (use `UploadsFiles` with `filesRules(required: false)` and `filesMessages()`, `LinksDocuments`, a `from` field `['nullable', 'string']`), `app/Http/Controllers/Budget/MovementController.php`, `app/Http/Requests/Budget/MovementIndexRequest.php` (drop `movement`), `resources/js/pages/budget/Movements.vue` (rows always to the page with `?from=`, *New movement* to the create page, no panel in the column; the selected-movement code and its tests go)
- Test: `tests/Feature/Budget/MovementControllerTest.php` (or the existing movements feature tests), `tests/Feature/Cards/*` that cover linking (they must stay green), `resources/js/pages/budget/Movements.test.ts`

**Interfaces:**
- Consumes: `PresetLink::movement()`, `ImportPage::open()` (Task 1).
- Produces: props of `budget/Movement` — existing ones plus `back: ?string` (`LocalPath::of(?from)`), `linkable`, `linkableNext`, `linkableSearch`, `hasLinkable` (filter `not_record` = the movement when saved), `canDeleteDocuments: bool`. `budget/Movements` loses `selected`, `occurrences`, `options`. Body of store/update accepts `files[]`, `link_documents[]`, `unlink_documents[]`, `from`.

- [ ] **Step 1: Write the failing tests**
  - store with `link_documents: [<doc>]` links it; update with `unlink_documents` unlinks it; a document of another family in `link_documents` → 404, nothing saved.
  - store/update with `files: [UploadedFile::fake()->create('scontrino.pdf', 100, 'application/pdf')]` → redirect to `import?files=<id>&link=expense:<id>`, the file waits (`to_sort`) with `link_type` `expense`.
  - without files, store/update with `from=/budget/movements?month=2026-10&kind=expense` redirects there; with a `from` outside the app (`https://x.test`) redirects to `budget.movements.index` of the month, without `movement`.
  - a `member` can link and upload on update (Review Focus 5).
  - `show` sends `linkable`, `hasLinkable`, `canDeleteDocuments`, `back` from `?from=`; `create` the same with `hasLinkable` over all documents.
  - `index` no longer sends `selected`.
  - `Movements.test.ts`: on the computer a row links to `/budget/movements/expense/<id>?from=<current list url>`; *New movement* goes to the create page; no movement panel beside the list.
  - the card tests that link/unlink with the save still pass.
- [ ] **Step 2: Run them, see them fail.**
- [ ] **Step 3: Implement** as the interfaces say. Store/update in a transaction: save, then `LinkDocuments::handle()`; then with files `FileStore::store($file, toSort: true, link: PresetLink::movement($movement))` and `ImportPage::open($ids, 'expense:<id>')`; `back_to_card` keeps priority without files. `saved()` → `LocalPath::of(from)` or the month. Run `sail artisan typescript:transform` if a data class changes.
- [ ] **Step 4: Run the tests, see them pass**; static checks on the touched files.
- [ ] **Step 5: Commit** `Budget: a movement saves its documents and files, and opens on its own page`.

### Task 3: `FmCardFiles` works for any record

**Files:**
- Modify: `resources/js/components/fm/FmCardFiles.vue`, `resources/js/components/fm/FmCardDocumentActions.vue`, `resources/js/pages/cards/EditCard.vue`
- Test: `FmCardFiles.test.ts`, `FmCardDocumentActions.test.ts`, `resources/js/pages/cards/EditCard.test.ts`

**Interfaces:**
- Produces: `FmCardFiles` without the `card` prop (rows of linked documents always show `FmCardDocumentActions` in form mode); `FmCardDocumentActions` with `card?: {type: string; id: number}`, required only out of the form (its *Unlink* request needs it). TSDoc of both says "a card or a movement".

- [ ] **Step 1: Write the failing tests**: `FmCardFiles` rendered without `card` with a linked document shows its menu and *Unlink* adds its id to `unlink`; `FmCardDocumentActions` with `inForm` and no `card` renders and emits `unlink`.
- [ ] **Step 2: Run, see them fail.**
- [ ] **Step 3: Implement**; `EditCard.vue` stops passing `card` to `FmCardFiles`.
- [ ] **Step 4: Run the tests of the two components and of `EditCard`/`Card` pages, types, lint.**
- [ ] **Step 5: Commit** `Components: the documents column of the form is not tied to cards`.

### Task 4: `FmMovementPanel` with the fields in pairs, sending files and links

**Files:**
- Modify: `resources/js/components/fm/FmMovementPanel.vue`, `lang/it.json` if a text changes
- Test: `resources/js/components/fm/FmMovementPanel.test.ts`

**Interfaces:**
- Consumes: Task 2 body fields.
- Produces: props `compact` and `embedded` and `defineExpose({ close, confirmLeave })` removed (only the column of *Movements* used them); new models `files: File[]`, `link: App.Data.DocumentData[]`, `unlink: number[]` (`defineModel`), and prop `back?: string | null` sent as `from`. The panel no longer renders the *Documents* list (the page renders `FmCardFiles`).

- [ ] **Step 1: Write the failing tests**
  - expense (board *Movimento*): the pairs *Bank account | Category*, card field («Riferito a») *| Holder*, *Planned expense | Due date* sit in the same grid row (assert both fields are inside the same `div.grid-cols-2`); *Organization* and *Commission* are not in a pair; without a planned item *Planned expense* is alone in its half row.
  - income (board *Entrata*): *Incassata il | Importo*, *Conto | Ente*, *Tipo* full width, *Intestatario | Rapporto di lavoro*, *Entrata fissa | Scadenza*.
  - *Save* with a file in `files` posts `files`, `link_documents`, `unlink_documents` and, on a change, `_method: 'put'` (spy `router.post`).
  - Review Focus 3: with a file in `files` and no field changed, the unsaved-changes guard is dirty.
  - `from` is sent when `back` is given.
- [ ] **Step 2: Run, see them fail.**
- [ ] **Step 3: Implement**; the guard reads `() => ({ ...values, files: files.value.length, link: link.value.map(d => d.id), unlink: unlink.value })`; files/link/unlink reset after a successful save.
- [ ] **Step 4: Run the panel tests, types, lint.**
- [ ] **Step 5: Commit** `Budget: the form of a movement in pairs, with its documents`.

### Task 5: The page of a movement

**Files:**
- Modify: `resources/js/pages/budget/Movement.vue`
- Test: `resources/js/pages/budget/Movement.test.ts`

**Interfaces:**
- Consumes: props of Task 2, `FmCardFiles` of Task 3, panel of Task 4.

- [ ] **Step 1: Write the failing tests**
  - `Movement`: shows the *Documents* column with *Link* and the linked documents of `movement.documents`; for a new movement *No documents yet*; the back link goes to `back` when given, otherwise to the card or the month; the page title (`FmTitle`/heading) as the board *Movimento-pc* («Spesa», breadcrumb *Bilancio › Spesa*).
- [ ] **Step 2: Run, see them fail.**
- [ ] **Step 3: Implement.** `Movement.vue` as `EditCard.vue`: `FmTitle` with back, then `div.flex.flex-col.gap-6.wide:grid.wide:grid-cols-[minmax(0,1fr)_minmax(0,1.55fr)].wide:items-start` with `FmCardFiles` first on wide and after the form on the phone (`order-*` with `wide:`), the panel on the right; owns `files`, `link`, `unlink` and passes the file errors (`listErrors(errors, 'files')`).
- [ ] **Step 4: Run the tests of both pages and of the components they use, types, lint.**
- [ ] **Step 5: Commit** `Budget: a movement opens on its own page, with its documents beside the form`.

### Task 6: Check in the browser and close

- [ ] Fix the commission help line on the board *Nuova-spesa* («Si aggiunge all'importo.») and republish.
- [ ] Headless Chromium (learnings): Bianchi account, computer 1440 and phone 390: open an expense from *Movements*, link a document, upload a PDF → *Import* → back on the expense with the document; compare with the boards.
- [ ] Learnings and docs in the vault (`project-docs`).
