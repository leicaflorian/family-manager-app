# Fameja.app - app for families

![fameja-app-og-1200x630.png](public/fameja-app-og-1200x630.png)

Fameja.app, the new app of Family Manager, made for parents who are not technical. It will replace the Directus interface of the `family-manager-cms` repository, and it can be sold to other families: each family sees only its own data.

It is built with Laravel 13 from the Vue starter kit (Inertia, Vue 3, TypeScript, Tailwind 4, Fortify for sign-in, Wayfinder for the routes), and runs in Docker with Laravel Sail. The stage 1 prototype is gone: it can be recovered from the git history, commit `54a6702` (`git show 54a6702:prototype/…`).

The code is in English and the app speaks English and Italian: it takes the browser's language, and when it is neither of the two it uses `APP_LOCALE` (`it`). The Italian translations are in `lang/it.json` and `lang/it/`; how to add a text is in `AGENTS.md`.

## Starting the app

You need Docker. The first time:

```bash
cp .env.example .env
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
```

The form fields come from FormKit Pro: its key goes in `VITE_FORMKIT_PRO_KEY` in `.env` before `npm run dev` or `npm run build`.

For the document previews the app uses `pdftoppm` (`poppler-utils`), `heif-convert` (`libheif-examples`) and PHP's `ext-gd` extension. The Sail image (`docker/8.5/`, Ubuntu 24.04) and the production `Dockerfile` (serversideup/php on Debian 13) install them, both with `libheif-plugin-libde265` to read HEIC files, so there is nothing to add. If a preview does not show up locally after an update, rebuild the image with `./vendor/bin/sail build --no-cache`.

Then, every time:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

The app is on http://localhost:8090, the emails it sends on Mailpit, http://localhost:8026 (the 8025 of the Mac is taken by another project: `FORWARD_MAILPIT_DASHBOARD_PORT`).

### The scheduler

Some work runs on Laravel's scheduler (`routes/console.php`): `loans:refresh`, every night at 00:05 Europe/Rome time. Locally it runs only while the scheduler is on:

```bash
./vendor/bin/sail artisan schedule:work   # keeps it running, every minute
./vendor/bin/sail artisan schedule:run    # once, now
./vendor/bin/sail artisan schedule:list   # what is scheduled, and when
```

Online the production container already runs `schedule:work` (and the queue worker) as S6 services, so there is no scheduled task to add in Coolify. Every scheduled command is `withoutOverlapping()->onOneServer()`, because a deploy briefly runs the old and the new container together.

## Registration

Sign-up is open by default. With `REGISTRATION_OPEN=false` (production) `/register` does not exist and families come in only by invitation or from the owner.

## Test accounts

The seeder creates the Bianchi family from the design board. The password is `password` for every account:

| Account | Who | Level |
| --- | --- | --- |
| `marco.bianchi@example.com` | Marco Bianchi | Everything |
| `laura.bianchi@example.com` | Laura Bianchi | Everything |

Giulia, the daughter, has no account.

The Bianchis also have the address for the bills, `bianchi.k7m2x9@` followed by `MAIL_INBOX_DOMAIN` (`bianchi.k7m2x9@bollette.familymanager.test` with the `.env.example` value; only on a local machine and in the tests: anywhere else the seeder gives them a random address, shown in *Address for the bills*), and the six mails of the *Posta ricevuta* board, the newest first:

| Arrived | From | Subject | Outcome |
| --- | --- | --- | --- |
| 25/10/2026 09:41 | Enel Energia | La tua bolletta | 1 file saved |
| 25/10/2026 09:12 | Fastweb | Fattura disponibile | 1 file saved |
| 24/10/2026 17:30 | Hera | La tua fattura è disponibile | Notice saved (no attachment) |
| 23/10/2026 11:05 | Comune di Bologna | TARI 2026 - avviso di pagamento | 1 file saved, the logo left out (too small) |
| 22/10/2026 08:03 | promo@offerte-energia.it | Nuove offerte luce e gas | Discarded |
| 14/10/2026 07:58 | Edison | Bolletta di settembre | 1 file saved, 2 deliveries |

Their files are in *To sort*; the PDFs are fake ones in `database/seeders/files/`.

## The owner's panel

The owner of the app (not a family) has a back office (Filament) at `/admin` locally and on its own host in production, `https://admin.fameja.app` (`ADMIN_DOMAIN`, a bare host with no scheme: the panel answers there and nowhere else, the app answers on `my.fameja.app` and not on the panel's host, and the two have separate sessions): families with their figures, *Enter as…* an account to look at it (only with the permission an admin gives in *Settings → Support*, 24 hours), suspend and delete a family, AI readings, incoming mail and the installation settings. The panel never shows the content of a family.

The owner is not an account of a family and is not in the seeder: it lives in the `owners` table, uses the `owner` guard and must set up an authenticator app (two-factor authentication) at the first login. Create it with:

```bash
./vendor/bin/sail artisan owner:create
```

Then open `http://localhost:8090/admin` and sign in: in the local environment (`APP_ENV=local`) the panel does not ask for the authenticator app, which is required everywhere else (scan the QR code at the first sign-in and enter the code). Online, run `php artisan owner:create` once from the terminal of the container (the migrations run by themselves at every start).

## Incoming mail

Mailgun calls `POST mail/mailgun` (see `AGENTS.md`). Without Mailgun, on a local machine only:

```bash
# Delivers a mail (with a PDF) to the Bianchis: the PDF goes to To sort, the mail to Posta ricevuta
./vendor/bin/sail artisan mail-inbox:try bianchi.k7m2x9@bollette.familymanager.test --subject="Bolletta gas" --file=storage/app/prova.pdf

# The log of the calls for the owner (no text, no payload): --family=<id> --outcome=saved --since=2026-10-25
./vendor/bin/sail artisan mail-inbox:calls
```

`mail-inbox:try` hands a call signed like Mailgun's straight to the action in the same process, with the signing key of `.env` or, if there is none, a key made for that call. Outside local it refuses.

## Commands

The full list of commands, with the code rules, is in `AGENTS.md`. The most used:

| Command | What it does |
| --- | --- |
| `./vendor/bin/sail test` | PHP tests |
| `./vendor/bin/sail npm run test` | Frontend tests |
| `./vendor/bin/sail npm run build` | Frontend build |
| `./vendor/bin/sail artisan migrate:fresh --seed` | Rebuilds the database with the Bianchi family |
| `./vendor/bin/sail artisan directus:copy --family="Leica"` | Copies a family from Directus |
| `./vendor/bin/sail artisan owner:create` | Creates the owner account of the panel (`/admin`, or `ADMIN_DOMAIN`) |
| `./vendor/bin/sail artisan files:backup` | Copies the family files to the Space (the `backup` disk, `BACKUP_S3_*`) and keeps the deleted ones 30 days; it stops if the local disk looks empty or much shorter, `--force` goes on anyway |
| `./vendor/bin/sail artisan loans:refresh` | Works out again the instalments paid of the active loans of every family (the scheduler runs it every night) |
| `./vendor/bin/sail npm run tokens` | Rewrites `resources/css/tokens.css` from `resources/design/tokens.json` |

Colours, text styles, radii, shadows and sizes come from the "Family Manager" design system on Claude Design: `resources/design/tokens.json` is an exact copy of its `project/tokens.json` and is never edited by hand. To change the look, copy the new `tokens.json` there and run `npm run tokens`; components use the token classes (`bg-card`, `text-ink-soft`, `rounded-card`…), never a colour written by hand.

## Releases

The version is the one in the `VERSION` file. To release, run `./release` from the main checkout, on a clean `dev` equal to `origin/dev`, on the Mac (it needs `claude`, `jq`, Sail running and `ssh family-manager-prod`). It merges `dev` into `main`, asks Claude for the `CHANGELOG.md` entry and the simple notes of `resources/release/notes.json`, lets you confirm the version number, dumps the production database on the droplet if there are new migrations, then commits, tags and pushes `main` with the tag. GitHub Actions runs the checks on the tag, creates the GitHub Release and deploys on Coolify. If the checks fail nothing is released: fix and release a patch.

Before the first release, check that `ssh family-manager-prod` works (it logs in as root, the dump goes to `/root/backups/…`) and that `family_manager_app` exists on the droplet: on the first release every migration counts as new, so the dump runs. If `dev` added Composer packages, run `composer install` in Sail first, otherwise artisan fails at step 5 (the rollback is safe). `./release` refuses to run from a worktree.

## Cards

The cards of a family (home, utilities, vehicles, insurance policies, loans and the others) are described in `app/Cards/`: one class per type in `app/Cards/Types/`, listed in `CardTypes`, with its area, fields, conditions and dates. Rules, saving, rows and the form of the pages all come from that description, so a new field of a card is added there (and in the migration, if the column is new), with its case in `tests/Unit/Cards/CardTypesTest.php`.

Each family chooses the order of the areas and hides the ones it does not use, in *Settings → Areas* (only an admin changes it). The choice is `family_settings.areas` (`{"order": [...], "hidden": [...]}`, `null` for the default order), read and written only by `App\Services\FamilyAreas`: use it, not `Area::cases()`, wherever the areas are listed. A hidden area leaves the menus only; its cards, deadlines and reminders stay, and a new card in it shows it again (`FamilyAreas::reveal()`).

## Budget

*Budget* has three tabs: *Movements* (the expenses and incomes of a month), *Planned and spent* (the due dates of the month against what was spent, by category, and the last six months) and *Planned expenses* (the expenses and incomes that repeat). The due dates are not stored: `App\Services\Occurrences` works them out from each planned expense or income and its exceptions, and `App\Services\Deadlines` joins them to the payments. A payment says which due date it settles in `occurrence_date` on `expenses` and `incomes`. *I paid it* (`RecordPayment`) and *Change this due date* (`ChangeOccurrence`) start from a due date; the cards show their own due dates and payments (`CardDueDates`, `CardPaymentFigures`). A loan works out its last instalment, total, cost and instalments paid by itself (`LoanSchedule`, `LoanObserver`), when it is saved and every night with `loans:refresh`. Rules and decisions are in the *Bilancio* note of the vault (`02 - Documentazione tecnica/App Laravel/`).

A family copied from Directus before the `occurrence_date` migration has payments without their due date, so its due dates look late: copy it again with `directus:copy --replace`, which fills them.

## Home, Agenda and calendar

The *Home*, the *Agenda* and the family's calendar read the same list: `App\Services\Agenda` joins the dates of the cards (`App\Cards\DeadlineSources`, read by `App\Queries\CardDeadlines`), the due dates of the planned expenses and incomes, and merges a payment with the card date of the same day. The *Home* takes its numbers from `App\Services\HomeSummary`. The Agenda filters (period, person, areas) are saved on the account (`users.agenda_filters`), so they are the same on the phone and the computer.

The Agenda is one list of things to do: card deadlines, unpaid payments (up to 90 days late) and tasks, the reminders written by hand (`App\Services\AgendaTasks`, which also pages the *Done* view by 20). `App\Services\AgendaPage` builds its props for `AgendaController` and for the task form (`ReminderController`), which sits over the Agenda on the computer. There is no reminders page: `/reminders` redirects to the Agenda (`?status=done` to `?view=done`, `?person=` saves the person in the Agenda filters).

The calendar to subscribe to is one secret link per family, `GET /calendar/<secret>.ics`, read without signing in (`App\Services\CalendarFeed`). An admin turns it on, off or changes it in *Settings → Calendar*; a member sees and copies it. The secret is stored encrypted (`families.calendar_token`) and found by its sha256 (`calendar_token_hash`); the texts of the events are in the language saved when it was turned on (`calendar_locale`). Every wrong link answers the same 404. The address is built with `APP_URL`, which online must be the public address. A card date with its automatic reminder is not an event: the open reminder is.

## Copy from Directus

`directus:copy --family="<name>"` copies the family stored in Directus into the app: people, organizations, records, documents with their files and links, reminders, and the people's accounts (with their Directus email and a new password, chosen through *Forgot password*), and it gives each copied payment the due date it settles (`occurrence_date`). Directus is read only: the `directus` connection takes the `DIRECTUS_DB_*` variables from `.env`, and the Directus files folder is mounted read only from `DIRECTUS_UPLOADS_SOURCE` (by default `../family-manager-cms/uploads`). If the family is already there the command stops; `--replace` deletes it with its files and copies it again, but only if it was copied from Directus (a family with the same name that signed up in the app is never touched), and `--without-files` does not copy the content of the files. What is copied and what is not is in the *Copia da Directus* note of the vault (`02 - Documentazione tecnica/App Laravel/`).

## Documentation

Analysis, plan and decisions are in the project's Obsidian vault, ToDo items *Nuova app per le famiglie in Laravel*, *Nuova app - base dell'app*, *Nuova app - le pagine delle schede*, *Nuova app - bilancio e pagamenti* and *Nuova app - Home e Agenda*. The approved screens are on the Claude Design board *Family Manager - app per le famiglie*.
