# Changelog

All notable changes to Fameja.app.

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
