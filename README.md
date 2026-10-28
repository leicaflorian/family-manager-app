# Family Manager - app for families

The new Family Manager app, made for parents who are not technical. It will replace the Directus interface of the `family-manager-cms` repository, and it can be sold to other families: each family sees only its own data.

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

For the document previews the app uses `pdftoppm` (`poppler-utils`), `heif-convert` (`libheif-examples`) and PHP's `ext-gd` extension. The Sail image (`docker/8.5/`, Ubuntu 24.04, which adds `libheif-plugin-libde265` to read HEIC files) and `nixpacks.toml` (Ubuntu 22.04, where that decoder is already in `libheif1`) install them, so there is nothing to add. If a preview does not show up locally after an update, rebuild the image with `./vendor/bin/sail build --no-cache`.

Then, every time:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

The app is on http://localhost:8090, the emails it sends on Mailpit, http://localhost:8025.

## Test accounts

The seeder creates the Bianchi family from the design board. The password is `password` for every account:

| Account | Who | Level |
| --- | --- | --- |
| `marco.bianchi@example.com` | Marco Bianchi | Everything |
| `laura.bianchi@example.com` | Laura Bianchi | Everything |

Giulia, the daughter, has no account.

## Commands

The full list of commands, with the code rules, is in `AGENTS.md`. The most used:

| Command | What it does |
| --- | --- |
| `./vendor/bin/sail test` | PHP tests |
| `./vendor/bin/sail npm run test` | Frontend tests |
| `./vendor/bin/sail npm run build` | Frontend build |
| `./vendor/bin/sail artisan migrate:fresh --seed` | Rebuilds the database with the Bianchi family |
| `./vendor/bin/sail artisan directus:copy --family="Leica"` | Copies a family from Directus |

## Copy from Directus

`directus:copy --family="<name>"` copies the family stored in Directus into the app: people, organizations, records, documents with their files and links, reminders, and the people's accounts (with their Directus email and a new password, chosen through *Forgot password*). Directus is read only: the `directus` connection takes the `DIRECTUS_DB_*` variables from `.env`, and the Directus files folder is mounted read only from `DIRECTUS_UPLOADS_SOURCE` (by default `../family-manager-cms/uploads`). If the family is already there the command stops; `--replace` deletes it with its files and copies it again, but only if it was copied from Directus (a family with the same name that signed up in the app is never touched), and `--without-files` does not copy the content of the files. What is copied and what is not is in the *Copia da Directus* note of the vault (`02 - Documentazione tecnica/App Laravel/`).

## Documentation

Analysis, plan and decisions are in the project's Obsidian vault, ToDo items *Nuova app per le famiglie in Laravel* and *Nuova app - base dell'app*. The approved screens are on the Claude Design board *Family Manager - app per le famiglie*.
