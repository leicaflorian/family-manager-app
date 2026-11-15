# Production on Coolify: the move of the owner's family

## Why

The owner's family today runs on Directus (`family.leicaflorianrobert.dev`, Coolify on the DigitalOcean droplet
`family-manager-prod`). The new Laravel app replaces it: the owner wants to prepare the full move of the family to
the new app, after which the Directus version is switched off. This is no longer the "test online with the Bianchi"
of stage 2: production holds the real family data.

Success: the family uses the app on `https://my.fameja.app` every day, the owner manages it from
`https://admin.fameja.app`, emails go out and bills arrive by mail, documents and database are backed up every
night, and Directus can be switched off without losing anything.

## Goals

1. **Build and run** the app on Coolify from a `Dockerfile` in the repository, based on `serversideup/php`
   (fpm-nginx): web, queue worker and scheduler in one container.
2. **Domains**: the app on `my.fameja.app`, the owner panel (Filament) on `admin.fameja.app`. The public site on
   `fameja.app` is not part of this work.
3. **Enter as…** keeps working across the two domains with a one-time handoff link (below), each domain keeping
   its own cookies.
4. **Shared services**: the existing MySQL 9 of Directus (new database and user) and the existing Redis 8 of
   Directus (cache and sessions).
5. **Mail**: one Mailgun domain, `mg.fameja.app`, for outgoing mail through the Mailgun API
   (`noreply@mg.fameja.app`) and for incoming mail (the bills addresses on `mg.fameja.app`). The root `fameja.app`
   keeps no MX of Mailgun, free for the public site and a personal mailbox.
6. **Sign-up closed** in production: a family is not created from the public pages; family members still join
   with an invitation.
7. **AI reading on from day one** with Gemini, like Directus today.
8. **Backups**: the family files (local volume) copied every night to the DigitalOcean Space; the new database
   inside the existing MySQL nightly backups.
9. **The move day** (stage 4) written as an ordered checklist, with the way back.

## Non-goals

- The public site `fameja.app`.
- Keeping the Bianchi seeder online: production has only real families (no `db:seed`).
- Creating a new family from the owner panel (sign-up is closed; a way to create families comes later).
- Moving the queue to Redis: it stays on the database (`QUEUE_CONNECTION=database`).
- Storing the files directly on the Space (S3 disk): rejected, see Approaches.
- Railpack, Laravel Cloud, Forge.
- Retiring the Directus extensions and repository work in `family-manager-cms` beyond what the move day needs.

## Anti-goals (a failure even if it works)

- Any family data lost or exposed: a document only on the droplet with no copy elsewhere for more than a day, a
  session cookie readable by the public site, the Directus database writable by the app.
- Reminders or morning emails sent twice (Directus and the app both running `reminders-automation`).
- The droplet (2 vCPU / 4 GB, shared with Directus until the switch-off) running out of memory.
- Configuration that lives only in the Coolify panel and cannot be rebuilt from the repository and `AGENTS.md`.

## Constraints

- Droplet: 2 vCPU / 4 GB / 80 GB, Ubuntu 24.04, Coolify (4.4.6 on 17/10/2026). Directus, its MySQL 9 and Redis 8.2
  run there until the switch-off.
- DigitalOcean blocks SMTP ports 25, 465, 587: mail goes through HTTPS APIs.
- The app needs at runtime: PHP ≥ 8.4 (Sail uses 8.5) with `gd`, `exif`, `redis`, `intl`, `pdo_mysql`, `bcmath`;
  `poppler-utils` (`pdftoppm`) and `libheif-examples` (`heif-convert`, with the libde265 decoder);
  body limits of `docker/8.5/php.ini` (post 100M, upload 26M, 100 files) and the same body limit in nginx.
- The build needs Node 22, PHP and `vendor` (Wayfinder runs in the Vite plugin), `VITE_FORMKIT_PRO_KEY` (ends up in
  the JS), and `npm run build` (app plus service worker, `vite.sw.config.ts`). Filament assets come from
  `filament:upgrade` (`post-autoload-dump`) and are not committed.
- nginx must leave `/sw.js`, `/scan/share`, `/livewire/*` and the panel to PHP and serve `public/js|css|fonts/filament`
  as static files (see `AGENTS.md`, *Pubblicazione*).
- Behind Coolify's proxy (Traefik): `TRUSTED_PROXIES` set to the proxy network (never `*`),
  `SESSION_SECURE_COOKIE=true`.
- Mailgun free plan: one custom domain. For the move period the owner moves Mailgun to a paid plan (both
  `mg.leicaflorianrobert.dev` and `mg.fameja.app` live together), and goes back to the free plan after removing the
  old domain at the end (step 9 of the move day).
- Never `migrate`/`db:seed` against a database that holds real data without a backup taken right before.
- Other Claude sessions have open changes on `compose.yaml` and `AGENTS.md`: those files are touched only after
  their commit.

## Approaches

**How the app is built and run** (owner picked 3):

1. *Nixpacks* (what `AGENTS.md` planned; also Coolify's Laravel guide, with supervisor in `nixpacks.toml`). A
   custom `nginx.template.conf`, supervisor programs, FPM config all written by hand in `nixpacks.toml`; no
   scheduler in the guide; Nixpacks is no longer developed and lags on PHP versions. Without supervisor, queue and
   scheduler become two more Coolify apps from the same repo: three builds, three copies in memory.
2. *Own Dockerfile from scratch* (PHP-FPM + nginx + supervisor, like Directus's Dockerfile on the same server):
   full control, many lines to write and keep (nginx, FPM, supervisor, permissions).
3. **`Dockerfile` on `serversideup/php` (fpm-nginx)**: the same model as Forge and Laravel Cloud (nginx + FPM for
   the web, a supervised queue worker, the scheduler every minute) in one container. nginx and FPM tuned with
   environment variables, non-root user, Laravel automations (migrations, caches) at start, extra services for
   queue and scheduler. Built and tried locally with `docker build`. Chosen: one container on a small droplet, the
   PHP version we want, everything in the repository.

Railpack (Coolify beta since 4.5, FrankenPHP, web only) and Coolify *Scheduled Tasks* (panel-only config, no
queue) were looked at and not used.

**Database** (owner): the existing MySQL 9 rather than a new MySQL 8, to save the memory of a second server
(~400–500 MB) and reuse the nightly backups already set up. Decided in place of "a separate MySQL 8
`family-manager-app-db`" of `AGENTS.md`. Sail moves to MySQL 9 so tests run on the production version.

**Redis** (owner): the existing Redis, from the start, for cache and sessions (not the queue). Own database index
and key prefix, so keys never mix with Directus's.

**Enter as… across two domains** (owner picked 2):

1. *Shared session on `.fameja.app`* (`SESSION_DOMAIN`): one line, but the session cookie reaches every subdomain,
   the public site included.
2. **One-time handoff link**: chosen. Each domain keeps its own host-only cookies.

**Family files** (owner picked 1):

1. **Local volume plus a nightly copy to the Space**: chosen. No code change in previews, AI reading, checksums;
   at most one day of uploads at risk.
2. *Files directly on the Space*: nothing at risk, but every place that works on a local file changes, and every
   file opening goes through the Space.

**Outgoing mail** (owner): the Mailgun API (HTTPS, port 443, immediate errors, Laravel `mailgun` transport) rather
than SMTP on port 2525. Incoming mail moves to the same new domain, so one Mailgun domain serves both; the bills
address changes and the Gmail filters are redone once on the move day.

**The Mailgun domain during the move** (owner picked a): a paid Mailgun plan for a month or two, so the new domain
is verified and outgoing mail tried before the move day and the old route to Directus stays as the way back.
Rejected: a second free Mailgun account (two accounts, two sets of keys), swapping the domain on the move day (no
test of sending before, no way back for incoming mail).

**Which domain** (owner): `mg.fameja.app`, not the root, so MX records never take over mail on `fameja.app`.

## Design

### 1. The image (`Dockerfile`, `.dockerignore`)

- Multi-stage. A build stage with PHP (same base) plus Node 22: `composer install --no-dev --optimize-autoloader`,
  `npm ci`, `npm run build` (needs `VITE_FORMKIT_PRO_KEY` as a build argument/secret, never baked into a layer of
  the final image other than the built JS). The final stage `serversideup/php:<8.5 or 8.4>-fpm-nginx` with the
  app, `vendor`, `public/build`, the Filament assets.
- System packages: `poppler-utils`, `libheif-examples` and the libde265 plugin if the base distribution needs it
  (check which Debian/Ubuntu the image uses); PHP extensions `gd`, `exif`, `redis`, `intl`, `bcmath` via the image's
  `install-php-extensions`.
- PHP limits from `docker/8.5/php.ini` values through the image's variables (`PHP_POST_MAX_SIZE`,
  `PHP_UPLOAD_MAX_FILE_SIZE`, …) or a production ini; nginx `client_max_body_size` at least the same (≥ 100M, so
  Mailgun's 41 MB and `scan.corners` get the app's JSON answers and never nginx's 413). The Sail-only lines
  (`variables_order`, `pcov`) do not go to production.
- Queue worker (`queue:work --tries=3`) and scheduler (`schedule:work`) as supervised services of the same
  container. `schedule:work` covers `reminders:run` 6:00, `loans:refresh` 00:05, `model:prune` and the file
  backup (below). Only one of `schedule:work` or Coolify's `schedule:run` task, never both.
- Start: `migrate --force`, `config:cache`, `route:cache`, `view:cache`, `event:cache`, `queue:restart` after each
  deploy (the worker keeps old code), then `reminders:run` (catches up a day the server was down; locked by
  `Cache::lock('reminders:run')`).
- Health check on `/up`.
- Volume: `/var/www/html/storage/app/private` (or wherever the image puts the app) persistent.
- Tried locally before going online: `docker build`, then the container against a `testing_*` database with the
  Sail network, uploads of a 25 MB file, a PDF and a HEIC preview, `/sw.js`, the panel, the queue processing a job.
- `nixpacks.toml` is removed; `AGENTS.md` *Pubblicazione* is rewritten for the new build.

### 2. Domains and the owner panel

- `APP_URL=https://my.fameja.app`. The Filament panel gets its domain from configuration (for example
  `ADMIN_DOMAIN=admin.fameja.app`): with it set, the panel answers only on that host (`->domain()`), at the root
  path; without it (local, tests) the panel stays at `/admin` on the app host as today.
- Cookies host-only (`SESSION_DOMAIN` empty): the app and the panel never share a session.
- The app's routes do not answer on the admin host and vice versa (a request to `admin.fameja.app/login` must not
  show the family sign-in). Livewire's routes must work on the admin host.
- `PageMeta`, `AddRobotsHeader`: the panel host is `noindex`; the app's `login` is indexable only on `my.`.

### 2b. Sign-up closed

- A configuration flag (for example `REGISTRATION_OPEN`, default open so local and tests keep today's behaviour,
  `false` in production) turns off Fortify's registration: `register` and `register.store` answer 404, the sign-in
  page has no *Sign up* link, `register` leaves `PageMeta::INDEXABLE` while closed.
- Invitations (`InvitationController`) keep working: a person invited by an admin creates their account from the
  link. Tests for both states of the flag.

### 3. Enter as… with a one-time link

- From the panel, *Enter as…* creates a link to the app host carrying the owner id, the user id, an expiry of
  60 seconds and a random one-time token; the link is signed (`URL::temporarySignedRoute`, which signs with
  `APP_KEY` and the app host). The token is stored in the cache (Redis) and consumed with `Cache::pull` on first
  use: a second opening, an expired or tampered link answers 403 and writes nothing.
- On the app host the route signs the owner in on the `owner` guard of the app's session and then starts the visit
  as today (`StartVisit`, the 24-hour support permission of the family, the visit log). The authenticator rule of
  `VisitController::start` stays (`requiresAuthenticator()` and an owner without a secret → 403).
- *Leave* (`visit.leave`) ends the visit, signs the owner out of the app's session and sends the browser to the
  panel on the admin host.
- Signing out of the panel no longer ends a visit open on the app host (they are different sessions);
  `EndVisitOnOwnerLogout` is kept for the single-host case (local) or removed if it no longer applies. A visit
  ends with *Leave*, the end of the app session, or the end of the support permission.
- Tests: the handoff works once; a second use, an expired link, a changed id, a link without the owner being able
  to visit (no permission, suspended family) are refused; the visit is logged once; *Leave* lands on the panel URL.

### 4. MySQL and Redis

- In the existing MySQL 9: database `family_manager_app`, user `family_manager_app` with all privileges on it only.
  For the copy from Directus, a second user with `SELECT` only on `family_manager` (the Directus database), used by
  the `directus` connection, dropped after the move.
- Redis: `REDIS_HOST` of the Directus Redis on Coolify's network, its password, `REDIS_DB`/`REDIS_CACHE_DB` set to
  indexes Directus does not use, `REDIS_PREFIX=fm_app_`. `CACHE_STORE=redis`, `SESSION_DRIVER=redis`. The Mailgun
  token cache and the locks (`reminders:run`, `onOneServer`) live there: check that the Redis keeps its data across a
  restart (persistence) and note in `AGENTS.md` what a Redis restart without it costs (everyone signed out, Mailgun
  tokens forgotten).
- Sail: MySQL 9 image and a Redis service; local `.env` with `CACHE_STORE=redis` and `SESSION_DRIVER=redis` so
  local runs like production. Tests keep their own drivers (`phpunit.xml`).

### 5. Mail

- Outgoing: `MAIL_MAILER=mailgun`, `symfony/mailgun-mailer` and `symfony/http-client`, EU endpoint, a sending key
  limited to the domain (`MAILGUN_SECRET`), separate from `MAILGUN_API_KEY` that `mail-inbox:route` uses for
  routes. `MAIL_FROM_ADDRESS=noreply@mg.fameja.app`. Emails leave in the sender's language as today.
- Incoming: `MAIL_INBOX_DOMAIN=mg.fameja.app`; `mail-inbox:addresses` gives the
  families their addresses on it, and `directus:copy` must not bring the old `mail_inbox_recipient` on
  `mg.leicaflorianrobert.dev` (it is regenerated). `MAILGUN_SIGNING_KEY` of the new domain.
- DNS of `mg.fameja.app` (SPF, DKIM, MX, tracking CNAME) set by the owner; Mailgun verifies the domain and a
  test email goes out before the move day.
- `mail-inbox:route` must create the route for `mg.fameja.app` without touching the one of
  `mg.leicaflorianrobert.dev` that serves Directus until the move (check how it matches routes today).

### 5b. AI reading

- On from day one: `DOCUMENT_READER_PROVIDER=gemini`, `DOCUMENT_READER_MODEL`, `GEMINI_API_KEY` (the key with a
  spending limit already used by Directus), `DOCUMENT_READER_MONTHLY_LIMIT` if not 1000.
- `directus:copy` does not queue readings (only uploads and incoming mail do, through `QueueReading`): the copy
  costs nothing. `files:checksums` once after the copy.

### 6. Nightly backup of the files

- A scheduled command (in the repository, with tests) copies the private disk's family files to the Space under
  their own prefix (for example `family-manager-app/files/`): new and changed files are uploaded (the checksum
  `files:checksums` already writes helps to skip unchanged ones); a file deleted in the app is moved in the
  backup under `deleted/<date>/` and removed after 30 days, so a wrong deletion can be recovered for a month.
  Runs every night through `schedule:work`; a failure is reported in the log.
- The Space is the one Directus already uses (`family-manager`), under a prefix of its own. It stays after
  Directus is switched off: it also holds the MySQL and Coolify backups.
- Space credentials only in the app's environment, with a key limited to the bucket if the Space allows it.
- The `feedback/` attachments are copied too.
- The new MySQL database is added to the MySQL nightly backup in Coolify (check whether the backup covers all
  databases or a list).

### 7. The move day (stage 4), ordered

Written as a checklist in `AGENTS.md`, replacing today's stage-4 notes:

1. Days before: DNS of `my.` and `admin.` to the droplet (low TTL first), the Mailgun domain verified, the app
   deployed and tried with an owner account and a test family that is deleted afterwards.
2. Backups: Directus database (`./fm prod dump`), app database.
3. Directus read-only for the family (no new documents), `reminders-automation` off.
4. Copy: Space files to a temporary folder reachable by the container, `directus:copy --family="Leica"` against
   the `family_manager` database with the read-only user, `files:checksums`, the owner account recreated (users
   without email in Directus, see `.claude/learnings.md`), `mail-inbox:addresses`.
5. Mail: on Directus remove `MAILGUN_API_KEY` and restart (otherwise `family-settings` rewrites the route), then
   `mail-inbox:route` to `https://my.fameja.app/mail/mailgun`; Gmail filters redone to the new bills address; a
   test mail with a PDF from Gmail and from Apple Mail.
6. Family members: the new address, reinstalling the icon on the phones, the calendar subscription redone, linked
   phones linked again.
7. First real checks: a Mailgun call (Gmail forwarding confirmation from `forwarding-noreply@google.com`, a new
   token per retry), the morning emails the next day, the file backup on the Space the next morning.
8. Way back, if the app fails in the first days: Mailgun route back to Directus, `reminders-automation` on,
   Directus writable again; what was entered in the app meanwhile is re-entered by hand.
9. After a few calm weeks: Directus and its database switched off, the read-only user dropped, Redis, MySQL and
   the Space remain for the app; the domain `mg.leicaflorianrobert.dev` removed from Mailgun and the plan back to
   free.

### 8. Memory

Before deploying, measure the droplet's free memory with Directus running; set FPM children and the worker count
for a small machine (for example `pm.max_children` 5–8, one worker). If it does not fit, the move waits for the
switch-off of Directus or a bigger droplet: the owner decides.

## Left to the builder

- The exact `serversideup/php` tag (8.5 if available and stable, else 8.4) and how the image declares extra
  services, automations and nginx overrides.
- Whether the default nginx config of the image already satisfies the routing constraints or needs an override.
- The names of configuration keys (`ADMIN_DOMAIN`, Redis indexes, backup prefix).
- How the backup command talks to the Space (Flysystem S3 driver or another way), as long as it is in the
  repository and tested without real calls.
- The order of tasks in the plan, keeping the move day last and the owner's go before any action on the droplet,
  DNS, Mailgun or Directus.
