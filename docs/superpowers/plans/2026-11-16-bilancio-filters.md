# Search and filters in Movements, Planned expenses and Documents — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** search and filters (title, type, account, amount, date) in *Movements* and *Planned expenses*, and on the computer one left column that shows the filters or the open item, also in *Documents*.

**Architecture:** the filters travel in the address (query string), validated by the index form requests; a filter action narrows the query (movements) or the rows (planned). On the page a new `FmFilterAside` holds the filters or the detail in the left column on the computer; below 1024 px a `FmFilterButton` opens the filters in `FmBottomSheet`.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3 + TS, FormKit Pro, Pest, Vitest (Vite+), spatie/laravel-data.

**Spec:** `docs/superpowers/specs/2026-11-16-bilancio-filters-design.md`. **Boards:** `.superpowers/sdd/bilancio-filtri/boards/` — `Movimenti*.dc.html`, `Spese-fisse*.dc.html`, `Documenti-dettaglio-pc.dc.html` (also `Documenti-pc` on the canvas). Read the boards of your task before writing the template; where code and board differ, the board wins (except `Spese-fisse-filtri-dettaglio-pc`, dropped: planned rows open their page).

## Global Constraints

- All rules of `AGENTS.md`: TDD, English code and keys with Italian in `lang/it.json` (add lines by hand before the final `}`, never rewrite with a JSON parser), `declare(strict_types=1)`, PHPDoc/TSDoc everywhere, `@group` in the TSDoc of new `fm/` components, `cn()` for classes outside templates, text styles of the design system (no `font-*`/`leading-*` beside them), FormKit Pro inputs (`currency` for amounts, `datepicker` for dates, `togglebuttons` for the type, `autocomplete` or `dropdown` for the account).
- Layout condition: `useComputerLayout()` (≥1024 px) decides column vs sheet; never a hand-written `matchMedia` or `min-[…]` class for it.
- Filters in the address: `search`, `kind`, `account`, `min`, `max`, `from`, `to` (amounts as `12.50`, dates `aaaa-mm-gg`); an id of another family → 404 (`OfFamily`).
- Run Pest only with your own database and one at a time in this worktree: `docker exec -u sail -w /var/www/html/.claude/worktrees/bilancio-filtri -e DB_DATABASE=testing_bilancio family-manager-app-laravel.test-1 php artisan test <paths>`. Vitest: `docker exec -u sail -w /var/www/html/.claude/worktrees/bilancio-filtri family-manager-app-laravel.test-1 npx vp test run <paths>`.
- Before each commit, on touched files: PHPStan, Rector `--dry-run`, Pint, `npm run types`, `npm run lint`, `npx vp check --fix`, `php artisan typescript:transform` after changing a Data class (commit `generated.d.ts`).
- Commit only your files (`git add <paths>`), message in English, ending with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

- Search with only spaces → treated as no search (no search mode).
- `min` greater than `max`, or `from` after `to` → 422 on the field, not an empty list or a 500.
- Opening a movement while filters are on keeps the filters in the address (and «Back to the filters» keeps them too).
- An income with `kind=expense` filter never appears in the search results; sum shows incomes as positive and expenses as positive money out — the line totals **only the shown rows**, signed like the month figures (incomes +, expenses −) when both kinds are shown.
- A member (not admin) sees the same filters; nothing new needs an ability.

---

### Task 1: `FmFilterAside` and `FmFilterButton`

**Files:**
- Create: `resources/js/components/fm/FmFilterAside.vue`, `FmFilterAside.test.ts`, `FmFilterButton.vue`, `FmFilterButton.test.ts`
- Modify: `resources/design/boards.json` (both in board `Pannelli`… `FmFilterButton` in `Basi`), `lang/it.json`

**Interfaces — Produces:**
- `FmFilterAside` (`@group panels`): props `{ detail: boolean; label?: string }` (label: accessible name of the filters, default `trans('Filters')`); slots `filters`, `detail`; emits `close: []`. Renders a sticky card (`sticky top-(--app-header) rounded-card-lg bg-card shadow-card`, as the `aside` of `Documents.vue`). With `detail` true it shows a header with a text button «Back to the filters» (ArrowLeft icon) and an icon button × (`aria-label` «Close»), both emitting `close`, then the `detail` slot; otherwise the `filters` slot inside `<aside :aria-label="label">`.
- `FmFilterButton` (`@group basics`): props `{ count: number }`; emits `click`. Square icon button `size-control-md` with `ListFilter`, `aria-label` «Filters» or «Filters, :count on» (`transChoice` `{1} Filters, :count on|[2,*] Filters, :count on`); with `count > 0` the soft action look of the board (`bg-action-soft text-action-dark`, border `edge-soft` → action) and a badge with the number.

- [ ] **Step 1: tests** — `FmFilterAside.test.ts`: shows the filters slot inside a region named «Filters» when `detail` is false; with `detail` true shows the detail slot, not the filters; clicking «Back to the filters» and clicking «Close» each emit `close` once. `FmFilterButton.test.ts`: name «Filters» with count 0 and no badge; name «Filters, 4 on» and visible «4» with count 4; click emits.
- [ ] **Step 2: run, see them fail** (component missing).
- [ ] **Step 3: write the two components** following the boards `Movimenti-filtri-pc` / `Movimenti-filtri-dettaglio-pc` (column, header of the detail) and `Movimenti-filtri` (button with badge). Add the rows to `boards.json` (`FmFilterAside` → `Pannelli`, `FmFilterButton` → `Basi`). Italian: «Back to the filters» → «Torna ai filtri», «Filters, :count on» → `{1} Filtri, :count attivo|[2,*] Filtri, :count attivi`.
- [ ] **Step 4: run the two tests and `resources/js/design/boards.test.ts`**: PASS.
- [ ] **Step 5: replace the plain filters button of `Documents.vue` with `FmFilterButton`** (count = number of chips), run `Documents.test.ts`, commit.

### Task 2: Movements — filters on the server

**Files:**
- Modify: `app/Http/Requests/Budget/MovementIndexRequest.php`, `app/Http/Controllers/Budget/MovementController.php::index`, `app/Services/Movements.php`
- Create: `app/Data/MovementFiltersData.php` (or a readonly value object `App\Support\MovementFilters` + Data for the page), `app/Data/MovementSearchData.php`
- Test: `tests/Feature/Budget/MovementSearchTest.php` (+ adjust the existing Movements index test only if a prop is renamed)

**Interfaces — Produces (props of `budget/Movements`):**
- `filters: App.Data.MovementFiltersData` = `{ search: string|null; kind: 'expense'|'income'|null; account: number|null; min: number|null; max: number|null; from: string|null; to: string|null }` (keeps the existing `kind` and `account` props working or replaces them — if replaced, update `Movements.vue` and its test in Task 3).
- `search: App.Data.MovementSearchData | null` — null when not in search mode; otherwise `{ months: { month: string /* aaaa-mm */; total: number; movements: MovementData[] }[]; count: number; total: number }`, the most recent month first, rows most recent first. Cap: at most 200 movements (`count` is the real number; the page says when more exist is out of scope — just cap).
- `Movements::search(MovementFilters $filters, User $user): MovementSearchData` reusing the row mapping of `month()`.
- Request methods: `filters(): MovementFilters`; validation `search` nullable string max 100, `min`/`max` nullable numeric ≥0 with `max` `gte:min`, `from`/`to` nullable `date_format:Y-m-d` with `to` `after_or_equal:from`.

- [ ] **Step 1: tests** (Bianchi seeder): `search=spesa` returns only movements whose title contains «spesa» (any case) from more than one month; `min=20&max=100` keeps amounts 20 and 100, drops 19,99 and 100,01; `from`/`to` inclusive; `kind=expense` excludes incomes in search mode; `account` narrows; `search=%20%20` gives `search: null` (month mode); `min=50&max=10` → 422 on `max`; `from=2026-10-10&to=2026-10-01` → 422 on `to`; an account of another family → 404; `filters` prop echoes the values; `movement=expense:ID` together with filters still gives `selected`.
- [ ] **Step 2: run, see them fail.**
- [ ] **Step 3: implement.** Search mode = search text (trimmed, non-empty) or any of min/max/from/to. Month mode untouched. `MovementFiltersData` and `MovementSearchData` with `#[TypeScript]` like the other Data classes; `typescript:transform`.
- [ ] **Step 4: run the new test and the existing Movements tests:** PASS. PHPStan/Rector/Pint on touched files.
- [ ] **Step 5: commit.**

### Task 3: Movements page

**Files:**
- Create: `resources/js/components/fm/FmMovementFilters.vue` + test (`@group forms`, `"pages"` in `boards.json`, as `FmDocumentFilters`; the same for `FmPlannedFilters` in Task 4)
- Modify: `resources/js/pages/budget/Movements.vue`, `Movements.test.ts`, `resources/js/lib/budget.ts` (`movementsUrl` takes the filters), `lang/it.json`

**Interfaces — Consumes:** Task 1 components, Task 2 props. **Produces:** `FmMovementFilters` props `{ filters: App.Data.MovementFiltersData; accounts: { value: string; label: string }[] }`, emits `change: [filters: App.Data.MovementFiltersData]`; fields: Type (`togglebuttons` Everything/Expenses/Income), Account (`dropdown`, «All the accounts» first), Amount From/To (`currency`), Date From/To (`datepicker`), help «Without dates I look in every month.», button «Remove the filters» when any filter is on. It does not hold the search text (the search field above the list does).

- [ ] **Step 1: page tests** (`Movements.test.ts`, fixtures shaped as Task 2 props): on the computer (mock `useComputerLayout` true) the filters are in a region «Filters» and the type buttons/account menu beside the month switch are gone; on the phone a «Filters» button opens the sheet with the same fields and the type buttons stay on the page; with `search` props the month switch and figures are replaced by «6 movements found, 310,10 € in all», month headers with totals, pills for each filter (Expenses, the account label, «From 20,00 € to 100,00 €», «From 01/09/2026 to 31/10/2026», «“spesa”») whose × visits the address without that filter, «Remove the filters» visits with none; with `selected` on the computer the detail (FmMovementPanel) is in the left column with «Back to the filters», which visits the address without `movement` but with the filters; the open row is `current`; the search field sends `search` on Enter; changing a filter visits with all filters (`replace`, `preserveState`, `preserveScroll`). Component test for `FmMovementFilters`: emits the changed filters; «Remove the filters» emits all null.
- [ ] **Step 2: run, see them fail.**
- [ ] **Step 3: implement** following `Movimenti-pc`, `Movimenti-filtri-pc`, `Movimenti-filtri-dettaglio-pc` (computer) and `Movimenti`, `Movimenti-filtri`, `Movimenti-filtri-pannello` (phone). Computer grid: `lg:grid-cols-[16rem_minmax(0,1fr)]` always, `FmFilterAside` first (`:detail="panelOpen"`, `FmMovementPanel compact` in its `detail` slot — drop its own × if `FmFilterAside` already gives one: pass a prop or hide it, with a test). Amount pills use `useFormat().euro`, dates `date()`.
- [ ] **Step 4: run page and component tests, `boards.test.ts`, types, lint:** PASS.
- [ ] **Step 5: commit.**

### Task 4: Planned expenses — search and filters

**Files:**
- Create: `app/Http/Requests/Budget/PlannedIndexRequest.php`, `resources/js/components/fm/FmPlannedFilters.vue` + test
- Modify: `app/Http/Controllers/Budget/PlannedController.php::index`, `app/Services/PlannedSummaries.php` (`index(CarbonImmutable $today, ?PlannedFilters $filters = null)`), `resources/js/pages/budget/Planned.vue` + test, `boards.json`, `lang/it.json`
- Test: `tests/Feature/Budget/PlannedSearchTest.php`

**Interfaces — Produces:** prop `filters: App.Data.PlannedFiltersData` = `{ search; min; max; from; to }` (same rules and validation as Task 2, dates compare the row's next due date, amounts the row's amount); rows filtered in PHP after they are built; `monthly_out`/`monthly_in` stay those of all the rows. `FmPlannedFilters` = Amount From/To + «Next due date» From/To + help «Without dates you see them all.» + «Remove the filters».

- [ ] **Step 1: tests** — Pest: search by title part; amount bounds inclusive; next date bounds inclusive; a date filter hides ended rows; 422s as in Task 2. Vitest: computer → column «Filters» always present, phone → `FmFilterButton` + sheet; with filters the empty section is hidden and the heading shows the count («Going out · 4»); pills and «Remove the filters» as in Task 3; a row still links to the planned page.
- [ ] **Step 2: fail. Step 3: implement** from `Spese-fisse-pc`, `Spese-fisse-filtri-pc`, `Spese-fisse`, `Spese-fisse-filtri`. **Step 4: tests, PHPStan, Rector, Pint, types, lint pass. Step 5: commit.**

### Task 5: Documents — the document in place of the filters

**Files:** Modify `resources/js/pages/documents/Documents.vue`, `Documents.test.ts`.

- [ ] **Step 1: tests:** on the computer the filters column exists from the computer layout (not only from 1440 px); with `selected` the document detail (`FmDocumentDetail compact`) is in the left column with «Back to the filters», which visits the address without `document` but with the filters; there is no third column and no floating side panel; the open row is `current`; below the computer layout the `FmFilterButton` opens the sheet.
- [ ] **Step 2: fail. Step 3: implement** from `Documenti-pc` and `Documenti-dettaglio-pc`: grid `lg:grid-cols-[16rem_minmax(0,1fr)]`, `FmFilterAside` with `FmDocumentFilters` / `FmDocumentDetail compact`; remove `useWideComputerLayout` and `FmSidePanel` here (leave the composable if others use it). **Step 4: tests, types, lint. Step 5: commit.**

### Task 6 (coordinator, canvas): boards of the new components

Draw the cards of `FmFilterAside` (Pannelli) and `FmFilterButton` (Basi, with and without count) in the component boards, publish the canvas. Done by the coordinator with a fork, in parallel with Task 1.
