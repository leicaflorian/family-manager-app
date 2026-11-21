# Changelog

All notable changes to Fameja.app.

## [2.5.0] - 2026-11-21

### Consumption
- New Consumption page (budget.consumption) for electricity, gas and water utilities: 12-month window with previous/next and Today, Consumption/Spending chart with month detail, four figures, a sentence comparing with the previous year, bills one by one, bills to check, archive still to read.
- New utility_consumptions table, model, policy, enums (ConsumptionUnit, ConsumptionSource, ConsumptionOutcome) and Consumption / ConsumptionPage services; quantities are written with at most one decimal.
- Bill reading extracts consumption (ConsumptionReaderAgent, ReadConsumption job, ConsumptionCheck limits); an error after the paid call sends the bill to To check; no energy amount for water bills.
- Import: new Record the consumption box (SaveConsumption), refusing out-of-range or reversed periods.
- Bills already in the archive can be read in bulk (QueueConsumptionReadings, ArchiveReadingRun).
- Correct or add a consumption by hand (ConsumptionSheet, UtilityConsumptionController, shared throttle consumption-save).
- Utility card shows a Usage section with the last 12 months.
- Reading log (ai_readings) records file and outcome, and the family's average cost.
- Seeder: Bianchi consumption data.

### Budget
- Planned and spent is now a page of its own under Budget (sidebar accordion, box in Movements), with the wide two-column layout and the page trail.
- Sidebar: Budget becomes an accordion like Areas.
- By category: a narrower name column on the computer; on the tablet the bar and its words go under the name.

### Charts
- FmBarChart: units, last year's mark, choosable bars, euros above the chosen bar, embedded mode inside a card.
- The value of a bar shows in a tooltip on hover, opening on top of the bar, with the year before on its own line.

### Migrations
- 2026_11_21_000001_create_utility_consumptions_table and 2026_11_21_000002_add_outcome_to_ai_readings_table (new table and a new nullable column).

## [2.4.0] - 2026-11-21

### Banks
- New bank link (Enable Banking behind the `BankFeed` contract, `fake` bank for local work), switched on per family from the owner panel.
- *Linked banks* settings page: connect, reconnect and disconnect a bank; reconnecting reuses the account, disconnecting keeps refused or put-aside transactions.
- `banks:sync` reads transactions three times a day (07:00, 13:00, 19:00) with a job per connection, a re-check and a timeout shorter than the queue retry.
- Consent watching: a notice and an email before a consent ends, and a connection marked to reconnect when it has ended.
- *From the bank* tab in the Budget: filters, refresh, rows as on the boards, a blue badge with the number to sort, sheet to create an expense or settle a planned item, ignore, and hint for monthly recurring transactions.
- Planned expenses and incomes get a *How it shows in the bank* rule (with an AI suggestion when reading is on); a recognised transaction pays its due date by itself, with a bell notice to the family and *Not this one* to undo it.
- Movements show a line for a payment recorded by the bank, with its undo.
- Lock order fixed (bank row first, then the planned item) so two payments of one due date never run together; new bank tables and models in a single migration.

### Pages and navigation
- `FmTitle` is the title of every page; Back and the trail sit above the page on phone and tablet (`FmPageTrail`) and in the top bar on the computer; forms close with Back or Cancel, no X.
- *Received mail* is its own section, and its address copies with a click.
- Family uses underlined tabs like the Budget; the Budget is the title of its three pages.
- *To sort* is a round button with the count beside search and bell on the phone.
- `FmTabs` pill tabs keep a longer name on one line.
- Card page header fades from the area colour to a lighter one (new `*-dark-end` design tokens).

### Infrastructure
- Every `throttle` has its own counter (Fortify and Livewire included); `RouteThrottleTest` enforces it.
- Pre-commit hook runs `vp check` and Pint on staged files; `./release` no longer runs checks before the merge.
- `.env.example` lists the bank variables.

## [2.3.3] - 2026-11-21

### Release tooling
- `./release` now runs the CI checks (Pint, PHPStan, Rector, types, `vp check` and all of Vitest) on `dev` before merging into `main`. PHPStan and Rector run in a throwaway container, away from the shared `/tmp` cache of the worktrees.
- `notes.json`, written by PHP, is excluded from Oxfmt.
- Removed the two `unbound-method` warnings in the `FmBackLink` and `FmTitle` tests that stopped `vp check`.

## [2.3.2] - 2026-11-21

### iPhone and iPad
- Formatted `public/manifest-ios.webmanifest` so the CI formatting check passes.

### Tests
- The sidebar test now fakes the visit of the link it clicks, so it no longer fails in the full CI run.

## [2.3.1] - 2026-11-20

### Layout and navigation
- Layout modes: tablet from 768 to 1279 px, computer from 1280 px (`useLayoutMode()`).
- Sidebar: always open on the computer; on the tablet it stays collapsed to the icons (76 px), opens over the page on hover or touch (closing when the page opens) and can be kept open with *Keep the menu open*; animated, icons stay still while it opens.
- Top bar of the tablet: 20 px side margins; the menu starts with the bar of the page, with *Back* where the bar has *Menu*; square bar buttons live in `lib/topBar.ts`.
- Bottom bar: the badge of the + button has a ring in the card colour.
- `FmTitle` and `FmBackLink` accept a back without an address (a button that notifies the page).

### Documents, scan and scanner
- To sort / Upload / Scan: ‹ back link above the title instead of the ×; Upload and Scan go back to the page they came from, and ask before throwing pages away.
- To sort: on the phone the + of the bar has *Take a photo* and *Choose a file*.
- Upload: on a Mac or Windows computer, a card for the scanner beside the drop zone.
- Scan: *Scan a sheet* of *Add* opens the camera at once (`?camera=1`); crop in two steps (corners, then *Improve* as a 0–100 slider on the cut sheet); on the tablet the app top bar stays over the photos.
- Scanner page follows the boards *Scanner* and *Scanner-pc*: own scroll areas, settings and put-aside sides components.

### PWA
- iPhone and iPad get `manifest-ios.webmanifest` (scope `/`, no start address), so the home-screen app keeps the whole site without Safari bars.
- Splash screens for 14 iPhones and 9 iPads (`apple-touch-startup-image`, light and dark, portrait and landscape), written by `SplashScreens` and drawn by `npm run splash-screens`.

### Docs
- Learnings: canvas artboard links and reka-ui popover names.

## [2.3.0] - 2026-11-19

### Owner panel
- Create a family from the panel (`CreateFamilyFromPanel`): family, first admin account, categories in the chosen language and a *Family created* log line; a signed 7-day welcome link (`start/{user}`) opens the choice of the password and confirms the email when it is saved. The link is tied to the email fingerprint.
- `SendWelcomeMail`: a failed mail does not stop the panel (the family stays, the error is logged); the mail goes out in the account's language; *Send again* is offered only for accounts created by the panel and until the email is confirmed.
- Mail pictures are served from the host of `APP_URL`, also when sent from the panel.
- `TracksAuthors` never writes an owner of the panel as author (it reads only the `web` guard and not while Filament is serving).

### Reminders and mail
- Weekly summary mail sent on Monday to the admins (sections of the week, late dates with the month, titles in the admin's language); a second Monday run still mails new notices. The morning mail shares its notice lines with it.
- Paying the due date of a card (first and last instalment, road tax, inspection, policy renewal) marks done the open automatic reminder of that deadline on the same day.

### Agenda and areas
- A deadline paid by the payment of its card on the same day leaves the Agenda and the Home with it (before, the bare deadline stayed, late).
- Rows of an area always show the cost; `CardDueDates` looks two years ahead and the next date is bold when it is within 30 days.
- The next due date of a planned expense shows its year when it is not this year.

### Budget
- A movement opens on its own page, with its form in pairs and its documents beside it; it saves its documents and files.
- The tab of planned expenses and incomes is now *Recurring*.
- A new movement from a list filtered on incomes starts as an income; list and search no longer send the documents.
- Import: files can come from an expense or an income.

### People and links
- The page of a person is in boxes, a few rows at a time; each list of documents loads its next page on its own and a row never shows twice (`FmShowMore`).
- Linked records are shown three at a time; the number beside the title of a card sits on the title line.

### App
- Notice of a new version in the open pages (`VersionController`, `useNewVersion`).
- The bottom bar's *Add* and *More* close when the page changes; menus open above the panels.
- Password managers leave searched menus and dates alone (Bitwarden opened over *Link it to*).

### Docs
- Specs and plans for the weekly summary, the new-version notice, the movement page, many documents of a person and creating a family from the panel; learnings updated.

## [2.2.2] - 2026-11-18

### Tests
- Renamed a global helper in the subscription tests that clashed with one in the due date tests, which stopped the whole Pest suite in CI.

### Docs
- Added a learning about unique names for global test helpers.

## [2.2.1] - 2026-11-18

### Planned expenses
- Optional commission (`fee_amount`) on planned expenses, added to the amount: due dates are worth amount plus commission.
- Each payment now leads to its movement.
- Payments carry the commission through "I paid it", a chosen planned item and the import.
- Forms (expense, "I paid it", planned expense, changed due date) work with cost + commission.
- Rows and the amount component show the commission an amount includes ("of which 1,00 € commission"), also in the planned expenses list and page.

### Subscriptions
- A new subscription can create its planned expense in the same form (cost, how often, first payment, category, "I already paid it"), following the Nuovo-abbonamento boards.
- The planned expense section asks before leaving the page with unsaved changes.

### Database
- Migration adding `fee_amount` to `planned_expenses` (nullable, compatible with the previous code).

## [2.2.0] - 2026-11-18

### Budget
- Movements: search box and filters (text, kind, account, amounts, days) on the server, results grouped by month, a note when a search is cut at 200 results; filters column on the computer, sheet on the phone.
- Planned expenses: search box and filters (amounts, next due date), shared with Movements through `useListFilters`, `FilterRanges` and `FmFilterPills`.
- Due dates no longer have a page of their own: the address opens the planned expense with the right panel (I paid it, Change this due date) or the movement that paid it; saving goes back to the page it was opened from.
- An admin can pay an unpaid due date from the Change this due date panel.
- A skipped due date reached from an old link now shows a toast instead of nothing.
- Paying a policy premium: same company renews the policy, another company opens the new policy form.

### Cards
- A new policy can cancel the old one of the same vehicle and take its premium (`ReplacePolicy`, `ReplaceablePolicies`); also available from Import.
- Card page: status shown as a coloured figure, figures in one box with the tiles first; a paid off loan is grey, a cancelled or sold card's past expiry is no longer red.
- Refers to menu tells the status or expiry of a closed or expired card, so two similar policies can be told apart.

### Documents
- On the computer layout the chosen document opens in the left column in place of the filters (embedded `FmDocumentDetail`), with its menu in the column header.
- Documents use the shared filter composable and pills; new `FmFilterAside` and `FmFilterButton`.

### Layout
- Page columns start from 900 px (`wide:` and `computer:` variants) instead of Tailwind's `lg:` at 1024 px, with a test that keeps them aligned with the composables.
- Back after a save reloads the page, so Home no longer lists a due date just paid.

### Fixes
- Scanner: the feeder button for another ID card has its own text.
- Organization form: the phone field reads «Telefono».
- Person and Organization pages no longer show a back link on the computer.
- Settings › Calendar: a member sees who can turn the calendar on.
- Category tree: a main category counts the documents of its subcategories.
- A phone not linked any more lands on the code page with the message under the field.

## [2.1.4] - 2026-11-15

### Production
- Fixed 502 on pages with many files (for example a card page): the `Link` preload header now lists at most 20 files, and nginx's FastCGI buffer is raised to 16k, so headers plus cookies no longer exceed the limit ("upstream sent too big header").

### Feedback reports
- Browser diagnostics now leave out the harmless ResizeObserver loop message and FormKit Pro's warning for an empty datepicker, so reports show only real problems.

## [2.1.3] - 2026-11-15

### Public assets
- Mail icons moved from `public/mail/icons` to `public/mail-icons`: nginx served the `public/mail` folder directly, so the owner panel's Posta page (`/mail`) answered 403.
- `PublicFoldersTest` fails when a folder of `public/` has the path of a GET page, with panel pages checked at the root of their host.

### Docs
- AGENTS: zero-downtime deploys need migrations that the previous code can live with.

## [2.1.2] - 2026-11-15

### Build
- Dockerfile now uses syntax 1.10, which is needed for the `env=` secret mounts Coolify adds to every `RUN` (the build stopped with "unexpected key 'env'" on 1.7).

### Release
- The Coolify deploy call in the release workflow is now a `POST` (Coolify 4.7 answers 405 to a `GET`).

### Docs
- Updated AGENTS.md and learnings with the two points above.

## [2.1.1] - 2026-11-15

### Tests
- FilePreviewsTest: the HEIC memory test now uses a dedicated 8000 × 6000 photo fixture (`tests/Fixtures/big-photo.heic`), so the memory PHP keeps reserved can never fit it and the test is reliable.

## [2.1.0] - 2026-11-15

### Agenda
- New calendar view: a month grid (bounded to 24 months from today) with a day list that follows the chosen day; the chosen view is remembered.
- Month data carries open and done entries; a paid subscription fee and the done renewal reminder it carries now show as one done row; incomes are left out of the month sum; weeks run Monday to Sunday.
- `FmMonthGrid` draws the weeks the server sends (4 to 6) as a labelled group, each day named with its weekday; short months in Italian.
- New *Connect a calendar* panel (`FmCalendarLink`) with the address and the steps for Google, Apple and Outlook; the address is sent only on a partial reload that asks for it and never during an owner visit. The Settings calendar page reuses the same component.
- `FmAgendaRow` gets a compact variant for the day list and a checked state; `FmTabs` supports icon tabs; `FmAgendaPanel` shows *Paid* for a paid payment and links to its due date.

### CI
- `ci.yml` runs only for a release (called by `release.yml` on the tag) or by hand, as parallel jobs (static checks, Pest, two Vitest shards) sharing a setup action.
- Tests: the build version is `__APP_BUILD__`, async queries wait 3 s, FormKit Pro's late key check is ignored in Vitest.

## [2.0.0] - 2026-11-15

### App
- New Laravel + Inertia + Vue app for families, replacing the Directus-based Family Manager: families with `admin` and `member` accounts, invitations, strict separation of family data.
- App renamed to Fameja.app, read from `config('app.name')` on the server and from the shared `name` prop in the pages.
- Light, dark and automatic theme chosen per account; design tokens generated from the Claude Design system.
- Italian and English texts, chosen from the browser language.

### Documents and cards
- Documents with file upload, previews of PDF and HEIC files, tags, categories and links to cards.
- 13 card types described in `app/Cards/`, grouped by area, with dates, expenses and linked documents.
- People and organizations with photo and logo.
- Document categories can say which card they belong to.

### AI reading and import
- Documents are read by an AI (Gemini, Anthropic or Ollama) with a monthly limit per family and a cost report (`ai-readings:report`).
- *To sort* and *Import* pages: proposals of documents, expenses, incomes, payments and cards, with *What I do too* boxes that say what is missing.

### Budget and loans
- Planned expenses and incomes, due dates, payments, movements per month, planned vs spent by category.
- Loans with schedule, totals and nightly refresh.

### Agenda, reminders and calendar
- One Agenda of things to do with deadlines, payments and tasks, done view, filters remembered per account, and *I paid it* inside the panel.
- Reminders with notice rules, the bell and the Notices page, morning round with email.
- Calendar of the family to subscribe to (`.ics`).

### Home, search and navigation
- Home with what to pay, what expires and the documents to complete.
- Global search over documents, cards, expenses, incomes, deadlines, people and organizations.
- New top bar with trail and Back for tablet and computer, sidebar with areas, wide layouts for tablet and computer.
- Settings: areas order and visibility, calendar, address for the bills, version and *What's new*.

### Received mail
- Each family has an address for the bills; mail arrives through Mailgun, is checked by the AI and its attachments are saved to *To sort*. Toasts and mails restyled with the design system.

### Scan from the phone
- Scan page with crop, straighten, improve and PDF in the browser, installable on the home screen, *Share* from Android, and the home scanner.
- Linked phones: a phone linked with a code opens only the scan.

### Reporting
- *Report a problem or an idea* with page screenshot, browser details and server errors, emailed to the owner and shown in the panel.

### Owner panel
- Filament panel on its own host (`ADMIN_DOMAIN`) with two-factor authentication: families, accounts, suspend and delete, settings, AI readings, mail, reports, support access and *Enter as…* in look-only mode.
- Sign-up can be closed with `REGISTRATION_OPEN`; `user:password` and `owner:create` commands.

### Production and releases
- Production image on serversideup/php (web, queue and scheduler in one container) for Coolify; scheduled commands safe during a rolling deploy.
- Nightly backup of family files to the Space (`files:backup`), mail through the Mailgun API, copy from Directus (`directus:copy`).
- Single `VERSION` file, `./release` script, changelog and notes written with Claude, GitHub Release and Coolify deploy after the CI checks.
- CI on PHP 8.5 with MySQL: Pest, PHPStan, Rector, Pint, types, `vp check`, Vitest and build.
