# Filters under the search bar — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Lists stop keeping their filters in a side column. From 900 px a *Filters* button next to the search (or the title) opens the filters in a box under it, with the pills of the chosen filters below. Under 900 px the same button opens the panel from below. Documents opens the chosen document in a 400 px column on the right. *Planned* merges its two «New…» buttons into one *Add* menu.

**Architecture:** Three new or extended pieces in `components/fm/` carry the pattern:
- `FmFilterButton` gets a labelled look for wide windows.
- `FmFilterBox` (new) is the card under the search.
- `FmDetailColumn` (new) is the 400 px column on the right.

`useListFilters` switches from the computer layout (1280 px) to `useWideLayout()` (900 px), drops the grid of the side column and keeps whether the box is open. `FmFilterAside` is deleted. Each page (Documents, Movements, Planned, To sort, Agenda) is then rebuilt on these pieces.

**Tech Stack:** Vue 3 `<script setup lang="ts">`, Inertia 3, FormKit Pro, reka-ui (`FmMenu`), Tailwind 4 with the design-system tokens, Vitest + Testing Library (`vite-plus/test`).

**Spec:** the canvas boards, downloaded into `.superpowers/sdd/list-filters/boards/project/` (read the board of your task before writing code; where code and board differ, the board wins):
- `Layout-pagine.dc.html`, card *Filtri ed elenco* and *Elenco e dettaglio*: the rules.
- Documents: `Documenti-pc`, `Documenti-filtri-aperti-pc`, `Documenti-anteprima-pc`, `Documenti-tablet`, `Documenti-filtri-aperti-tablet`, `Documenti-anteprima-tablet`, `Documenti-anteprima-tablet-verticale`, `Documenti`, `Documenti-filtri`.
- Movements: `Movimenti-pc`, `Movimenti-filtri-pc`, `Movimenti-filtri-dettaglio-pc`, `Movimenti-filtri-vuota-pc`.
- Planned: `Spese-fisse-pc` (the *Add* menu open), `Spese-fisse-filtri-pc`, `Spese-fisse-filtri-vuota-pc`.
- To sort: `Da-smistare-pc`, `Da-smistare-tablet`.
- Agenda: `Agenda-pc`, `Agenda-filtri-aperti-pc`, `Agenda-filtri-aperti-tablet`, `Agenda-filtri`.
- Component boards: `Basi` (FmFilterButton), `Campi` (FmFilterPills), `Pannelli` (FmFilterAside, to remove), `Navigazione` (FmMenu).

## Global Constraints

- The rules of `AGENTS.md`:
  - TDD with Vitest;
  - English code and comments, with the Italian in `lang/it.json` (new keys added by hand before the final `}`, never by rewriting the file with a JSON parser);
  - TSDoc on every component, prop, emit and function, with `@group` on components;
  - classes outside the template go through `cn()`;
  - text styles from tokens (`text-label`, `text-body-sm`…), never `font-*` or `leading-*` beside them;
  - control heights `h-control`, radii by token name;
  - 1 px edges `border-edge-soft`.
- The width condition comes only from `useWideLayout()` and the `wide:` / `not-wide:` variants. No `matchMedia` and no `min-[900px]:`. `computer:` is used only for what follows the computer layout (the four-column grid of the box).
- Filters from 900 px: the button with its label next to the search, and the box under it. Under 900 px: the square button and the panel from below (`FmBottomSheet`).
- The box grid has four columns on the computer (`computer:grid-cols-4`) and two below that (`grid-cols-2`).
- Detail column: 400 px on the computer, 380 px on the tablet, sticky under the top bar (`top-(--app-header)`), a card with radius 20 px and `shadow-card`. Header: ‹ › (previous / next row on the page), «N of M», and × to close. Escape closes it too.
- Pills (`FmFilterPills`) show whenever a filter is on, box open or closed. This follows the Documents boards; the Movements and Planned boards are fixed in the canvas to match.
- Every test that clicks an Inertia `Link` fakes `router.visit` (`vi.spyOn(router, 'visit').mockImplementation(() => {})`).
- Before each commit, only on the files touched:
  - Vitest of those files and of the pages that use them;
  - `npm run types`;
  - `npx vp check --fix <files>`.
  - Commit with `git commit -- <files>`.
- Commands run in Sail: `docker exec -u sail -w /var/www/html family-manager-app-laravel.test-1 <cmd>`.

## Review Focus

- **Window crossing 900 px with the box open.** The box is dropped and the sheet starts closed. Narrowing and widening again never shows box and sheet together. Pin it in `useListFilters.test.ts`: `boxOpen` goes false when `wide` turns false.
- **Esc in the detail column while a FormKit menu is open inside it.** Only the menu closes. The column listens on `keydown` at the document and ignores events with `defaultPrevented`. Pin it in `FmDetailColumn.test.ts`.
- **‹ › at the ends of the page of results.** ‹ is disabled on the first row and › on the last, never wrapping around. Pin it in `FmDetailColumn.test.ts`.
- **A document chosen, then a filter changed so it leaves the list.** The column stays on that document; the counter says «– of M» and both arrows step from the list. Pin it in `Documents.test.ts` with `selected` not among `documents`.
- **Count with only a search.** The button shows no badge, because the search lives in the box (`filterCount` excludes `search`). Keep and run the existing test in `useListFilters.test.ts`.

---

### Task 1: FmFilterButton labelled, FmFilterBox, useListFilters on the wide layout

**Files:**
- Modify: `resources/js/components/fm/FmFilterButton.vue`, `FmFilterButton.test.ts`
- Create: `resources/js/components/fm/FmFilterBox.vue`, `FmFilterBox.test.ts`
- Modify: `resources/js/composables/useListFilters.ts`, `useListFilters.test.ts`
- Modify: `resources/design/boards.json` (add `FmFilterBox` to `Pannelli`)
- Modify: `lang/it.json` (new keys only)

**Interfaces:**
- Produces: `FmFilterButton` props `{ count: number; expanded?: boolean; labelled?: boolean; controls?: string }`, emit `click`.
  - Without `labelled`: today's square (opens the sheet, `aria-haspopup="dialog"`).
  - With `labelled`: `h-control rounded-control px-3.5`, the `ListFilter` icon at 20 px, the text «Filters» in `text-label`, and when `count > 0` a pill badge after the text: 22 px tall, `bg-action text-on-action text-badge`.
  - Labelled colours: closed is `border border-edge-soft bg-card text-ink`; `expanded` is `border-[1.5px] border-action bg-action-soft text-action-dark`.
  - With `controls`: `aria-controls` is set and `aria-haspopup` is left off.
  - The name stays «Filters» or «Filters, N on».
- Produces: `FmFilterBox` props `{ id: string; label?: string }` (default `trans('Filters')`) and a default slot. It renders `<section :id :aria-label class="rounded-card bg-card px-4.5 py-4 shadow-card">`. `@group panels`.
- Produces: `useListFilters()` returns `ListFilters<F, E>` with these changes:
  - `desktop` becomes `wide: Ref<boolean>`, from `useWideLayout()`.
  - New `boxOpen: Ref<boolean>`: false at start, and set back to false when `wide` turns false.
  - `layout` is removed.
  - Everything else is unchanged.

- [ ] **Step 1: Write the failing tests**
  - `FmFilterButton.test.ts`:
    - `labelled shows the word Filters and the count`: render `{ count: 2, labelled: true, controls: 'filters' }`; expect `getByRole('button', { name: 'Filters, 2 on' })` to have the text `Filters`, `aria-controls="filters"` and no `aria-haspopup`.
    - `labelled without filters has no badge`: `{ count: 0, labelled: true }` gives the name `Filters` and no element with text `0`.
  - `FmFilterBox.test.ts`:
    - `is a named region around its fields`: `getByRole('region', { name: 'Filters' })` has `id="filters"` and contains the slot text.
  - `useListFilters.test.ts`, with the matchMedia fake already used by the `useWideLayout` tests:
    - `opens the box only on a wide window`: `wide.value` is true with the query matching, and `boxOpen` starts false.
    - `closes the box when the window narrows`: set `boxOpen` true, flip the query to not matching, then `await nextTick()`; `boxOpen.value` is false.
    - Delete the tests of `layout` and `desktop`.
- [ ] **Step 2: Run them and see them fail**
  `docker exec -u sail -w /var/www/html family-manager-app-laravel.test-1 npx vp test run resources/js/components/fm/FmFilterButton.test.ts resources/js/components/fm/FmFilterBox.test.ts resources/js/composables/useListFilters.test.ts`
  Expected: FAIL (no `labelled`, no `FmFilterBox`, no `boxOpen`).
- [ ] **Step 3: Implement the three files as in the Interfaces block**
  - Add the `FmFilterBox` row to `boards.json` under `Pannelli`.
  - The pages still read `desktop` and `layout`; leave them failing types until Tasks 2–6.
  - Run `npm run types` only on these files' tests at this point.
- [ ] **Step 4: Run the tests and see them pass** (same command). Expected: PASS.
- [ ] **Step 5: Commit**
  `git commit -m "Filters: labelled button, FmFilterBox, useListFilters on the wide layout" -- <the files above>`

### Task 2: The filter forms in columns

**Files:**
- Modify: `resources/js/components/fm/FmDocumentFilters.vue`, `FmMovementFilters.vue`, `FmPlannedFilters.vue` and their `*.test.ts`

**Interfaces:**
- Produces: each of the three gets the prop `columns?: boolean`.
  - With `columns`, the root becomes `grid grid-cols-2 gap-x-3.5 gap-y-3 computer:grid-cols-4`. Each field (or each range group from `FilterRanges`) is one cell, in the order of the boards.
  - Documents: Categoria, Persona, Ente, Stato; the toggle *Expiring within 60 days* plus *Remove the filters* in a full row below (`col-span-full flex justify-between`).
  - Movements: Tipo, Conto, Importo, Data.
  - Planned: Importo, Prossima scadenza.
  - Without `columns`, nothing changes (the sheet keeps the stack).

- [ ] **Step 1: Failing test per form.** `in columns every field is a cell of the grid`: render with `columns: true`. Expect the root (`container.firstElementChild`) to have the classes `grid-cols-2` and `computer:grid-cols-4`, and the labels in board order (`getAllByText` of the field labels, compared by `compareDocumentPosition`).
- [ ] **Step 2: Run them and see them fail.** `… npx vp test run resources/js/components/fm/FmDocumentFilters.test.ts resources/js/components/fm/FmMovementFilters.test.ts resources/js/components/fm/FmPlannedFilters.test.ts`
- [ ] **Step 3: Add `columns` to the three forms.** If `FilterRanges` needs a change to sit in two cells, give it the same `columns` prop.
- [ ] **Step 4: Run them and see them pass.**
- [ ] **Step 5: Commit**
  `"Filter forms: four columns in the box"`

### Task 3: FmDetailColumn and Documents with the box and the right column

**Files:**
- Create: `resources/js/components/fm/FmDetailColumn.vue`, `FmDetailColumn.test.ts`
- Modify: `resources/js/pages/documents/Documents.vue`, `Documents.test.ts`
- Delete: `resources/js/components/fm/FmFilterAside.vue`, `FmFilterAside.test.ts` (Movements and Planned stop using it in Tasks 4–5; until then keep the file, and delete it in Task 5)
- Modify: `resources/design/boards.json` (add `FmDetailColumn` to `Pannelli`)

**Interfaces:**
- Consumes: Task 1 (`FmFilterButton labelled controls`, `FmFilterBox`, `useListFilters().wide/boxOpen`) and Task 2 (`FmDocumentFilters columns`).
- Produces: `FmDetailColumn`:
  - props `{ label: string; position: number | null; total: number; previousLabel: string; nextLabel: string }`;
  - emits `previous`, `next`, `close`;
  - slots `default` and `actions?` (icon buttons before the ×).
  - It renders `<aside :aria-label="label">` with the header: ‹ and › as 40 px `bg-canvas rounded-[10px]` buttons named `previousLabel` / `nextLabel`, disabled at the ends; «:position of :total» (`text-body-sm text-ink-soft`, «– of :total» when `position` is null); then `actions`, then × «Close».
  - Escape on the document emits `close`, unless `event.defaultPrevented`.
  - Classes: `sticky top-(--app-header) w-[380px] computer:w-[400px] shrink-0 self-start max-h-[calc(100dvh-var(--app-header,0px)-(--spacing(6)))] overflow-y-auto rounded-[20px] bg-card px-5 pt-4.5 pb-5.5 shadow-card flex flex-col gap-3.5`.
  - `@group panels`.
- Documents page, wide:
  - Title row as today.
  - Then the search row: the search field plus `FmFilterButton labelled controls="document-filters" :expanded="boxOpen"`, which toggles `boxOpen`.
  - Then `FmFilterBox id="document-filters"` with `FmDocumentFilters columns` when `boxOpen`.
  - Then `FmFilterPills`, then the list.
  - With `selected`, the list block and `FmDetailColumn` sit side by side (`wide:grid wide:grid-cols-[minmax(0,1fr)_400px] gap-5 computer:gap-7`, 380 px under the computer).
  - The column holds `FmDocumentDetail compact embedded`, with `FmDocumentActions compact menu-only` in `actions`.
  - ‹ › visit the neighbouring row of `documents` with the same `rowHref`. × and Esc call `closeSelected`.
- Not wide: the square button and the sheet, as today. A row opens the document page.
- `rowHref` uses `wide` in place of `desktop`.

- [ ] **Step 1: Failing tests**
  - `FmDetailColumn.test.ts`:
    - `names itself and counts the rows`: `{ label: 'Busta paga', position: 1, total: 4, … }`; expect `getByRole('complementary', { name: 'Busta paga' })` and the text `1 of 4`.
    - `has no previous on the first row and no next on the last`: position 1 makes ‹ disabled; position 4 of 4 makes › disabled.
    - `closes with the × and with Escape`: both emit `close`. A keydown whose `preventDefault()` was already called emits nothing.
  - `Documents.test.ts`, wide (matchMedia matching `WIDE_QUERY`):
    - `the filters open in a box under the search`: click `Filters`; expect `getByRole('region', { name: 'Filters' })` and no `complementary` named `Filters`.
    - `the chosen document opens in the column on the right`: with `selected`, expect `getByRole('complementary', { name: <title> })` and `2 of 3`. Clicking `Next document` calls `router.visit` with the next row's address, from the `rowHref` of row 3.
    - `a chosen document that left the list still shows`: `selected` not in `documents` gives `– of 3`.
  - Not wide:
    - `the filter button opens the panel from below`: the existing test, unchanged.
- [ ] **Step 2: Run them and see them fail.** `… npx vp test run resources/js/components/fm/FmDetailColumn.test.ts resources/js/pages/documents/Documents.test.ts`
- [ ] **Step 3: Implement `FmDetailColumn`, then rebuild `Documents.vue` as in Interfaces.**
  - Add the `boards.json` row.
  - New strings in `lang/it.json`:
    - `":position of :total"`: `":position di :total"`
    - `"– of :total"`: `"– di :total"`
    - `"Previous document"`: `"Documento precedente"`
    - `"Next document"`: `"Documento successivo"`
  - Keep any existing key as it is.
- [ ] **Step 4: Run them and see them pass, then `npm run types`.**
- [ ] **Step 5: Commit**
  `"Documents: filters in a box under the search, the document in a column on the right (FmDetailColumn)"`

### Task 4: Movements with the box

**Files:**
- Modify: `resources/js/pages/budget/Movements.vue`, `Movements.test.ts`

**Interfaces:**
- Consumes: Tasks 1–2.
- Change: the side column goes. On a wide window, the search row gets `FmFilterButton labelled controls="movement-filters"`, and `FmFilterBox id="movement-filters"` holds `FmMovementFilters columns` (without the «Filter» heading) right under the search row. Pills, the month switch, the figures and the list follow as today. The rest of the page is unchanged.

- [ ] **Step 1: Failing test.** In `Movements.test.ts`, wide: `the filters open in a box under the search, never beside the list`. There is no `complementary` named `Filters`; clicking `Filters` shows the region `Filters` with the field `Bank account`; with two pills the button is named `Filters, 2 on`.
- [ ] **Step 2: Run it and see it fail.** `… npx vp test run resources/js/pages/budget/Movements.test.ts`
- [ ] **Step 3: Rebuild the filter part of `Movements.vue`.**
- [ ] **Step 4: Run it and see it pass, then `npm run types`.**
- [ ] **Step 5: Commit**
  `"Movements: filters in a box under the search"`

### Task 5: Planned with the box and the *Add* menu

**Files:**
- Modify: `resources/js/components/fm/FmMenu.vue`, `FmMenu.test.ts`
- Modify: `resources/js/pages/budget/Planned.vue`, `Planned.test.ts`
- Delete: `resources/js/components/fm/FmFilterAside.vue`, `FmFilterAside.test.ts`, and the `FmFilterAside` row of `resources/design/boards.json`

**Interfaces:**
- Consumes: Tasks 1–2.
- Produces: `FmMenu` prop `primary?: boolean`.
  - The trigger is a blue button: `h-control rounded-control bg-action text-on-action pl-5 pr-4 gap-2`, with the `icon` at 20 px, the `label` as visible text (`text-body`), and a `ChevronDown` at 20 px after it.
  - The entries open under its right edge (`align="end"`).
- Planned title actions: in place of `FmActions`, `<FmMenu primary :icon="Plus" :label="$t('Add')">` with two `FmMenuItem`s:
  - `ArrowUpRight` with the class `text-overdue`, labelled «Planned expense», `href="create({ kind: 'expense' }).url"`;
  - `ArrowDownLeft` with the class `text-income`, labelled «Planned income», `href="create({ kind: 'income' }).url"`.
  - Shown only with `canEdit`, as today.
- Filters as in Task 4, with `planned-filters` and `FmPlannedFilters columns`.

- [ ] **Step 1: Failing tests**
  - `FmMenu.test.ts`:
    - `primary shows its label and a chevron`: render `{ primary: true, label: 'Add', icon: Plus }`; the button named `Add` has the visible text `Add`.
  - `Planned.test.ts`:
    - `one Add button opens the two new planned`: no links named `New planned expense` or `New planned income`. After opening `Add`, `getByRole('menuitem', { name: 'Planned expense' })` has the expense create address and `Planned income` the income one.
    - `a member does not see Add`: `can_edit` false gives no button `Add`.
    - `the filters open in a box under the search` (wide), as in Task 4.
- [ ] **Step 2: Run them and see them fail.** `… npx vp test run resources/js/components/fm/FmMenu.test.ts resources/js/pages/budget/Planned.test.ts`
- [ ] **Step 3: Implement `primary`, rebuild `Planned.vue`, and delete `FmFilterAside` with its row.**
  - Then `grep -rn FmFilterAside resources/js` must print nothing.
  - New strings in `lang/it.json` if missing: `"Add"`, `"Planned expense"`: `"Spesa fissa"`, `"Planned income"`: `"Entrata fissa"`.
- [ ] **Step 4: Run them and see them pass, together with `resources/js/design/boards.test.ts`, then `npm run types`.**
- [ ] **Step 5: Commit**
  `"Planned: one Add menu, filters in a box under the search; FmFilterAside removed"`

### Task 6: To sort and Agenda: the button in the title row

**Files:**
- Modify: `resources/js/pages/documents/ToSort.vue`, `ToSort.test.ts`
- Delete: `resources/js/components/documents/ToSortSources.vue` and its test (if it has one)
- Modify: `resources/js/pages/Agenda.vue`, `Agenda.test.ts`, `resources/js/components/fm/FmAgendaFilters.vue`, `FmAgendaFilters.test.ts`

**Interfaces:**
- Consumes: Task 1 (`FmFilterButton labelled controls`, `FmFilterBox`).
- To sort, on the computer (`mode === 'computer'`):
  - `FmFilterButton labelled controls="to-sort-filters"` comes first in the title `#actions`, before *Link the phone* and *Scan*. Its count is 1 when `source` is not null, else 0.
  - When open, `FmFilterBox id="to-sort-filters"` under the title holds the same `FmChip` list the tablet shows.
  - When closed and a source is chosen, an `FmFilterPills` with the one pill (label = the chip's name, `remove` = choose *All*) sits above the drop zone.
  - The note «Files that arrive by mail, from the phone or uploaded here stay until you import or delete them.» (the text `ToSortSources` shows today, same key) moves under the list as `text-body-sm text-ink-soft`.
  - Phone and tablet are unchanged.
- Agenda, on a wide window:
  - `FmFilterButton labelled controls="agenda-filters"` goes in the title `#actions` before *New task*. The count is the number of filters away from the defaults: months not the default, a person, each area.
  - When open, `FmFilterBox id="agenda-filters"` under the title holds `FmAgendaFilters variant="box"`.
  - The always-visible `toolbar` variant goes.
  - Under 900 px the sheet stays as today.
- Produces: `FmAgendaFilters` gets `variant: 'sheet' | 'box'`. `box` lays out Period, Of whom and Areas in one row (`computer:grid-cols-3`, `grid-cols-1` under it) with the same controls as `toolbar`. The `toolbar` variant is removed.

- [ ] **Step 1: Failing tests**
  - `ToSort.test.ts`, computer:
    - `the sources are behind the Filters button, not beside the list`: no text `Where they come from`. Clicking `Filters` shows the region `Filters` with the chip `Mail`. With `source: 'mail'` the button is named `Filters, 1 on`, and a pill with a × removes it (a visit to *All*).
  - `Agenda.test.ts`, wide:
    - `the filters open in a box under the title`: no filters before the click; after `Filters`, the region `Filters` holds `Period`.
    - With a person chosen the button is named `Filters, 1 on`.
  - `FmAgendaFilters.test.ts`:
    - `box shows period, people and areas in a row`: replaces the `toolbar` tests.
- [ ] **Step 2: Run them and see them fail.** `… npx vp test run resources/js/pages/documents/ToSort.test.ts resources/js/pages/Agenda.test.ts resources/js/components/fm/FmAgendaFilters.test.ts`
- [ ] **Step 3: Implement as in Interfaces.** Delete `ToSortSources`; `grep -rn ToSortSources resources/js` prints nothing.
- [ ] **Step 4: Run them and see them pass, then `npm run types`.**
- [ ] **Step 5: Commit**
  `"To sort and Agenda: the Filters button in the title row, the filters in a box"`

### Task 7 (coordinator, in parallel with Tasks 1–6): the component boards

A fork of the coordinator, not a subagent: it can open the canvas. Before Task 1's commit it publishes:
- `Basi`, card *FmFilterButton*: the labelled look on wide windows.
- `Pannelli`:
  - the *FmFilterAside* card is replaced by *FmFilterBox* (the box under the search) and *FmDetailColumn* (the 400 px column with ‹ › «1 di 4» ×);
  - the *Colonna dei filtri* text goes.
- `Navigazione`, card *FmMenu*: the `primary` case («Aggiungi» with the chevron, open with two entries).
- `Movimenti-filtri-pc` and `Spese-fisse-filtri-pc`: the pills stay visible under the open box, as on the Documents boards.

It republishes the canvas and saves the copies in `.superpowers/sdd/list-filters/boards/project/`.

### Task 8: Check in the browser and wrap up

- [ ] Build the worktree (`npm run build`) and open Documents, Movements, Planned, To sort and Agenda with a Bianchi account in headless Chromium at 1440×900, 1024×800 and 390×844. Compare the screenshots with the boards above.
- [ ] Delete the `FmFilterAside` entries from `.claude/learnings.md` and `AGENTS.md` if any mention it. Run `grep -rn "FmFilterAside\|ToSortSources" --exclude-dir=node_modules .`, which prints only the plan.
- [ ] Note in the *Decisioni prese* section of the plan (here, below) what changed from the boards.

## Decisions taken

- Pills stay visible with the box open (as on the Documents boards); Movements and Planned boards are fixed to match.
- Agenda has no pills under its button: its filters are few and the box shows them all; the button's count says how many are on.
- `FmDetailColumn` is new because no existing component is a column on the right. `FmFilterAside` held a detail, but on the left and in place of the filters, which the boards remove. Agenda and *Posta ricevuta* move to it in a later piece of work (the *Elenco e dettaglio* card).
