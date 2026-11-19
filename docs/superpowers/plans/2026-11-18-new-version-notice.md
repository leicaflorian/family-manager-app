# Avviso di una versione nuova - piano

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** una pagina aperta si accorge che il server ha una versione più nuova e mostra un toast «There's a new version» con il tasto «Reload».

**Architecture:** una rotta pubblica `GET /version` (fuori dal gruppo `web`) dice `config('app.version')`; il composable `useNewVersion`, avviato da `AppLayout`, la chiede quando la finestra torna visibile e ogni 10 minuti, la confronta con `__APP_BUILD__` grazie a `isNewerVersion()` e mostra il toast di `vue-sonner` (disegnato da `FmToaster`).

**Tech Stack:** Laravel 12 + Pest, Vue 3 + TypeScript, Vitest (`vite-plus/test`), `vue-sonner`, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-11-18-new-version-notice-design.md`

## Global Constraints

- `declare(strict_types=1);`, classi `final`, PHPDoc su ogni classe e metodo, PHPStan 9 senza ignore; TSDoc su ogni funzione e composable; niente `any`.
- Testi in inglese nel codice, italiano in `lang/it.json` (righe aggiunte a mano prima della `}` finale, mai riscrivendo il file con un parser JSON).
- La rotta resta fuori dal gruppo `web`: niente sessione, niente `Set-Cookie`, `Cache-Control: no-store`.
- `dev` o una versione non `major.minor.patch` non avvisano mai; una pagina più nuova del server non avvisa.
- Prima del commit solo i controlli dei file toccati (Pest e Vitest di quei file, PHPStan, Rector, Pint, tipi, lint).

## Review Focus

- Rolling update: il server risponde una volta la versione nuova e una volta la vecchia → il toast resta uno solo, mai doppio (Task 2: `shows the notice once`).
- Rete assente o risposta non valida (500, HTML, JSON senza `version`) → nessun toast e nessun errore non gestito (Task 2: `ignores a failed check`).
- Finestra nascosta per ore (telefono in tasca) → nessuna richiesta mentre è nascosta, una appena torna visibile (Task 2: `checks when the page comes back`).
- Telefono collegato (*Documenti dal telefono*) → la rotta risponde anche a lui, perché sta fuori da `LimitLinkedDevice` (Task 1: `answers a linked phone`).
- Componente smontato (uscita, cambio di layout) → timer e ascoltatore tolti (Task 2: `stops when unmounted`).

---

### Task 0: la tavola (chi coordina, con un fork)

**Files:** tavola `project/Pannelli.dc.html` della tela (`https://claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr`).

- [ ] Scaricare la tavola (`Artifact read`, `paths: ["project/Pannelli.dc.html"]`), aggiungere nella scheda di `FmToaster` il caso «New version»: icona informazione blu, titolo «C'è una versione nuova», tasto «Ricarica», su telefono (sopra la barra in basso) e su computer (in basso a destra); colori con `var(--…)`.
- [ ] Pubblicare la tela e salvare la copia in `.superpowers/sdd/new-version-notice/boards/` per i task seguenti.

### Task 1: la rotta della versione

**Files:**
- Create: `app/Http/Controllers/VersionController.php`
- Modify: `routes/web.php` (accanto a `service-worker`)
- Test: `tests/Feature/VersionTest.php`

**Interfaces:**
- Produces: `GET /version`, nome `version`, risposta `{"version": string}`; la funzione Wayfinder `version()` in `resources/js/routes` (generata dalla build/plugin).

- [ ] **Step 1: test che falliscono** (`tests/Feature/VersionTest.php`)
  - `it answers the version of the server`: con `config(['app.version' => '2.3.0'])`, `getJson('/version')` → 200, `assertExactJson(['version' => '2.3.0'])`, header `Cache-Control` contiene `no-store`.
  - `it opens no session`: risposta senza `Set-Cookie` (`assertCookieMissing` per `config('session.cookie')` e `XSRF-TOKEN`).
  - `it answers a linked phone`: con un telefono collegato attivo (come in `LinkedDeviceLimitTest`, cookie `fm_device`) → 200 e la versione.
- [ ] **Step 2:** `docker exec -u sail -e DB_DATABASE=testing_version family-manager-app-laravel.test-1 php artisan test tests/Feature/VersionTest.php` → FAIL (404).
- [ ] **Step 3:** `final class VersionController` invocabile, `__invoke(): JsonResponse` con `config()->string('app.version')` e `Cache-Control: no-store`. Rotta `Route::get('version', VersionController::class)->middleware([SeparateHosts::OUTSIDE_WEB, 'throttle:120,1'])->withoutMiddleware('web')->name('version')`, con il commento del perché (chiesta spesso da tutte le pagine aperte: niente sessione).
- [ ] **Step 4:** stesso comando → PASS; poi PHPStan, Rector `--dry-run`, Pint sui file toccati.
- [ ] **Step 5:** commit `Version route for the open pages`.

### Task 2: il controllo nelle pagine

**Files:**
- Create: `resources/js/lib/versions.ts`, `resources/js/lib/versions.test.ts`
- Create: `resources/js/composables/useNewVersion.ts`, `resources/js/composables/useNewVersion.test.ts`
- Modify: `resources/js/layouts/AppLayout.vue` (una riga: `useNewVersion()`), `lang/it.json`

**Interfaces:**
- Consumes: `GET /version` del Task 1 (via `version().url` di Wayfinder).
- Produces:
  - `isNewerVersion(server: string, page: string): boolean` in `lib/versions.ts`.
  - `useNewVersion(options?: { current?: string; check?: () => Promise<string | null>; every?: number }): void`; predefiniti `current = __APP_BUILD__`, `check` = `fetch` di `/version` con `cache: 'no-store'` che dà `null` su errore o forma sbagliata, `every = 600_000`.

- [ ] **Step 1: test di `isNewerVersion`** (`versions.test.ts`): `('2.3.0','2.2.2')` → true; `('2.10.0','2.9.9')` → true; `('2.2.2','2.2.2')` → false; `('2.2.1','2.2.2')` → false; `('dev','2.2.2')`, `('2.3.0','dev')`, `('','2.2.2')`, `('2.3','2.2.2')` → false.
- [ ] **Step 2: test di `useNewVersion`** (`useNewVersion.test.ts`, componente di prova montato con `@testing-library/vue`, fake timers, `toast` di `vue-sonner` letto dal `Toaster` montato insieme, come la persona lo vede):
  - `shows the notice when the server is newer`: `current: '2.2.2'`, `check` → `'2.3.0'`; dopo `every` ms compare «There's a new version» con il bottone «Reload»; il clic chiama `window.location.reload` (spiato).
  - `checks when the page comes back`: con `document.visibilityState` `hidden` passano 2 × `every` senza chiamate a `check`; con `visible` + evento `visibilitychange` una chiamata subito.
  - `shows the notice once`: `check` dà `'2.3.0'`, poi `'2.2.2'`, poi `'2.3.0'` → un solo toast, e dopo il primo avviso `check` non viene più chiamato.
  - `ignores a failed check`: `check` → `null` (e una volta rifiuta) → nessun toast, nessun errore non gestito.
  - `stays quiet in development`: `current: 'dev'` → `check` mai chiamato.
  - `stops when unmounted`: dopo l'unmount, avanzare i timer e mandare `visibilitychange` non chiama `check`.
- [ ] **Step 3:** `npx vp test run resources/js/lib/versions.test.ts resources/js/composables/useNewVersion.test.ts` (nel container) → FAIL.
- [ ] **Step 4:** scrivere `isNewerVersion` (confronto numerico dei tre pezzi, regex `^\d+\.\d+\.\d+$` per tutti e due) e `useNewVersion` (ascoltatore e `setInterval` in `onMounted`, tolti in `onBeforeUnmount`; l'intervallo chiede solo se `document.visibilityState === 'visible'`). Toast: `toast.info(trans("There's a new version"), { duration: Infinity, action: { label: trans('Reload'), onClick: () => window.location.reload() } })`.
- [ ] **Step 5:** `lang/it.json`: `"There's a new version": "C'è una versione nuova"`, `"Reload": "Ricarica"` (se `"Reload"` c'è già con un altro significato, usare una chiave propria).
- [ ] **Step 6:** `useNewVersion()` in `AppLayout.vue`, con un `check` predefinito che in `AppLayout.test.ts` non fa partire richieste vere (se il test del layout lo vede, fingere il composable lì).
- [ ] **Step 7:** stessi test → PASS, poi `AppLayout.test.ts`, `TranslationsTest` (Pest), `npm run types`, `npm run lint`, `npx vp check --fix` sui file toccati.
- [ ] **Step 8:** commit `Notice of a new version in the open pages`.

### Task 3: prova nel browser e chiusura

- [ ] Con Playwright (Chromium headless) su una build: `page.route('**/version', …)` risponde `{"version":"99.0.0"}`, si aspettano 10 minuti finti (`page.clock`) o si manda `visibilitychange`, si guarda il toast su telefono (390×844) e computer, chiaro e scuro, e lo si confronta con la tavola del Task 0.
- [ ] Una riga in `.claude/learnings.md` se si scopre qualcosa; aggiornare la documentazione nel vault con `project-docs`.
