# Changelog

All notable changes to Fameja.app.

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
