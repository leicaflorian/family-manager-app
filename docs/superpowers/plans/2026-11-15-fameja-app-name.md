# The app is called Fameja.app Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** everything the person sees says «Fameja.app» instead of «Family Manager», with the name read from one place.

**Architecture:** the name is `config('app.name')` (default `'Fameja.app'`). The server reads it directly (Blade, mails, `PageMeta`, calendar, Mailgun route); the pages read the shared prop `name` that `HandleInertiaRequests` already sends in both shares, through a new composable `useAppName()`. Texts get it as the placeholder `:app`. The static files that cannot read the config (manifest, offline page, preview image) are written by hand and a test ties them to the config. A guard test fails if «Family Manager» is left anywhere outside the agent's name.

**Tech Stack:** Laravel 13, Pest, Inertia + Vue 3, `laravel-vue-i18n`, Vitest, `rsvg-convert` (Sail container), Claude Design canvas.

**Spec:** `docs/superpowers/specs/2026-11-15-fameja-app-name-design.md`

## Global Constraints

- The name is always «Fameja.app» (capital F, `.app` always), never «Fameja» alone, never «Family Manager».
- One source: `config('app.name')` on the server, `usePage().props.name` in the pages (via `useAppName()`). No literal «Fameja.app» in PHP, Blade, Vue or TS outside `config/app.php`, `.env.example` and test expectations; the only hand-written copies are `public/manifest.webmanifest`, `public/offline.html`, `resources/design/og-image.svg` and the canvas.
- Translation keys carry `:app`, not the name: `"Search in :app"` → `"Cerca in :app"`. New lines of `lang/it.json` are added by hand before the final `}` (the file has a duplicate key; never rewrite it with a JSON parser); removed keys are searched in `database/` too.
- Unchanged: `Fm…` component names, the repository/folder `family-manager-app`, the database `family_manager_app`, paths, Coolify resources, `fm_` prefixes, and *Family Manager Agent* (with the page texts that name it).
- **Gate:** another session has open changes on `lang/it.json`, `navigation.ts`, `navigation.test.ts`, `FmSidebar.*`, `FmBottomBar.test.ts`, `AppLayout.*`, `HandleInertiaRequests.php`. Before Tasks 1, 3 and 4 run `git status --short`: if any of the task's files is still modified by someone else, wait for that commit. Commit only your own files.
- Checks before each commit, on touched files only: the Pest and Vitest tests of the touched files and of their users, PHPStan, Rector `--dry-run`, Pint, `npm run types`, `npm run lint`. Pest with its own database: `docker exec -u sail -e DB_DATABASE=testing_fameja family-manager-app-laravel.test-1 php artisan test <files>`.

## Review Focus

1. **Production without `APP_NAME`:** `config/app.php` falls back to `'Fameja.app'`, not `'Laravel'`. Pinned by `AppNameTest > the default name is Fameja.app` (Task 1).
2. **A phone linked with a code** gets the reduced share: it must carry `name` too, or the scan page shows an empty name. Pinned by `AppNameTest > the linked phone share carries the name` (Task 1).
3. **The browser tab title** on the first page load, before any visit: it must be «Accedi - Fameja.app», not «Accedi - undefined». Pinned by the `app.ts` title helper test (Task 3).
4. **Plural texts with `:app`** (`AcceptInvitation`, `$tChoice`): `:count` is replaced automatically, `:app` must survive in both forms. Pinned in `AcceptInvitation` test (Task 4).
5. **A leftover «Family Manager»** in a text, comment, mail or static file. Pinned by `AppNameTest > nothing says Family Manager` (Task 4).

---

### Task 1: The name on the server

**Files:**
- Modify: `config/app.php:18`, `.env.example:1` and `:80` (remove `VITE_APP_NAME`), `app/Support/PageMeta.php:50`, `app/Support/Ics/Calendar.php:33`, `app/Http/Controllers/CalendarFeedController.php:40`, `app/Services/Mailgun/MailgunRoutes.php:19,75`, `resources/views/app.blade.php:28`, `resources/views/vendor/mail/html/message.blade.php:2`, `resources/views/vendor/mail/text/message.blade.php:2`, `resources/views/mail/reset-password.blade.php:6`, `resources/views/mail/feedback-report.blade.php:1`, `lang/it.json`
- Create: `tests/Feature/AppNameTest.php`
- Test: `tests/Feature/PageMetaTest.php`, `tests/Feature/FeedbackTest.php`, `tests/Feature/Mail/AppMailsTest.php`, `tests/Feature/Mail/MailgunRoutesTest.php`, `tests/Feature/Calendar/CalendarFeedTest.php`

**Interfaces:**
- Produces: `config('app.name') === 'Fameja.app'` by default; `MailgunRoutes::description(): string` (replaces the constant `DESCRIPTION`), returning `config('app.name').' - posta in arrivo'`; preview image URL `url('/fameja-app-og-1200x630.png')` (the file comes in Task 2).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AppNameTest.php`:
- `the default name is Fameja.app`: `expect(require base_path('config/app.php'))['name']` evaluated with `APP_NAME` unset (use `putenv('APP_NAME')` + restore, or read the default with a regex on the file: `env('APP_NAME', 'Fameja.app')`) `->toBe('Fameja.app')`.
- `the linked phone share carries the name`: sign in a linked phone as `LinkedDeviceLimitTest` does, `get(route('scan'))->assertInertia(fn ($page) => $page->where('name', 'Fameja.app'))`.

Update the existing expectations (read the name from `config('app.name')` where the test builds strings, literal `'Fameja.app'` is fine in assertions):
- `PageMetaTest`: `<title>Accedi - Fameja.app</title>`, `og:title`, `<title>Log in - Fameja.app</title>`, `og:site_name content="Fameja.app"`, `og:image` ends with `/fameja-app-og-1200x630.png`, `apple-mobile-web-app-title content="Fameja.app"`.
- `FeedbackTest:165`: `You get this email because you look after Fameja.app.`
- `AppMailsTest:47,51,116`: `You asked to change your Fameja.app password.`, `We are writing to you because you have an account on Fameja.app.`, `toContain('Fameja.app')`.
- `MailgunRoutesTest:115`: `->toBe('Fameja.app - posta in arrivo')`.
- `CalendarFeedTest`: the feed has `PRODID:-//Fameja.app//Promemoria//IT` and `X-WR-CALNAME:Fameja.app · <family name>` (check how `Calendar::render` writes the name and assert that line).

- [ ] **Step 2: Run them, expect FAIL** on the old name

Run: `docker exec -u sail -e DB_DATABASE=testing_fameja family-manager-app-laravel.test-1 php artisan test tests/Feature/AppNameTest.php tests/Feature/PageMetaTest.php tests/Feature/FeedbackTest.php tests/Feature/Mail tests/Feature/Calendar/CalendarFeedTest.php`

- [ ] **Step 3: Implement**

- `config/app.php`: `'name' => env('APP_NAME', 'Fameja.app')`; `.env.example`: `APP_NAME="Fameja.app"`, drop the `VITE_APP_NAME` line.
- `PageMeta`: image `url('/fameja-app-og-1200x630.png')`.
- `Calendar.php`: `'PRODID:-//'.config('app.name').'//Promemoria//IT'`; `CalendarFeedController`: `config('app.name')." · {$family->name}"`.
- `MailgunRoutes`: `public static function description(): string` with PHPDoc; `sync()` uses it. (Routes are recognised by their target address, so existing routes are not affected.)
- `app.blade.php`: `content="{{ config('app.name') }}"`.
- Mails: keys become `'We are writing to you because you have an account on :app.'`, `'You asked to change your :app password. Tap the button to choose a new one.'`, `'You get this email because you look after :app. You also find it in the panel, under Reports.'`, each with `['app' => config('app.name')]`.
- `lang/it.json`: replace those three lines with the `:app` keys and Italian («Ti scriviamo perché hai un account su :app.», «Hai chiesto di cambiare la password di :app. Tocca il bottone per sceglierne una nuova.», «Ti arriva perché curi :app. La trovi anche nel pannello, in Segnalazioni.» — keep the current Italian wording, only the name becomes `:app`). `grep -rn "Family Manager" database/` must find nothing.

- [ ] **Step 4: Run the same tests plus `TranslationsTest`, expect PASS**; then PHPStan, Rector, Pint on the touched PHP files.

- [ ] **Step 5: Commit** — `git add` the files above; message `Name: Fameja.app on the server, from config('app.name')`.

---

### Task 2: Static files and the preview image

**Files:**
- Modify: `public/manifest.webmanifest:2-3`, `public/offline.html` (title and both sentences), `tests/Feature/FaviconTest.php:5` (test name only), `package.json` (script)
- Create: `resources/design/og-image.svg`, `scripts/og-image.ts`, `public/fameja-app-og-1200x630.png`
- Delete: `public/family-manager-og-1200x630.png`
- Test: `tests/Feature/AppNameTest.php`

**Interfaces:**
- Consumes: `config('app.name')` (Task 1), image path `/fameja-app-og-1200x630.png` (Task 1).
- Produces: `npm run og-image` → `public/fameja-app-og-1200x630.png`.

- [ ] **Step 1: Failing tests** in `AppNameTest`:
- `the manifest says the name`: decode `public/manifest.webmanifest`, `name` and `short_name` `->toBe(config('app.name'))`.
- `the offline page says the name`: `file_get_contents(public_path('offline.html'))` contains `<title>`.config('app.name').`</title>` and does not contain `Family Manager`.
- `the preview image is there`: `getimagesize(public_path('fameja-app-og-1200x630.png'))` is `[PageMeta::IMAGE_WIDTH, PageMeta::IMAGE_HEIGHT]`, and `public/family-manager-og-1200x630.png` does not exist.

- [ ] **Step 2: Run, expect FAIL.**

- [ ] **Step 3: Implement**
- Manifest `name` and `short_name`: `"Fameja.app"`. `offline.html`: «Fameja.app ha bisogno della rete…» and «Fameja.app needs the network to open. Check your connection and try again.», title `Fameja.app`.
- `resources/design/og-image.svg` 1200×630, same layout as the current image: diagonal gradient of the hero blues (light top left → darker bottom right, the colours of `tokens.json` written in hex, since rsvg does not read CSS variables), the logo of `public/favicon.svg` in white (roof stroke + three circles) on the left, «Fameja.app» bold white on the right, below it «Tutto ciò che riguarda la famiglia, in un posto solo.» on two lines. Font `Inter` with a sans-serif fallback.
- `scripts/og-image.ts` (TSDoc at the top, like `scripts/mail-icons.ts`): `rsvg-convert -w 1200 -h 630 resources/design/og-image.svg -o public/fameja-app-og-1200x630.png`; `package.json` `"og-image": "node scripts/og-image.ts"`. Run in Sail: `docker exec -u sail family-manager-app-laravel.test-1 npm run og-image`.
- Look at the PNG with Read: text not cut, font not a fallback serif. `git rm public/family-manager-og-1200x630.png`; update the image reference in `README.md` if it points to the old file.
- `FaviconTest`: test name `the svg favicon is the Fameja.app icon`.

- [ ] **Step 4: Run `AppNameTest`, `FaviconTest`, `PageMetaTest`, expect PASS.**

- [ ] **Step 5: Commit** — `Name: manifest, offline page and preview image say Fameja.app`.

---

### Task 3: The name in the pages — composable, wordmark, tab title

**Files:**
- Create: `resources/js/composables/useAppName.ts`, `resources/js/composables/useAppName.test.ts`, `resources/js/lib/pageTitle.ts`, `resources/js/lib/pageTitle.test.ts`
- Modify: `resources/js/app.ts:22,39,72`, `resources/js/types/global.d.ts:8` (drop `VITE_APP_NAME`), `resources/js/test/fixtures.ts:25`, `resources/js/components/fm/FmTopBar.vue:244`, `FmSidebar.vue:202`, `resources/js/layouts/AuthLayout.vue:72,148`, `resources/js/components/fm/FmLogo.vue:3` (TSDoc), `resources/js/composables/useInstall.ts` (TSDoc)
- Test: `FmTopBar.test.ts`, `FmSidebar.test.ts`, `AuthLayout.test.ts`, `AppLayout.test.ts`

**Interfaces:**
- Produces: `useAppName(): ComputedRef<string>` — `computed(() => usePage().props.name)`; `pageTitle(title: string, app: string): string` — `title ? \`${title} - ${app}\` : app`.

- [ ] **Step 1: Failing tests**
- `useAppName.test.ts`: with `usePage` mocked as in `useToSortCount.test.ts` and `props.name = 'Fameja.app'`, `useAppName().value === 'Fameja.app'`.
- `pageTitle.test.ts`: `pageTitle('Accedi', 'Fameja.app') === 'Accedi - Fameja.app'`; `pageTitle('', 'Fameja.app') === 'Fameja.app'`.
- `FmTopBar`, `FmSidebar`, `AuthLayout` tests: the wordmark is `getByText('Fameja.app')` (fixture `name: 'Fameja.app'`), and `queryByText('Family Manager')` is null.

- [ ] **Step 2: Run, expect FAIL.** `docker exec -u sail family-manager-app-laravel.test-1 npx vitest run <files>`

- [ ] **Step 3: Implement**
- Composable and helper with TSDoc.
- `app.ts`: a module `let appName = ''` set in `setup({ props })` from `props.initialPage.props.name` before mounting (Inertia's head manager calls `title` only after mount); `title: (title) => pageTitle(title, appName)`. Remove `VITE_APP_NAME`. Comment on line 72: *Install Fameja.app*.
- Wordmarks: `{{ appName }}` from `useAppName()` in `FmTopBar`, `FmSidebar`, `AuthLayout` (both places).
- Fixture `name: 'Fameja.app'`. TSDoc of `FmLogo` and `useInstall`: «Fameja.app».

- [ ] **Step 4: Run the tests above plus `pages.test.ts`, then types and lint, expect PASS.**

- [ ] **Step 5: Commit** — `Name: the pages read Fameja.app from the shared prop`.

---

### Task 4: Texts with `:app`, and the guard

**Files:**
- Modify: `resources/js/components/fm/navigation.ts:208`, `FmLinkPhoneSheet.vue:67`, `FmTitleTools.vue:24`, `FmTopBar.vue:261`, `resources/js/pages/Search.vue:188`, `pages/settings/Support.vue:100`, `pages/auth/AcceptInvitation.vue:31`, `pages/auth/Login.vue:41`, `pages/scan/Scan.vue:11,1094`, `resources/js/design/tokens.ts:54` + `tokens.test.ts:273` + `resources/css/tokens.css` (via `npm run tokens`), `app/Mail/AccountMails.php:13`, `app/Support/PageHead.php:14`, `app/Support/DefaultCategories.php:81`, `Dockerfile:3`, `composer.json:5`, `lang/it.json`
- Test: `tests/Feature/AppNameTest.php`, `navigation.test.ts`, `FmTitleTools.test.ts`, `FmTopBar.test.ts`, `FmBottomBar.test.ts`, `AppLayout.test.ts`, `Search.test.ts`, `Support.test.ts`, `Scan.test.ts`, `Home.test.ts`, `Areas.test.ts`, `Movements.test.ts`, plus `AcceptInvitation`/`Login` tests if present

**Interfaces:**
- Consumes: `useAppName()` (Task 3); in `.ts` files without a component (`navigation.ts`) read `usePage().props.name` where the item is built.

- [ ] **Step 1: Failing tests**
- `AppNameTest > nothing says Family Manager`: scan `app/`, `config/`, `resources/` (excluding `resources/js/generated/`), `lang/`, `public/manifest.webmanifest`, `public/offline.html`, `Dockerfile`, `composer.json`; every occurrence of `Family Manager` must be followed by ` Agent` (`preg_match_all('/Family Manager(?! Agent)/', …)` is 0 per file; report the file names on failure).
- In the page/component tests listed, replace expected «Family Manager» with «Fameja.app» (e.g. `getByRole('button', { name: 'Search in Fameja.app' })`, Italian tests «Cerca in Fameja.app», «Installa Fameja.app», «Non hai ancora Fameja.app?»).
- `AcceptInvitation`: one name → «Laura usa già Fameja.app per…», two → «Laura e Marco usano già Fameja.app per…».

- [ ] **Step 2: Run, expect FAIL.**

- [ ] **Step 3: Implement**
- Keys with `:app` and `{ app: appName }` (`$t`, `$tChoice`, `trans`, `transChoice`): `Search in :app`, `Install :app`, `No :app yet?`, `It reaches who makes :app`, `Frame the code with the phone camera: :app opens already with your account, without typing the password.`, `If you ask us for help, we can look at :app as one of your accounts. We only look, we change nothing, and only while you allow it.`, `{1} :names already uses :app for the deadlines, the bills and the documents of the home.|[2,*] :names already use :app for the deadlines, the bills and the documents of the home.`, and `:inviter invited you to :app, where the family keeps everyone's due dates, bills and documents.` (find its caller with grep and pass `app`).
- `lang/it.json`: same Italian as today with «Family Manager» → `:app`, old lines removed by hand; `git diff lang/it.json` shows only those lines. The three *Family Manager Agent* lines stay.
- Comments and PHPDoc: «Fameja.app» (`DefaultCategories`: «the categories of the Directus app»). `tokens.ts` header `/* Fameja.app — generated …`, `tokens.test.ts` expects `'/* Fameja.app'`, run `npm run tokens`. `Dockerfile` comment, `composer.json` description «Fameja.app, l'app per le famiglie.».

- [ ] **Step 4: Run all the tests above, `TranslationsTest`, types, lint, PHPStan, Rector, Pint, expect PASS.**

- [ ] **Step 5: Commit** — `Name: every text says :app, guard against Family Manager`.

---

### Task 5: The canvas (coordinator or a fork, not a fresh subagent)

**Files:** canvas `https://claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr`; copies in `.superpowers/sdd/fameja-app-name/boards/`

- [ ] **Step 1:** `Artifact list`, `scope: "files"`, `url` of the canvas; read every `project/*.dc.html` and find the boards that contain `Family Manager` (wordmark of top bar and sidebar, *Accesso*, *Navigazione*, mails, install sheet, search field…).
- [ ] **Step 2:** In each, the app name becomes «Fameja.app» (sentences too: «Cerca in Fameja.app», «Installa Fameja.app»); *Family Manager Agent* stays. Also send `project/ds/family-manager/tokens.css` = the new `resources/css/tokens.css` (header comment changed in Task 4).
- [ ] **Step 3:** Republish with `url` of the canvas and `root` on the folder holding `project/`, one board at a time (see the learnings on «You haven't viewed the latest version»). Read each board back and check that `Family Manager` is gone except the agent.
- [ ] **Step 4:** Read the design system (`https://claude.ai/artifact/FYg8PGZBFUW3SiwrjMWE2g`): if its card names the app, say «Fameja.app» there too (the design system keeps its own title).

---

### Task 6: Docs and production

**Files:** `AGENTS.md`, `README.md`, `.claude/learnings.md`; vault via the `project-docs` skill

- [ ] **Step 1:** `AGENTS.md` title and first line: «Fameja.app — app per le famiglie» and a line in *Regole del progetto*: the name is «Fameja.app» always, it comes from `config('app.name')` / prop `name` / `useAppName()`, texts use `:app`; the hand-written copies are manifest, `offline.html`, `og-image.svg` (`npm run og-image`) and the canvas. Add `npm run og-image` to *Comandi*. `README.md`: title and introduction say Fameja.app (the design system and the repository keep their names). Add to the env list of *Pubblicazione*: `APP_NAME=Fameja.app` (or unset).
- [ ] **Step 2:** Commit `Docs: the app is called Fameja.app`.
- [ ] **Step 3:** Run `project-docs` with what changed (name, one source, `:app`, preview image, agent unchanged).
- [ ] **Step 4:** Tell the owner: in Coolify `APP_NAME` must be `Fameja.app` or absent before the next deploy; ask before reading or changing it.
