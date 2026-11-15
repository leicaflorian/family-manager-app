# Production on Coolify Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** the app ready to host the owner's family in production on Coolify (`my.fameja.app`, panel on `admin.fameja.app`), tried online, with the move day written as a checklist.

**Architecture:** a `Dockerfile` on `serversideup/php` (fpm-nginx) runs web, queue worker and scheduler in one container. It uses the existing MySQL 9 and Redis of Directus, with a database, a user, a Redis index and a key prefix of its own. The panel moves to its own host, and *Enter as…* crosses the hosts with a one-time signed link. Mail goes through Mailgun on `mg.fameja.app` (API for sending, routes for receiving). Sign-up is closed, and a nightly command copies the family files to the DigitalOcean Space. The agent creates the Coolify resources with the write token, and the owner gives the go for each block.

**Tech Stack:** Laravel 13, Fortify, Filament 5, Pest, Inertia + Vue 3, Vitest, `serversideup/php`, `symfony/mailgun-mailer`, `league/flysystem-aws-s3-v3`, Coolify 4 API, MySQL 9, Redis 8.

**Spec:** `docs/superpowers/specs/2026-11-15-coolify-production-design.md`

## Global Constraints

- Hosts: app `my.fameja.app` (`APP_URL=https://my.fameja.app`), panel `admin.fameja.app` (`ADMIN_DOMAIN`). Without `ADMIN_DOMAIN` (local, tests) the panel stays at `/admin` on the app host, as today.
- Cookies host-only: `SESSION_DOMAIN` empty, never `.fameja.app`.
- *Enter as…* link: signed, valid 60 seconds, usable once.
- MySQL: database `family_manager_app` and user `family_manager_app` in the existing MySQL 9 of Directus. For the copy only, a user `family_manager_app_ro` with `SELECT` on `family_manager`, dropped after the move.
- Redis: the existing Redis, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `REDIS_PREFIX=fm_app_`, `REDIS_DB`/`REDIS_CACHE_DB` on indexes Directus does not use. `QUEUE_CONNECTION=database`.
- Mail: `MAIL_MAILER=mailgun`, domain `mg.fameja.app`, `MAIL_FROM_ADDRESS=noreply@mg.fameja.app`, EU endpoint, a sending key (`MAILGUN_SECRET`) separate from `MAILGUN_API_KEY` (routes). `MAIL_INBOX_DOMAIN=mg.fameja.app`.
- Sign-up: `REGISTRATION_OPEN=false` in production, open by default.
- AI reading on: `DOCUMENT_READER_PROVIDER=gemini`, the Gemini key already used by Directus.
- File backup: the Space `family-manager`, prefix `family-manager-app/files/`; deleted files kept under `deleted/<Y-m-d>/` for 30 days.
- PHP limits: post 100M, upload 26M, 100 files; nginx body limit ≥ 100M.
- `schedule:work` in the container, never also a Coolify `schedule:run` task.
- Never `migrate`, `migrate:fresh`, `db:seed` against `laravel` (local dev, real family copy) or against production data without a dump taken right before. No `db:seed` in production.
- Every action on the droplet, Coolify, DNS, Mailgun or Directus waits for the owner's go, given for each block of Task 8. Tokens are never printed: Coolify tokens (`~/.config/family-manager/coolify-token-write`) go to `curl` through a header file, secrets go to Coolify through the API, never into the repository or the logs.
- Everything in English with Italian in `lang/it.json` (new keys added by hand before the final `}`; `git diff --stat lang/it.json` shows only added lines). TDD; `declare(strict_types=1)`, `final`, PHPDoc/TSDoc, PHPStan 9, no `any`.
- Work in the worktree `.claude/worktrees/coolify-production` on branch `coolify-production` from `dev` (other sessions have open changes on `dev`). Pest one at a time with its own database: `docker exec -u sail -e DB_DATABASE=testing_coolify -w /var/www/html/.claude/worktrees/coolify-production family-manager-app-laravel.test-1 php artisan test --filter=…`. Full suites only before the last commit (see `.claude/learnings.md`).
- `compose.yaml` and `AGENTS.md` have open changes from another session on `dev`: Task 4 and Task 7 merge `dev` first and touch those files only after that session's commit.

## Review Focus

- An *Enter as…* link opened after the table was drawn a few minutes before still works, because the link is made at the click and not when Filament draws the table → test in Task 3 (the action makes the link at the moment it runs).
- A request to the app's pages on `admin.fameja.app` (for example `/login`, `/dashboard`, `/mail/mailgun`), or to the panel on `my.fameja.app`, answers 404 and never shows the wrong sign-in → tests in Task 2.
- With sign-up closed, a `POST /register` sent by hand creates nothing (404), and an invitation link still lets the person create an account → tests in Task 1.
- A file deleted by mistake in the app can be taken back from the backup during 30 days, and a file deleted 31 days ago is gone from the backup → tests in Task 5.
- *Leave* after a visit writes the end of the visit once, even though signing the owner out of the app session fires `Logout` of the `owner` guard → test in Task 3.

---

### Task 0: The worktree

- [ ] Create `.claude/worktrees/coolify-production` on branch `coolify-production` from `dev`. In the container from that folder run `composer install` and `npm ci`, copy `.env` with `DB_DATABASE=testing_coolify_dev` and run `npm run build` (needed by `DewarpCheckTest`). See `.claude/learnings.md`, *Un worktree con Sail*.
- [ ] Check: `php artisan test --filter=PageMetaTest` is green in the worktree with `DB_DATABASE=testing_coolify`.

### Task 1: Sign-up closed by configuration

**Files:**
- Modify: `config/fortify.php` (`Features::registration()` only when `config('fortify.registration_open')`, key `registration_open => env('REGISTRATION_OPEN', true)`)
- Modify: `app/Support/PageMeta.php` (`INDEXABLE` becomes a method `indexable(): list<string>`: `register` only while open)
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` or the login view data: prop `canRegister: bool` (check how `Login.vue` gets its props today; the starter kit passes `canRegister` from `FortifyServiceProvider::loginView`)
- Modify: `resources/js/pages/auth/Login.vue` (no *Sign up* link when `canRegister` is false)
- Modify: `.env.example` (`REGISTRATION_OPEN=true` with a comment)
- Test: `tests/Feature/Auth/RegistrationClosedTest.php`, `resources/js/pages/auth/Login.test.ts`

**Interfaces:**
- Produces: `config('fortify.registration_open'): bool`; `PageMeta::indexable(): list<string>`.

- [ ] **Write the failing tests** in `RegistrationClosedTest`. Fortify reads features at boot, so each test sets the config and refreshes the application, the way `DewarpCheckTest` restarts it:
  - `closed sign-up hides the register page`: `GET /register` → 404.
  - `closed sign-up refuses a register post`: `POST /register` with valid data → 404, `User::count()` and `Family::count()` unchanged.
  - `closed sign-up still accepts an invitation`: an invitation from a Bianchi admin, open its link and create the account → the user exists in the Bianchi family (reuse the steps of the existing invitation test).
  - `closed sign-up keeps register out of the indexable pages`: `PageMeta::indexable()` === `['login']`; the `/login` response has no `X-Robots-Tag`.
  - `open sign-up is unchanged`: `GET /register` → 200.
- [ ] **Write the failing Vitest case** in `Login.test.ts`: `hides the sign-up link when sign-up is closed`: render with `canRegister: false` → `queryByRole('link', { name: 'Sign up' })` is null; with `true` it is there. Use the link's real text as the page writes it.
- [ ] Run both, see them fail for the right reason.
- [ ] Implement.
- [ ] Green (`--filter=RegistrationClosedTest`, `--filter=PageMeta`, `npx vitest run resources/js/pages/auth/Login.test.ts`).
- [ ] Commit `Sign-up can be closed by configuration (REGISTRATION_OPEN)`.

### Task 2: The panel on its own host

**Files:**
- Modify: `config/app.php` (`'admin_domain' => env('ADMIN_DOMAIN')`)
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (`ADMIN_DOMAIN` set → `->domain($domain)->path('')`; empty → `->path('admin')` as today)
- Create: `app/Http/Middleware/SeparateHosts.php`, appended to the `web` group in `bootstrap/app.php`
- Modify: `.env.example` (`ADMIN_DOMAIN=` empty, with a comment)
- Test: `tests/Feature/Admin/AdminHostTest.php`

**Interfaces:**
- Produces: `config('app.admin_domain'): ?string`; `SeparateHosts::onAdminHost(Request): bool`.
- `SeparateHosts` rule, only when `admin_domain` is set: on the admin host only routes named `filament.*` and the Livewire ones (`LimitVisit::PANEL`) pass, everything else → 404. On any other host a `filament.*` route → 404 (the `->domain()` already does it; the middleware is the safety net). `calendar.feed` and `scan.share` are outside `web` and keep answering only on the app host because their URLs are built from `APP_URL`; a test pins it.

- [ ] **Write the failing tests** in `AdminHostTest`. Boot with `ADMIN_DOMAIN=admin.fameja.app` and `APP_URL=https://my.fameja.app` by refreshing the application:
  - `the panel answers on the admin host`: `GET https://admin.fameja.app/` → redirect to the panel login on `admin.fameja.app`; `GET https://admin.fameja.app/login` → 200 with the Filament login (not the Inertia page).
  - `the app pages do not answer on the admin host`: `GET https://admin.fameja.app/dashboard`, `/register`, `/forgot-password`, `POST https://admin.fameja.app/mail/mailgun` → 404.
  - `the panel does not answer on the app host`: `GET https://my.fameja.app/admin` → 404; `GET https://my.fameja.app/login` → the Inertia login.
  - `an owner signs in on the admin host only`: owner login on admin host → panel dashboard 200; the same session cookie is not sent to `my.` (assert the `Set-Cookie` has no `Domain` attribute).
  - `without ADMIN_DOMAIN the panel stays at /admin`: `GET /admin` → panel redirect, as today (existing `OwnerAccessTest` stays green).
- [ ] Run, see them fail.
- [ ] Implement.
- [ ] Green (`--filter=AdminHostTest`, `--filter=OwnerAccessTest`, the whole `tests/Feature/Admin` folder).
- [ ] Commit `Owner panel on its own host (ADMIN_DOMAIN)`.

### Task 3: *Enter as…* with a one-time link

**Files:**
- Create: `app/Support/VisitHandoff.php`
- Modify: `app/Filament/Resources/Families/RelationManagers/UsersRelationManager.php` (the `enterAs` action makes the link when clicked and opens it in a new tab with `$livewire->js('window.open(…)')`, not as `->url()` made when the table is drawn)
- Modify: `routes/web.php` (`support/visit/{user}`: drop `auth:owner`, keep `signed`, add the `token` query parameter)
- Modify: `app/Http/Controllers/Admin/VisitController.php` (`start` consumes the token, signs the owner in on the `owner` guard of this session, then `StartVisit`; `leave` also signs the owner out of this session and goes to the panel URL)
- Modify: `app/Http/Middleware/LimitVisit.php` (`LINK_MINUTES = 30` is replaced by `LINK_SECONDS = 60`)
- Check: `app/Listeners/EndVisitOnOwnerLogout.php` (it must not write a second end after `leave`)
- Test: `tests/Feature/Admin/VisitTest.php`, `tests/Unit/Support/VisitHandoffTest.php`

**Interfaces:**
- Produces:
  - `VisitHandoff::link(Owner $owner, User $user): string`: stores `['owner_id' => int, 'user_id' => int]` under `visit-handoff:<token>` in the cache for `LimitVisit::LINK_SECONDS`. The token is 40 random characters (`Str::random(40)`). Returns `URL::temporarySignedRoute('support.visit.start', now()->addSeconds(LimitVisit::LINK_SECONDS), ['user' => $user->id, 'token' => $token])`, which is absolute on `APP_URL` (the app host) even when called from the panel host. Force the root URL to `config('app.url')` for this one call.
  - `VisitHandoff::consume(string $token, int $userId): ?Owner`: `Cache::pull`, then null when the entry is missing or its `user_id` differs.

- [ ] **Write the failing unit tests** in `VisitHandoffTest`: `link points to the app host` (starts with `https://my.fameja.app/support/visit/` with `APP_URL` set and the panel host in the request); `consume returns the owner once` (second call null); `consume refuses another user` (null, and the entry is gone); `the entry expires after 60 seconds` (`travel(61)->seconds()` → null).
- [ ] **Write the failing feature tests** in `VisitTest`, adapting the existing ones (support permission, suspended family, authenticator rule, visit log):
  - `a handoff link starts the visit without a panel session`: a fresh client (no owner session) opens the link → redirected to `dashboard`, signed in as the user, the visit logged once.
  - `a handoff link works once`: second opening → 403, nothing logged.
  - `an expired handoff link is refused`: after 61 s → 403.
  - `a changed user id is refused`: same token, another user id in the URL → 403 (the signature breaks).
  - `the enter as action makes the link when clicked`: with Livewire, call the action 10 minutes after mounting the table → the dispatched JS contains a link that works.
  - `leave ends the visit once and goes to the panel`: `POST visit/leave` → `Inertia::location` to the panel dashboard URL; one end row in the visit log; the owner guard of this session is signed out.
  - The existing permission cases (no support permission → 403, suspended family, owner without authenticator secret where required) keep their expected answers through the new link.
- [ ] Run, see them fail.
- [ ] Implement.
- [ ] Green (`--filter=VisitTest`, `--filter=VisitHandoffTest`, `--filter=LimitVisit`, `tests/Feature/Admin`).
- [ ] Commit `Enter as: one-time link across the app and panel hosts`.

### Task 4: Mailgun for sending, MySQL 9 and Redis in Sail

**Files:**
- Modify: `composer.json` (`symfony/mailgun-mailer`, `symfony/http-client`)
- Modify: `config/mail.php` (mailer `mailgun`: `transport => 'mailgun'`, `domain => env('MAILGUN_DOMAIN')`, `secret => env('MAILGUN_SECRET')`, `endpoint` = the host of `MAILGUN_ENDPOINT` without scheme (`parse_url(…, PHP_URL_HOST)`), because Laravel's transport wants a host, while `services.mailgun.endpoint` keeps `https://` for `MailgunRoutes`)
- Modify: `compose.yaml` (MySQL image to the same 9.x as production, read in Task 8 step 1; a `redis` service; `laravel.test` depends on it). Only after the other session's commit, merging `dev` first.
- Modify: `.env.example` (`MAILGUN_DOMAIN`, `MAILGUN_SECRET`; `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `REDIS_HOST=redis`, `REDIS_PREFIX=fm_app_`)
- Test: `tests/Feature/Mail/MailgunTransportTest.php`

- [ ] **Write the failing test** `the mailgun mailer uses the api on the eu host`: with `MAILGUN_DOMAIN=mg.fameja.app`, `MAILGUN_SECRET=key-test`, `MAILGUN_ENDPOINT=https://api.eu.mailgun.net` → `Mail::mailer('mailgun')->getSymfonyTransport()` is a `MailgunApiTransport` and `(string)` of it contains `api.eu.mailgun.net` and `mg.fameja.app`. No real call.
- [ ] Run, see it fail; add the packages and the config; green.
- [ ] Commit `Outgoing mail through the Mailgun API`.
- [ ] **The copy and the new inbox domain.** Write the failing test in `tests/Unit/DirectusMapTest.php` (next to the existing `mail_inbox_recipient` case): `the copy moves the bills address to the configured inbox domain`. With `services.mailgun.inbox_domain = mg.fameja.app`, `Bollette.Leica@mg.leicaflorianrobert.dev` becomes `bollette.leica@mg.fameja.app`. With the inbox domain empty it becomes `null`, and `mail-inbox:addresses` gives a new one later. An address already taken (`FamilySetting::addressTaken()`) also becomes `null`. Change the mapping in `app/Copy/Copier.php` (around line 316). Green. Commit `Copy from Directus: the bills address on the new inbox domain`. The move-day checklist (Task 7) then sets `MAIL_INBOX_DOMAIN` before `directus:copy`.
- [ ] **MySQL 9 locally.** Owner's go first: the `sail-mysql` volume holds `laravel`, the copy of the real family. Dump it as in *Rifare la copia da Directus* step 1 (`storage/app/backups/laravel-before-mysql9-<date>.sql`). Change the image, `sail up -d`, check `SELECT VERSION()` and that `laravel` opens (`migrate:status`, read only). 8.4 → 9.x is an in-place upgrade with no way down: if it fails, a new volume and the dump restored.
- [ ] **Redis locally.** Local `.env` with Redis for cache and sessions; sign in on `http://localhost:8090` with Playwright (headless Chromium, see `.claude/learnings.md`) and see `fm_app_` keys with `redis-cli --scan`. Tests keep `phpunit.xml`'s array drivers.
- [ ] Pest `tests/Feature/Schema` and `tests/Feature/Auth` green on MySQL 9.
- [ ] Commit `Sail: MySQL 9 and Redis like production`.

### Task 5: Nightly backup of the family files

**Files:**
- Modify: `composer.json` (`league/flysystem-aws-s3-v3`)
- Modify: `config/filesystems.php` (disk `backup`: driver `s3`, `key`/`secret`/`region`/`bucket`/`endpoint` from `BACKUP_S3_*`, `root => env('BACKUP_S3_ROOT', 'family-manager-app/files')`, `use_path_style_endpoint => false`, `throw => true`)
- Create: `app/Services/FileBackup.php`, `app/Console/Commands/BackupFiles.php` (`files:backup`)
- Modify: `routes/console.php` (`Schedule::command('files:backup')->dailyAt('02:30')->timezone('Europe/Rome')->withoutOverlapping()->onOneServer()`, only when `filled(config('filesystems.disks.backup.bucket'))`)
- Modify: `.env.example` (`BACKUP_S3_*` empty)
- Test: `tests/Unit/Services/FileBackupTest.php`, `tests/Feature/Console/BackupFilesTest.php`

**Interfaces:**
- Produces: `FileBackup::run(CarbonImmutable $today): array{uploaded: int, kept: int, moved: int, pruned: int}`.
- Algorithm (reads the local disk `local`, writes the disk `backup`): the manifest `manifest.json` on `backup` maps each path to `{size, mtime}`. For every file under `families/` and `feedback/` on `local`, upload it when it is missing from the manifest or its size or mtime differ. For every path in the manifest that is gone from `local`, copy it to `deleted/<Y-m-d>/<path>`, delete the original and remove it from the manifest. Remove the `deleted/<date>/` folders older than 30 days. Write the manifest last, so a run that stops halfway uploads again next time and never forgets a file.

- [ ] **Write the failing tests** with `Storage::fake('local')` and `Storage::fake('backup')`:
  - `uploads new files`: two files under `families/1/` and one under `feedback/1/` → all three on `backup`, `uploaded` 3.
  - `skips unchanged files`: second run → `uploaded` 0, `kept` 3.
  - `uploads a changed file again`: change content (size) → uploaded, content equal.
  - `keeps a deleted file for 30 days`: delete locally, run on day D → at `deleted/D/families/1/a.pdf`, not at the original path; run on D+30 → still there; run on D+31 → gone (`pruned` 1).
  - `ignores files outside families and feedback` (for example `backups/…`).
  - `a failing upload leaves the manifest unchanged`: a `backup` disk that throws on the second put → the exception reaches the command, and the next run uploads both.
  - `BackupFilesTest`: `files:backup` prints the four numbers and exits 0. Without a bucket configured it prints a warning and exits 0 without touching anything. The schedule has `files:backup` only when the bucket is set (`Schedule::events()`).
- [ ] Run, see them fail.
- [ ] Implement.
- [ ] Green; PHPStan on the new files.
- [ ] Commit `Nightly copy of the family files to the Space (files:backup)`.

### Task 6: The production image

**Files:**
- Create: `Dockerfile`, `.dockerignore`, `docker/production/` (PHP ini with the limits of `docker/8.5/php.ini` without `variables_order`/`pcov`, nginx override only if the image's default does not satisfy the routes below, the start script, the service definitions for `queue:work --tries=3 --sleep=3 --max-time=3600` and `schedule:work`)
- Delete: `nixpacks.toml`
- Test: `tests/Feature/ProductionImageTest.php` (static checks on the files)

**Requirements** (spec section 1):
- Build stage: the same `serversideup/php` tag plus Node 22. Run `composer install --no-dev --optimize-autoloader` (it runs `filament:upgrade`), `npm ci`, `npm run build` with `VITE_FORMKIT_PRO_KEY` as a build secret (`--mount=type=secret`), not as an `ARG` left in the image history.
- Final stage `serversideup/php:<8.5 if published and stable, else 8.4>-fpm-nginx`: extensions `gd exif redis intl bcmath pdo_mysql`, packages `poppler-utils libheif-examples` (plus the libde265 plugin if the base distribution has it as a separate package), the app with `vendor`, `public/build`, the Filament assets. No `node_modules`, `tests`, `.env`, `storage/app/*` content.
- nginx: body limit ≥ 100M; `/sw.js`, `/scan/share`, `/livewire/*` and the panel go to PHP; `public/js|css|fonts/filament` served as files.
- At start: `migrate --force`, `config:cache`, `route:cache`, `view:cache`, `event:cache` (the image's Laravel automations if they do this), then `reminders:run` once. Queue and scheduler start as services of the same container.
- Health check `/up`. Persistent path: `/var/www/html/storage/app/private` (adjust to the image's app folder).
- `TRUSTED_PROXIES` read from the environment as today.

- [ ] **Write the failing test** in `ProductionImageTest`: `Dockerfile` exists and names `serversideup/php` and `fpm-nginx`; it installs `poppler-utils`, `libheif-examples`, `gd`, `exif`, `redis`; it does not contain `VITE_FORMKIT_PRO_KEY=` as an `ARG` default or `ENV`; `nixpacks.toml` is gone; the production ini has `post_max_size = 100M`, `upload_max_filesize = 26M`, `max_file_uploads = 100` and no `pcov`.
- [ ] Run, see it fail; write the files; green.
- [ ] **Try it locally** (no Coolify): `docker build --secret id=formkit,env=VITE_FORMKIT_PRO_KEY -t fm-app:try .`, then run it on the network `family-manager-app_sail` with `DB_DATABASE=testing_coolify_prod`, Redis of Sail, `APP_ENV=production`, `REGISTRATION_OPEN=false`, port 8092, its own `SESSION_COOKIE`. Check each item and write the results in the commit message:
  - `/up` answers 200;
  - `/login` loads with its JS;
  - `/sw.js` answers with `Service-Worker-Allowed: /`;
  - an upload of a 25 MB PDF works, and a 30 MB one gets the app's JSON message (not nginx's 413);
  - the PDF and a HEIC get their previews;
  - a queued job runs (`QUEUE_CONNECTION=database`, a test mail with `MAIL_MAILER=log`);
  - `schedule:list` from inside the container shows `reminders:run`, `loans:refresh`, `model:prune`, `files:backup`;
  - `php -m` has `gd`, `exif` and `redis`; `php -i` shows the limits;
  - the panel at `/admin` loads with its CSS.
  The container is removed afterwards.
- [ ] Commit `Production image on serversideup/php (web, queue, scheduler)`.

### Task 7: Documentation in the repository and the move-day checklist

**Files:**
- Modify: `AGENTS.md`, section *Pubblicazione*, rewritten (after the other session's commit on `AGENTS.md`, merging `dev` first): build from the `Dockerfile`, hosts, MySQL/Redis shared with Directus and their names, the environment variables with what each one is for (no values), volume, backups (MySQL in Coolify, files with `files:backup`), Mailgun on `mg.fameja.app`, sign-up closed, the Coolify app uuid (filled in Task 8), how to redeploy. The two *Tappa 4* paragraphs become the move-day checklist of spec section 7, steps 1–9 in order, with the way back.
- Modify: `README.md` (the panel on its own host in production, `REGISTRATION_OPEN`, `files:backup` in the commands table), `.claude/learnings.md` (what was found in Tasks 4–6, one dated entry per fact).
- `mail-inbox:route` (`MailgunRoutes::sync`) only changes routes that already forward to this app's address. Creating the route for `mg.fameja.app` → `https://my.fameja.app/mail/mailgun` leaves Directus's route alone (it was checked in the code while writing this plan). Write it in the checklist: Directus's `MAILGUN_API_KEY` must still be removed first, otherwise `family-settings` rewrites its own route.
- Decisions to record (in `AGENTS.md` where they replace old text): the existing MySQL 9 instead of a separate MySQL 8; Redis for cache and sessions; serversideup instead of Nixpacks; Mailgun on `mg.fameja.app` with a paid plan during the move.

- [ ] Write; check that every environment variable used by the config in Tasks 1–6 is listed.
- [ ] Before the commit: full Pest (`--parallel --processes=6`) and full Vitest together, PHPStan, Rector `--dry-run`, Pint, `npm run types`, `npm run lint`, `npm run build`.
- [ ] Commit `Docs: production on Coolify and the move-day checklist`.
- [ ] Ask the owner to merge `coolify-production` into `dev` and then into `main` (or to choose which branch Coolify builds from). `superpowers:finishing-a-development-branch`.

### Task 8: Coolify, DNS and services (agent with the write token, owner's go per block)

Not TDD: each step ends with a check. Before each block the agent says what it will do and waits for the go. Tokens through header files only. Write the uuids and names in `AGENTS.md` (commit at the end of the task).

**Owner, before or in parallel** (the agent sends the list):
- DNS: `A my.fameja.app` and `A admin.fameja.app` → `164.90.206.43`.
- Mailgun: paid plan, domain `mg.fameja.app` (EU), DNS records Mailgun gives (SPF, DKIM, MX, tracking CNAME), a sending key for that domain.
- Space: a key for the `family-manager` bucket for `files:backup`.
- The write token in `~/.config/family-manager/coolify-token-write` (already there; confirm it is valid).

**Block A — read only (go once):**
- [ ] `ssh family-manager-prod`: `free -m`, `docker stats --no-stream` (memory with Directus running), MySQL version (`SELECT VERSION()`), Redis `INFO keyspace`, `CONFIG GET databases`, `CONFIG GET appendonly`, `CONFIG GET save`. Coolify API: version, the MySQL and Redis resources of the project *Family Manager* and their backup settings. Report to the owner: free memory, the indexes free in Redis, persistence on or off, whether the MySQL backup covers all databases. **If free memory with Directus is under ~800 MB the owner decides** (wait for the switch-off or a bigger droplet) before going on.

**Block B — database and Redis (go):**
- [ ] Create database `family_manager_app` (`utf8mb4_unicode_ci`) and user `family_manager_app` with a generated password, all privileges on it only. Create `family_manager_app_ro` with `SELECT` on `family_manager.*`. Passwords go straight into the Coolify environment in Block C, never on screen.
- [ ] Add `family_manager_app` to the MySQL nightly backup in Coolify if the backup lists databases.
- [ ] Check: login as each user, `SHOW GRANTS`.

**Block C — the application (go):**
- [ ] Create the application in the project *Family Manager*, environment `production`, server `localhost`: public repository `leicaflorian/family-manager-app`, the branch agreed in Task 7, build pack Dockerfile, port 80 (or the image's), domains `https://my.fameja.app,https://admin.fameja.app`, health check `/up`, persistent storage on the private files path, the build secret `VITE_FORMKIT_PRO_KEY`.
- [ ] Environment: `APP_ENV=production`, `APP_DEBUG=false`, a new `APP_KEY`, `APP_URL=https://my.fameja.app`, `ADMIN_DOMAIN=admin.fameja.app`, `DB_*` (host = MySQL container on the Coolify network), `DIRECTUS_DB_*` with the read-only user (for the move day), `REDIS_HOST`/`REDIS_PASSWORD`/`REDIS_DB`/`REDIS_CACHE_DB`/`REDIS_PREFIX=fm_app_`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=database`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` (the Coolify proxy network, read in Block A), `REGISTRATION_OPEN=false`, `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAIL_FROM_ADDRESS=noreply@mg.fameja.app`, `DOCUMENT_READER_PROVIDER=gemini`, `DOCUMENT_READER_MODEL`, `GEMINI_API_KEY`, `BACKUP_S3_*`. `MAILGUN_SIGNING_KEY`, `MAILGUN_API_KEY` and `MAIL_INBOX_DOMAIN` are left out until the move day.
- [ ] Deploy; follow the build log through the API until done.
- [ ] Check: `https://my.fameja.app/up` 200 with a valid certificate, `https://admin.fameja.app/` the panel login, `https://my.fameja.app/register` 404, `https://admin.fameja.app/login` the Filament login. In the container: `php -m`, `php -i | grep -E 'post_max|upload_max'`, `schedule:list`, queue worker running.

**Block D — trial (go; the owner does the interactive parts):**
- [ ] The owner runs `php artisan owner:create` in the container terminal of Coolify and sets the authenticator at the first `/admin` sign-in.
- [ ] A test family made in the container (`tinker` with the factories, no seeder) with an admin account for the owner. Then, with Playwright on the public URL:
  - sign in;
  - upload a PDF and a HEIC: preview, AI reading done;
  - a password reset mail arrives from `noreply@mg.fameja.app`;
  - *Enter as…* from the panel and *Leave*;
  - next morning: `files:backup` has written to the Space and the MySQL backup has the new database.
- [ ] Remove the test family (panel *Delete family* or tinker) and its files.
- [ ] Write the app uuid, resource names and the trial results in `AGENTS.md`; commit `Docs: the app on Coolify`.

The move day itself (spec section 7) is not part of this plan's run. It follows the checklist written in Task 7, on a day the owner chooses, with the owner present.
