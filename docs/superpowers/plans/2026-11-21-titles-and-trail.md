# Titles and trail Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every page of the app starts the same way: one `FmTitle`, and Back with the trail in one place per layout (top bar on the computer, a row above the title on the tablet, the link with the chevron on the phone), as the board *Titoli, percorso e colonne* says.

**Architecture:** The layout already knows the trail (pages write it with `usePageTrail`, section pages get the section name). The Back logic leaves `FmTopBar` for a composable, `usePageBack`, and a new `FmPageTrail` draws, above the page, the phone link or the tablet row from that same trail. Pages stop passing their own link back: `FmTitle` loses `back`/`backLabel` and gains the slots for the hand-written titles (`overline`, `leading`), and it puts search and bell beside the title on the phone in every section page by itself.

**Tech Stack:** Vue 3 + TypeScript, Inertia 3, Tailwind 4 (variants `computer:`, `wide:`), Vitest + Testing Library, FormKit untouched.

**Spec:** the boards in `.superpowers/sdd/2026-11-21-titles-and-trail/boards/`: `Titoli-e-colonne.dc.html` (cards *Le tre misure*, *Il titolo della pagina*, *Dove sei e Indietro*, *Cosa cambia nelle pagine*; the card *Le colonne del contenuto* is **not** part of this plan), `Navigazione.dc.html` (cards `FmTopBar`, `FmTrail`, `FmBackLink`), `Layout-telefono/tablet/computer.dc.html`, `Scheda-tablet.dc.html` (a tablet inner page with the row). Open them in a browser or read the HTML.

## Global Constraints

- Code, names, comments, TSDoc and test names in English; texts the person sees through `$t()`/`trans()`, with the Italian in `lang/it.json` (add new keys as text before the final `}`, never by rewriting the file with a JSON parser).
- `<script setup lang="ts">`, typed props/emits/slots, TSDoc on every component (with `@group`), prop, emit and function; no `any`.
- Tailwind classes outside the template go through `cn(…)`; no `lg:` in pages, layouts or the touched components: the title size follows the device with `computer:`, columns stay on `wide:`.
- Text styles of the design system only (`text-title-mobile`, `text-title`, `text-body-sm`, `text-label`, `text-row`…), no `font-*`/`leading-*` beside them; 1 px borders in `border-edge-soft`.
- Measures from the boards: title 28 px (`text-title-mobile`) on phone and tablet, 32 px (`text-title`) on the computer; tablet row: Back button 36 px (`size-9`, `rounded-md`, `border border-edge-soft bg-card`, arrow icon 18 px) + `FmTrail`, 8 px above the title; phone link 8 px above the title.
- Layouts come only from `useLayoutMode()` (`'phone' | 'tablet' | 'computer'`); the page columns stay on `useWideLayout()`.
- TDD for every component, composable and page change (`vite-plus/test`); a test that clicks an Inertia `Link` spies `router.visit`.
- Before each commit, only on what the task touched: Vitest of the touched files and of the pages that use them, `npm run types`, `npx vp check --fix` on the touched files (all in Sail: `docker exec -u sail family-manager-app-laravel.test-1 …`). Commit with paths (`git commit -- <files>`), other sessions may have files staged.

## Decisions (bring into the vault documentation at the end)

- New component `FmPageTrail`: no existing one draws «Back + trail» or «the link back from the trail»; it composes `FmBackLink`, `FmTrail` and the Back of `usePageBack`, so the three layouts read the same trail and the same Back. Place: board *Navigazione*.
- The phone link back is no longer written by each page: it is the nearest page above in the trail (the penultimate piece), and it goes where Back goes (the previous page of the app, else that page).
- Search and bell beside the title on the phone come from `FmTitle` on every section page (`isSectionPage`), no page adds `FmTitleTools` by hand.
- *Received mail* is the page of its own section (it is one in the sidebar): no trail under *To sort*, no Back.
- Not in this plan: the columns card, *Scan* and the scanner full screen on the tablet (they keep their own Back), the auth pages (`FmHeader`).

## Review Focus

- An inner page opened from outside (a link in a mail, a reload): no history, Back must go to the page above in the trail, on all three layouts — tested in Task 1 (`usePageBack`) and Task 2 (`FmPageTrail`).
- A trail whose page above has no `href` (a piece used only as a label): the link and the Back take the nearest piece with an `href`, never a dead piece — tested in Task 1.
- A page that changes its trail after mounting (the title arrives, `rerender`) or unmounts: the row/link follow the new trail and a section page shows nothing — tested in Task 2 (`AppLayout`).
- The browser refuses the clipboard (page not secure, permission denied): the address is selected so the person can copy it by hand, and nothing says «copied» — tested in Task 7.
- A long title on the phone with search and bell beside it: the title wraps, the tools never go below or out of the screen — tested in Task 3 (`FmTitle`).

---

### Task 1: `usePageBack`, and the top bar with Back and trail only on the computer

**Files:**
- Create: `resources/js/composables/usePageBack.ts`, `resources/js/composables/usePageBack.test.ts`
- Modify: `resources/js/components/fm/FmTopBar.vue` (move `parentHref`, `showBack`, `back` out), `resources/js/components/fm/FmTopBar.test.ts`

**Interfaces:**
- Produces: `usePageBack(trail: () => TrailPiece[], section: () => boolean): PageBack` with
  `type PageBack = { parent: ComputedRef<TrailPiece | undefined>; visible: () => boolean; back: (event: MouseEvent) => void }`.
  `parent` is the nearest piece before the last that has an `href`; `visible()` is `!section() && (canGoBack() || parent.value !== undefined)`; `back` keeps today's behaviour of `FmTopBar.back` (modifier key with a parent: let the browser open the link; otherwise `history.back()` when `canGoBack()`, else `router.visit(parent.href)`).

- [ ] **Step 1: Write the failing tests** in `usePageBack.test.ts` (mount the composable in a tiny component; fake `canGoBack` by visiting through `lib/visitHistory` as `FmTopBar.test.ts` already does):
  - `parent` of `[{Aree,/areas},{Casa,/areas/1},{Luce}]` is `Casa`; of `[{Famiglia} (no href),{Persone,/people},{Marco}]` is `Persone`; of `[{Famiglia} (no href),{Marco}]` is `undefined`.
  - `visible()` is false on a section page, false with no history and no parent, true with a parent and no history.
  - `back(click)` without history calls `router.visit('/areas/1')`; with history calls `history.back()`; with `metaKey` and a parent calls neither and does not `preventDefault`.
- [ ] **Step 2: Run** `docker exec -u sail family-manager-app-laravel.test-1 npx vp test run resources/js/composables/usePageBack.test.ts` — FAIL (module missing).
- [ ] **Step 3: Implement `usePageBack`** by moving the code of `FmTopBar` (lines with `parentHref`, `showBack`, `back`).
- [ ] **Step 4: Change `FmTopBar`**: use `usePageBack(() => trail, () => section)`; render the Back button and `FmTrail` only when `mode === 'computer'`. Update its TSDoc (tablet: menu, search, bell). In `FmTopBar.test.ts` add: «on the tablet it shows neither Back nor the trail» (mode `tablet`, inner trail: no `Back` button, no navigation «Where you are») and keep the computer cases.
- [ ] **Step 5: Run** both test files and `npm run types` — PASS.
- [ ] **Step 6: Commit** `git commit -m "Top bar: Back and the trail only on the computer, Back in usePageBack" -- <files>`

### Task 2: `FmPageTrail` above the page, and *Received mail* as a section

**Files:**
- Create: `resources/js/components/fm/FmPageTrail.vue`, `resources/js/components/fm/FmPageTrail.test.ts`
- Modify: `resources/js/components/fm/FmBackLink.vue` (+ test): no `useLayoutMode`, it always draws (the caller decides); `href` and `click` stay.
- Modify: `resources/js/layouts/AppLayout.vue` (+ `AppLayout.test.ts`): inside the non-`bare` `<main>` column, before `<slot />`: `<FmPageTrail v-if="mode !== 'computer'" :mode="mode" :trail="trail" :section="sectionPage" class="-mb-2" />` (the column has `gap-4`: 16 − 8 = 8 px above the title). Update the TSDoc at the top.
- Modify: `resources/js/lib/pageTrail.ts`: add `'mail/ReceivedMails'` to `SECTION_PAGES`.
- Modify: `resources/js/pages/mail/ReceivedMails.vue` (+ test): remove `usePageTrail`, `back`, `back-label` (`pages.test.ts` then accepts it as a section page).
- Modify: `resources/design/boards.json`: `"FmPageTrail": "Navigazione"`.

**Interfaces:**
- Consumes: `usePageBack` (Task 1), `FmTrail`, `FmBackLink`.
- Produces: `FmPageTrail` props `{ mode: 'phone' | 'tablet'; trail: TrailPiece[]; section: boolean }`. Phone: `FmBackLink` with `label = parent.label`, `href = parent.href`, click → `back(event)`. Tablet: a row `flex items-center gap-2.5 min-w-0` with the 36 px Back button (`aria-label` `$t('Back')`, only when `visible()`) and `FmTrail`. Nothing on a section page or when the trail has fewer than 2 pieces; on the phone nothing without a parent.

- [ ] **Step 1: Failing tests** in `FmPageTrail.test.ts`:
  - phone, `[{Famiglia,/family},{Persone,/people},{Marco Bianchi}]`: a link named «People» with `href="/people"`, no navigation «Where you are»;
  - tablet, same trail: a button «Back» and a navigation «Where you are» with links «Famiglia», «Persone» and «Marco Bianchi» marked `aria-current="page"`;
  - tablet, opened without history: clicking «Back» calls `router.visit('/people')` (spy);
  - `section: true` on both layouts: renders nothing (`container` empty).
  In `AppLayout.test.ts`: on the tablet an inner page that calls `usePageTrail` shows the row and the top bar has no «Back»; on a section page (`Home`) neither row nor link; on the phone the link shows the parent of the trail; on the computer no row. In `ReceivedMails.test.ts`: no link «To sort» above the title.
- [ ] **Step 2: Run** the three test files — FAIL.
- [ ] **Step 3: Implement** `FmPageTrail.vue` (`@group navigation`, TSDoc citing the board), the `FmBackLink` change, the `AppLayout` slot, `SECTION_PAGES`, *Received mail*, `boards.json`.
- [ ] **Step 4: Run** the three tests, `FmBackLink.test.ts`, `resources/js/design/boards.test.ts`, `resources/js/pages/pages.test.ts`, `npm run types` — PASS.
- [ ] **Step 5: Commit** `git commit -m "Back and the trail above the page on the phone and the tablet (FmPageTrail); Received mail is its own section" -- <files>`

### Task 3: `FmTitle` without its own link back; title sizes by device; search and bell on every phone section page

**Files:**
- Modify: `resources/js/components/fm/FmTitle.vue` (+ test), `FmCardHeader.vue` (+ test), `FmSettingsPage.vue` (+ test).
- Modify every page that passes `back`/`back-label`/`@back` to `FmTitle`, renders `FmBackLink`, or renders `FmTitleTools` by hand (list: `grep -rn "back-label\|<FmBackLink\|FmTitleTools" resources/js/pages resources/js/components --include=*.vue`), and their tests that look for the link back (it is now drawn by the layout, so a page test no longer finds it: remove those assertions, the behaviour is covered in Task 2).
- Exception: `pages/scan/Scan.vue` keeps its own back: replace `FmTitle :back-label @back` with `<FmBackLink :label="$t('To sort')" @click="cancel('toSort')" />` above `FmTitle` (it is not under `AppLayout`'s column).

**Interfaces:**
- Produces: `FmTitle` props `{ title: string; subtitle?: string }` (no `back`, `backLabel`, no emit `back`); slots `actions?`, `beside?`, `overline?` (above the title, in the same column: `text-label` or `text-body-sm text-ink-soft` written by the page), `leading?` (before the column, e.g. an avatar, `gap-4`, vertically centred). On a section page (`isSectionPage(usePage().component)`) with `useLayoutMode() === 'phone'` and not inside `SECTION_TITLE`, `FmTitle` renders `FmTitleTools` after the `actions` slot.
- `h1` classes: `text-title-mobile computer:text-title` in `FmTitle`, `FmCardHeader` and `FmSettingsPage` (replacing `lg:`); the subtitle is `text-body-sm text-ink-soft` (drop `lg:text-body-sm`); `FmCardHeader` padding `p-5 computer:p-7`.

- [ ] **Step 1: Failing tests** in `FmTitle.test.ts`: renders the `overline` slot before the `h1` and the `leading` slot before both; on the phone with `usePage().component = 'documents/Documents'` it shows a link «Search in Fameja.app» and the bell, on `cards/Card` it does not, on the tablet it does not; with a title of 80 characters and the tools, the tools stay in the same row as the title (`h1` and the tools share the same parent row). Remove the tests of `back`/`backLabel`. In `FmCardHeader.test.ts`/`FmSettingsPage.test.ts`: the `h1` has `computer:text-title` and no `lg:` class.
- [ ] **Step 2: Run** them — FAIL.
- [ ] **Step 3: Implement** `FmTitle`, `FmCardHeader`, `FmSettingsPage`; then strip the props/`FmBackLink`/`FmTitleTools` from the pages and fix their tests; *Scan* as above.
- [ ] **Step 4: Run** the three component tests, the tests of every touched page, `resources/js/design/text-styles.test.ts`, `resources/js/design/breakpoints.test.ts`, `npm run types` — PASS.
- [ ] **Step 5: Commit** `git commit -m "FmTitle: no own link back, overline and leading slots, search and bell on phone section pages, 32 px only on the computer" -- <files>`

### Task 4: Hand-written titles become `FmTitle`; forms lose the X

**Files (each with its test):**
- `pages/Home.vue`: `FmTitle :title="Hi, :name"` with the day in `overline` (`text-body-sm text-ink-soft`).
- `pages/family/Person.vue`: `FmTitle` with `FmAvatar` (64 px) in `leading`, subtitle `personSubtitle(person)`, `FmActions` in `actions`.
- `pages/family/Organization.vue`: same with `FmOrganizationMark` in `leading`.
- `pages/cards/CardPayments.vue`: `FmTitle :title="budget.payments_label"`, «Area · Type» in `overline` (`text-label text-ink-soft`), `FmPayButton` in `actions`.
- `pages/budget/PlannedItem.vue`: `FmTitle :title="row.title" :subtitle="summary"`, the line «Planned expense · card» in `overline`, *Edit* in `actions`.
- `pages/documents/Import.vue` (not computer): replace the X + `h1 text-subtitle` with `FmTitle` (title as on the computer, «File :current of :total» in `actions`); keep the progress bar under it.
- `pages/reminders/EditReminder.vue` (not computer): `FmTitle :title="heading"` instead of `h1 text-stat` + X.
- Remove the X «Close» from the titles of `cards/EditCard.vue`, `family/EditPerson.vue`, `family/EditOrganization.vue`, `budget/EditPlanned.vue` (the form closes with Back or *Cancel*). Keep the X of panels and of the open mail in *Received mail* (that closes the detail, not a form).

- [ ] **Step 1: Failing tests**, one per page: there is exactly one `heading` level 1 with the title text (and, for Person and Organization, the image is before it); for the four forms and EditReminder/Import there is no button or link «Close».
- [ ] **Step 2: Run** them — FAIL.
- [ ] **Step 3: Implement** the pages.
- [ ] **Step 4: Run** the page tests, `text-styles.test.ts`, `npm run types` — PASS.
- [ ] **Step 5: Commit** `git commit -m "Every page title is FmTitle; forms close with Back or Cancel, no X" -- <files>`

### Task 5: Complete trails, and *Budget* as the title of its three pages

Rule (board, *Dove sei e Indietro*): the trail names every page with an address from the section to the open one; when the page above is a tab of the section, the tab is there too. The last piece equals the `FmTitle` text.

**Files (each with its test asserting the pieces written through `usePageTrail`, as the existing page tests do; if none does, provide `TRAIL_KEY` with `createTrailState()` and read `pieces`):**
- `pages/family/Person.vue`: Family › People (`index()`) › name.
- `pages/family/EditPerson.vue`: Family › People › name (if editing) › «Edit :name» / «New person».
- `pages/family/EditOrganization.vue`: Family › Organizations › name (if editing) › edit title.
- `pages/budget/Movement.vue`: Budget › Movements (`movementsUrl` of the month of the movement) › title.
- `pages/budget/PlannedItem.vue` and `EditPlanned.vue`: Budget › «Recurring» (the tab label of `budgetTabs`, `index()` of planned) › title (› edit title).
- `pages/budget/Planned.vue`: `FmTitle :title="$t('Budget')"` (subtitle unchanged), as Movements and Comparison.

- [ ] **Step 1: Failing tests** with the exact pieces above (labels through `trans`, hrefs from the Wayfinder routes).
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the page tests and `npm run types` — PASS.
- [ ] **Step 5: Commit** `git commit -m "Trails name the tab of the section; Budget is the title of its three pages" -- <files>`

### Task 6: *Family* with the underlined tabs

**Files:** `resources/js/components/fm/FmFamilyViews.vue` (+ test).

- Replace the grey segmented `nav` with `<FmTabs :tabs="…" :label="$t('Family')" />` (underline, as the budget), each tab with its `count` (people, organizations) and `current` on the open view. Same distance under the title as the budget pages.

- [ ] **Step 1: Failing test**: the navigation «Family» has links «People» and «Organizations» with their numbers, the open one with `aria-current="page"`, drawn by `FmTabs` (look for the underline variant class used by `FmTabs.test.ts`).
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** `FmFamilyViews.test.ts`, `People.test.ts`, `Organizations.test.ts`, types — PASS.
- [ ] **Step 5: Commit** `git commit -m "Family: underlined tabs as the budget" -- <files>`

### Task 7: *Received mail*: the address copies with a click

**Files:** `resources/js/pages/mail/ReceivedMails.vue` (+ test), `lang/it.json` if a new key is needed.

- The subtitle keeps «The mails that arrived at :address», but the address is a `button` (`text-body-sm`, `text-action`, no border, `cursor-pointer`, `aria-label` «Copy the address :address») that calls `useCopy().copy(address)`. After a copy the subtitle says «Address copied» beside it for 2 s (reuse the existing key); when `copy` returns false, select the address text (`Range` on the button's text) so the person can press ⌘C. The subtitle is passed through a new `FmTitle` slot `subtitle?` only if `subtitle` as a string is not enough — prefer the slot (add it in `FmTitle` with its test: when given, it replaces the `subtitle` text).

- [ ] **Step 1: Failing tests**: clicking the address calls `navigator.clipboard.writeText('leica.x@mg.example')` (stub it) and shows «Address copied»; with `writeText` rejecting, «Address copied» does not appear and `window.getSelection().toString()` equals the address. `FmTitle.test.ts`: the `subtitle` slot replaces the prop.
- [ ] **Step 2: Run** — FAIL. **Step 3: Implement.** **Step 4: Run** both tests, `TranslationsTest` is PHP: run `docker exec -u sail -e DB_DATABASE=testing_titles family-manager-app-laravel.test-1 php artisan test tests/Feature/TranslationsTest.php` if a key was added, types — PASS.
- [ ] **Step 5: Commit** `git commit -m "Received mail: the address copies with a click" -- <files>`

### Task 8: Check in the browser and close

- [ ] Build in Sail (`npm run build`, Vite is never started by the agent) or use the running Vite if the owner has it on; with Playwright (headless shell, see `.claude/learnings.md`), signed in as a Bianchi account of `README.md`, screenshot at 390×844, 1024×768 and 1440×900: Home, Documents, a card (`Aree › Casa › …`), a person, a planned expense, Received mail. Compare with the boards; fix what differs (each fix in its task's style, with a test).
- [ ] Update the boards that changed: `FmTitle` card (no link back, `overline`, `leading`, tools on phone section pages), `FmPageTrail` card in *Navigazione*, the trail table of *Titoli, percorso e colonne* (*Received mail* as a section, «Recurring» in the budget trails), the *Posta ricevuta* screen (clickable address).
- [ ] Vault documentation with the skill `project-docs` (the decisions above).
