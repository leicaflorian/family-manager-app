# The app is called Fameja.app

Decided with the owner on 15/11/2026. A small change: short design.

## Intent

The app changes name from *Family Manager* to **Fameja.app**, the same as its domains (`my.fameja.app`, `admin.fameja.app`, `mg.fameja.app`). Whatever the person sees says the new name.

## Goals

- The name is always written **«Fameja.app»**, never «Fameja» alone: logo/wordmark, page titles, sentences, emails, the name under the home-screen icon, the calendar, the link preview image (owner's decision).
- The name lives **in one place**: `config('app.name')` (`APP_NAME`) on the server, the shared prop `name` that `HandleInertiaRequests` already sends in the pages. Texts get it as a placeholder: `"Search in :app"` → «Cerca in :app».

## Non-goals (owner's decision)

- Internal names stay as they are: the `Fm…` components, the folder and repository `family-manager-app`, the database `family_manager_app`, paths, Coolify resources, `fm_` prefixes.
- *Family Manager Agent* (the scanner program, repository `family-manager-agent`) keeps its name for now, and so do the page texts that name it.

## Anti-goals

- A sentence that still says «Family Manager» after the change (outside the agent).
- A name that differs between the server, the pages and the static files.
- Production showing «Laravel» because `APP_NAME` is missing.

## Approaches

1. Write «Fameja.app» in place of «Family Manager» in every text. Simplest, but the name stays copied in about twenty places.
2. **One place for the name** (chosen by the owner): `config('app.name')` plus the shared prop, texts with `:app`. More parameters to pass now, and no name inside translation keys.

## The how, as decided

- `config/app.php`: default of `name` becomes `'Fameja.app'`; `.env.example` `APP_NAME="Fameja.app"`. In Coolify `APP_NAME` is set to `Fameja.app` (or left out, so the default applies): to be checked before the deploy.
- Pages: wordmark (`FmTopBar`, `FmSidebar`, `AuthLayout`), the title callback of `app.ts` and every `$t`/`trans` with the name read the shared prop `name` (passed as `:app`); `VITE_APP_NAME` stops being the source of the name.
- Server: `__()` in Blade, mails, `PageMeta`, `Calendar` (`PRODID`, feed name), `MailgunRoutes::DESCRIPTION` read `config('app.name')`. Note: `MailgunRoutes::sync` recognises the app's routes by their address, not the description; to be confirmed while building.
- Translation keys with the name get `:app` in English and Italian (`lang/it.json`, keys added by hand as the learnings say); the old keys are removed, and a search in `database/` checks that no seeder still uses them.
- Static files that cannot read the config, written by hand: `public/manifest.webmanifest` (`name`, `short_name`), `apple-mobile-web-app-title` (it can read the config, since it is in Blade), `public/offline.html`, the link preview image (renamed to `fameja-app-og-1200x630.png` and redrawn with the new name). A test fails if manifest and `offline.html` don't say `config('app.name')`.
- Canvas: the wordmark in the component boards (*Navigazione*, …) and the screen boards that show it (*Accesso*, top bar, sidebar) say «Fameja.app»; the design system's card too, if it names the app.
- Docs: `AGENTS.md`, `README.md`, the vault (skill `project-docs`) say the app is called Fameja.app; the history (old plans, specs) is not rewritten.
- TDD: the tests that look for «Family Manager» (`FmTopBar`, `FmSidebar`, `AuthLayout`, `PageMetaTest`, `FaviconTest`, `FeedbackTest`, mails…) change first to the new name read from the config/prop.

## Constraints

- Another session has open changes on `lang/it.json`, `navigation.ts`, `FmSidebar`, `FmBottomBar`, `AppLayout` (the release notes panel): those files are touched after its commit.
- Only the checks of the touched files before the commit; full suites in CI.

## Left to the builder

- How to redraw the link preview image (same layout as the current one).
- Whether `useInstall`, comments and PHPDoc that say «Family Manager» change to «Fameja.app» (yes for user-facing descriptions of the app, e.g. *Install Fameja.app*; internal comments may follow).
