# Versione e Novità nell'app - piano

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** una voce *Versione X.Y.Z* nel menu di *Il mio account* (barra laterale) e in *Altro* (telefono) che apre il pannello *Novità* con le note delle versioni.

**Architecture:** il server condivide `release` (stringa, da `config('app.version')`, che legge `VERSION`, `dev` se manca) e, solo quando il pannello lo chiede, `release_notes` (`Inertia::optional`, come `bell_notices`), letto da `resources/release/notes.json` da `App\Services\ReleaseNotes` nella lingua della richiesta. Il pannello è `FmReleaseNotesSheet` (su `FmBottomSheet`), aperto da `AppLayout` come *Segnala*.

**Tech Stack:** Laravel 13, Inertia, spatie/laravel-data, Vue 3 + TS, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-15-releases-design.md` (forma di `VERSION` e `notes.json`, fatti da un'altra sessione) e le tavole della tela *Novita*, *Novita-pc*, *Navigazione* (barra laterale e *Altro aperto*), *Altro*.

## Global Constraints

- `notes.json`: `[{ "version": "1.2.0", "date": "2026-11-15", "notes": { "it": [...], "en": [...] } }]`, dalla più nuova. I file e `config('app.version')` li scrive l'altra sessione: qui non si toccano `config/app.php`, `VERSION`, `resources/release/*`.
- Testi in inglese con `lang/it.json`; date scritte `15/11/2026` con `useFormat`.
- Icona `Sparkles` di Lucide.

## Review Focus

- `notes.json` mancante, illeggibile o con voci rotte: il pannello mostra *No news yet*, mai un 500.
- Lingua senza note (solo `it`): si usano quelle dell'altra lingua.
- `release` = `dev` (locale): la voce dice *Development version*, nessuna pillola.
- Telefono collegato con un codice: niente `release_notes`.
- Due aperture di fila: si ricarica solo `release_notes`, senza cambiare l'indirizzo.

---

### Task 1: server

**Files:** Create `app/Services/ReleaseNotes.php`, `app/Data/ReleaseNoteData.php`, `tests/Feature/Navigation/ReleaseNotesTest.php`; Modify `app/Http/Middleware/HandleInertiaRequests.php`.

- `ReleaseNoteData(string $version, string $date, list<string> $notes)` con `#[TypeScript]`.
- `ReleaseNotes::__construct(string $path)` (dal container con `resource_path('release/notes.json')`), `latest(string $locale): list<ReleaseNoteData>`: salta le voci senza `version`/`date` stringhe o senza note in nessuna lingua; lingua chiesta, poi `en`, poi `it`.
- `share()`: `'release' => config('app.version'), `dev` se vuoto` (stringa) e `'release_notes' => Inertia::optional(...)`; nel ramo del telefono collegato nessuno dei due.
- Test: visita normale ha `release` e non `release_notes`; ricarica parziale porta le note in italiano e in inglese; ripiego di lingua; file mancante e JSON rotto danno `[]`; telefono collegato senza `release_notes`.

- [ ] test che falliscono → codice → test verdi → `typescript:transform`.

### Task 2: pannello

**Files:** Create `resources/js/components/fm/FmReleaseNotesSheet.vue` + test, `resources/js/composables/useReleaseNotes.ts` + test; Modify `resources/js/types/global.d.ts`, `resources/design/boards.json` (`FmReleaseNotesSheet: pages`), `lang/it.json`.

- `useReleaseNotes()`: `release: ComputedRef<string>`, `notes: ComputedRef<App.Data.ReleaseNoteData[] | undefined>`, `loadNotes()` (`router.reload({ only: ['release_notes'], preserveUrl: true })`).
- Il pannello (`v-model:open`, titolo *What's new*): all'apertura chiama `loadNotes`; finché le note non ci sono mostra *Loading…*; senza note *No news yet*; ogni versione è una `section` con titolo *Version :version*, data, pillola *Your version* sulla versione che gira, elenco puntato.

- [ ] test che falliscono → codice → test verdi.

### Task 3: le voci del menu

**Files:** Modify `navigation.ts` (+ test), `FmSidebar.vue`, `FmBottomBar.vue` (+ test), `AppLayout.vue`.

- `releaseItem(release: string): PanelItem`: etichetta *Version :version* o *Development version*, riga *What's new in the app*, icona `Sparkles`.
- Barra laterale: voce dopo *Segnala*, prima della linea; emette `news`. *Altro*: riga dopo *Segnala*, chiude *Altro* ed emette `news`. `AppLayout` apre `FmReleaseNotesSheet`.

- [ ] test che falliscono → codice → test verdi → controlli dei file toccati → commit.
