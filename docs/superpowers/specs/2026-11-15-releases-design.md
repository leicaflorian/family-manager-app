# Versioni, changelog e release

Data: 15/11/2026. Richiesta del proprietario: a ogni rilascio su `main` la versione sale, il changelog si compila, GitHub lancia tutti i test e solo dopo nasce e si pubblica la release.

## Perché

Il proprietario vuole sapere cosa è andato in produzione e quando (per controllare e per tornare indietro). Più avanti una versione semplificata delle note si mostrerà anche nell'app, alle persone della famiglia: le note semplificate si scrivono già adesso, la pagina nell'app è un lavoro a parte.

## Obiettivi

- Un comando che il proprietario lancia quando vuole rilasciare: legge i commit dall'ultima versione, fa scrivere a Claude (Sonnet, la CLI `claude` installata sul Mac, **non** l'API) il changelog tecnico e le note semplificate, suggerisce *minor* o *patch*, chiede conferma, poi fa commit, tag `vX.Y.Z` e push.
- Al push del tag GitHub lancia tutti i controlli; solo se passano crea la GitHub Release con le note e lancia il deploy su Coolify.
- La CI dei push e delle pull request torna a funzionare (oggi `tests.yml` usa PHP 8.3, non ha MySQL né la chiave di FormKit Pro e non lancia Vitest).

## Non obiettivi

- La pagina delle novità nell'app (dopo; i dati sono pronti).
- Conventional commits: i messaggi restano "Area: cosa".
- Rilasci automatici a ogni push: il rilascio parte solo dal comando.

## Anti-obiettivi

- Una release pubblicata o un deploy partito con un controllo rosso.
- Una chiamata all'API di Anthropic o una chiave API in più.
- Un comando che rompe `main` a metà: se il proprietario annulla o Claude risponde male, niente commit, niente tag, `main` e `dev` come prima.
- Un deploy con migrazioni partito senza la copia del database.

## Vincoli

- Artisan gira solo in Sail (sul Mac PHP 8.3); nel container `git` non ha identità né credenziali, e `claude` c'è solo sul Mac. Per questo il punto di partenza è uno script sul Mac.
- TDD, PHPStan 9, Rector, Pint, regole di `AGENTS.md`.
- Il proprietario rilascia da `dev`, albero pulito, `dev` e `main` allineati con `origin`.
- L'accesso al droplet è `ssh family-manager-prod` (nel `~/.ssh/config` del proprietario).

## Strade considerate

1. **Script `./release` sul Mac + `php artisan app:release` in Sail** (scelta del proprietario, con la CLI `claude` al posto dell'API). Lo script chiama Claude e fa git; il comando Artisan valida la risposta, chiede conferma e scrive i file, ed è provato con Pest.
2. Solo uno script sul Mac che guida `claude -p`: niente comando Artisan, niente test Pest.
3. Tutto nel comando Artisan, push compreso: servirebbero un token di GitHub nel `.env` e l'API di Anthropic (Claude non è nel container).

## Come funziona

### Il flusso (`./release`, bash, alla radice)

Decisioni del proprietario (15/11/2026): lo script parte da `dev` e fa lui l'unione in `main` e il ritorno in `dev` (1a); se ci sono migrazioni nuove fa lui la copia del database sul droplet prima del push (2a).

1. Controlla: branch `dev`, nessuna modifica, `git fetch`, `dev` uguale a `origin/dev` e `main` uguale a `origin/main`; Sail acceso; `claude` nel `PATH`.
2. Passa su `main` e unisce `dev` (`git merge --no-ff dev`, messaggio `Merge branch 'dev' for release`). Se l'unione ha conflitti si ferma con `git merge --abort` e torna su `dev`.
3. Raccoglie il contesto da `main`: ultimo tag `v*` (se non c'è, è la prima release), commit dal tag a `HEAD` raggiungibili da `dev` (`git log --no-merges <tag>..HEAD`, hash corto, titolo e corpo), `git diff --stat <tag>..HEAD` e l'elenco delle migrazioni nuove (`git diff --name-only --diff-filter=A <tag>..HEAD -- database/migrations`).
4. Chiama `claude -p --model sonnet --output-format json --json-schema <schema>` (lo schema sta in `resources/release/response.schema.json`, così la CLI garantisce la forma) con il prompt versionato `resources/release/prompt.md` più il contesto. Claude può leggere il repository solo con strumenti in lettura (`--allowedTools` ristretto a `Read`, `Grep`, `Glob`, `Bash(git log:*)`, `Bash(git show:*)`, `Bash(git diff:*)`), così guarda un diff quando un titolo non basta. Deve rispondere con il JSON:
   ```json
   {
     "bump": "minor" | "patch",
     "reason": "una frase sul perché del bump",
     "changelog": "markdown in italiano, sezioni per area",
     "notes": { "it": ["frase semplice", "…"], "en": ["simple sentence", "…"] }
   }
   ```
   La risposta si salva in un file dentro il repository ignorato da git (`storage/app/release/response.json`), che il container vede.
5. Lancia `./vendor/bin/sail artisan app:release storage/app/release/response.json` (interattivo).
6. Se il comando esce con 0: `git add VERSION CHANGELOG.md resources/release/notes.json`, commit `Release vX.Y.Z` e tag annotato `vX.Y.Z` con il changelog della versione come messaggio.
7. Se al passo 3 c'erano migrazioni nuove: copia del database di produzione **prima del push** (il push porta al deploy e il deploy alle migrazioni): `ssh family-manager-prod`, `docker exec` nel contenitore di MySQL (uuid `xwdxjjleppacin2ses4d93xe`, la password di root si legge dall'ambiente del contenitore, mai scritta né stampata) con `mysqldump --single-transaction --no-tablespaces family_manager_app` in `/root/backups/family-manager-app/before-vX.Y.Z-<aaaa-mm-gg-hhmm>.sql.gz` sul droplet. Lo script controlla che il file esista e non sia vuoto; se la copia non riesce si ferma prima del push e lo dice.
8. `git push origin main --follow-tags`, poi torna su `dev`, unisce `main` (avanti veloce: `dev` riceve il commit di rilascio) e `git push origin dev`.
9. Se il comando Artisan esce con 1 (annullato o risposta non valida) o un passo prima del push fallisce: `main` torna a `origin/main` (`git reset --hard origin/main`, che toglie l'unione appena fatta e niente altro, perché al passo 1 `main` era uguale a `origin/main`), il tag locale, se c'è, si toglie, e si torna su `dev`. I file scritti nel working tree si buttano con il reset.

### Il comando `app:release`

- Legge e valida il JSON (forma esatta, `bump` solo `minor` o `patch`, almeno una nota per lingua, changelog non vuoto). Una risposta non valida esce con 1 e un messaggio chiaro.
- Versione attuale da `VERSION` (se non c'è il file: prima release, chiede la versione con `1.0.0` come proposta).
- Mostra changelog, note e versione suggerita con il motivo; il proprietario sceglie tra la suggerita, l'altra (patch/minor), *major* o annulla. Annullare esce con 1 senza scrivere niente.
- Scrive, solo dopo la conferma:
  - `VERSION`: `X.Y.Z` e a capo;
  - `CHANGELOG.md`: in cima la sezione `## [X.Y.Z] - aaaa-mm-gg` con il changelog (il file si crea con l'intestazione se manca);
  - `resources/release/notes.json`: in cima all'elenco `{ "version", "date", "notes": { "it": [...], "en": [...] } }`.
- Il lavoro sta in un service (`App\Services\Release\…`) con le parti pure (validazione, calcolo della versione, scrittura) provate con Pest; il comando è sottile.

### La versione nell'app

`config('app.release')` legge `VERSION` (`dev` se manca). `config('app.version')` resta il commit (`SOURCE_COMMIT`), perché lo usano le segnalazioni per confrontare pagina e server. La release compare nel pannello del proprietario accanto alla versione (dove già si mostra) e nei dettagli delle segnalazioni.

### GitHub Actions

- `ci.yml` (sostituisce `tests.yml`), *reusable* (`workflow_call`) e su push di `dev`/`main` e pull request: PHP 8.5 con le estensioni del Dockerfile, servizio MySQL 9.7.2 (con il database `testing_directus` per il Directus finto), Node 22, `composer install`, `npm ci`, build con `VITE_FORMKIT_PRO_KEY` (segreto del repository), poi Pint `--test`, PHPStan, Rector `--dry-run`, Pest, Vitest, tipi, lint.
- `release.yml`, su push di tag `v*`: chiama `ci.yml`; se passa, crea la GitHub Release del tag con la sezione del changelog di quella versione; poi controlla che `main` su GitHub sia ancora il commit del tag e lancia il deploy di Coolify (`GET /api/v1/deploy?uuid=…` con `COOLIFY_TOKEN` e `COOLIFY_APP_UUID`, segreti del repository). Se `main` è andato avanti, la release c'è ma il deploy no, e il job lo dice.
- In Coolify l'applicazione costruisce da `main` con il deploy automatico a ogni push **spento**.
- Se i controlli falliscono il tag resta senza release: si corregge e si rilascia una patch.

## Lasciato a chi costruisce

- Il testo del prompt (stile delle note: al tu, semplici, senza parole tecniche, come le regole dei testi dell'app).
- Come lo script passa il contesto a Claude (argomento o stdin) e legge `result` dall'uscita JSON della CLI.
- L'ordine dei job in `ci.yml` e la cache di Composer e npm.

## Scelte mie (da controllare)

- `release.yml` controlla prima di tutto che il commit del tag sia in `origin/main` (antenato); un tag spinto da un altro branch non crea release né deploy.
- `ci.yml` riceve i segreti con `secrets: inherit`. Sulle pull request senza segreti (fork, Dependabot) il passo che costruisce le pagine si salta e i test che vogliono la build restano fuori; tutto il resto gira.
- `main` non è protetto (controllato il 15/11/2026), quindi il push diretto del commit di rilascio passa.
- Se il push fallisce dopo commit e tag, lo script non annulla niente: lo dice e stampa i comandi da rilanciare (push di `main` con i tag, ritorno in `dev`).
- La copia del database resta sul droplet, in `/root/backups/family-manager-app/`, e non si scarica sul Mac; le copie vecchie non si tolgono da sole.
- Una versione con i controlli rossi resta nel changelog e nelle note così com'è (il tag c'è); la patch successiva ha la sua voce.
- A Claude si passano titoli e corpi dei commit; i diff li legge lui quando servono. Nessun taglio del contesto, salvo per la prima release, dove passano solo i titoli.
- La prop condivisa con la versione e la lettura di `notes.json` per l'app le fa la sessione che costruisce la voce del menu e il pannello delle novità; questo lavoro scrive solo i file.

- Changelog in italiano (lo legge il proprietario, come `AGENTS.md`); le note per l'app in tutte e due le lingue.
- `config('app.release')` separato da `app.version`, per non rompere il confronto delle segnalazioni.
- Il deploy parte da `main` e non dal tag, con il controllo che coincidano.
- Prima release: la versione la sceglie il proprietario (proposta 1.0.0).
