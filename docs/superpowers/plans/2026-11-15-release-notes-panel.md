# Versione e Novità nelle impostazioni - piano

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** la versione dell'app nelle *Impostazioni*, nel gruppo *L'app*, con la riga *Novità* che apre la pagina delle note delle versioni.

**Architecture:** `SettingsController` passa `release` (da `config('app.version')`, che legge `VERSION`, `dev` se manca) e `releasedOn` (la data della versione in `resources/release/notes.json`, `null` se non c'è). La pagina *Novità* (`settings.news`, `GET settings/news`, `ReleaseNotesController`, componente `settings/News`) riceve `release` e `notes` da `App\Services\ReleaseNotes` nella lingua della richiesta, e sta in `FmSettingsPage` come le altre pagine delle impostazioni (sezione `news`, dopo *Assistenza*).

**Tech Stack:** Laravel 13, Inertia, spatie/laravel-data, Vue 3 + TS, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-15-releases-design.md` (forma di `VERSION` e `notes.json`) e le tavole della tela *Impostazioni*, *Impostazioni-pc*, *Imp-novita*, *Imp-novita-pc*.

## Decisioni prese

- Prima la voce stava nel menu di *Il mio account* e in *Altro*, con un pannello (`FmReleaseNotesSheet`): il proprietario ha scelto le impostazioni (15/11/2026). Pannello, composable `useReleaseNotes` e prop condivise `release` e `release_notes` sono stati tolti, e così le tavole *Novita* e *Novita-pc*.
- *Novità* è una pagina e non un pannello, come le altre voci delle impostazioni; nessun componente nuovo in `components/fm/`.

## Global Constraints

- `notes.json`: `[{ "version": "1.2.0", "date": "2026-11-15", "notes": { "it": [...], "en": [...] } }]`, dalla più nuova. I file e `config('app.version')` li scrive il flusso dei rilasci: qui non si toccano `config/app.php`, `VERSION`, `resources/release/*`.
- Testi in inglese con `lang/it.json`; date scritte `15/11/2026` con `useFormat`.
- Icona `Sparkles` di Lucide.

## Review Focus

- `VERSION` mancante (`dev`): la riga dice *Versione di sviluppo*, senza data.
- Una versione senza note in `notes.json`: la riga dice solo *Versione X.Y.Z*.
- File delle note rotto o mancante: la pagina dice *Ancora nessuna novità.*, nessun errore.
- Una versione senza note nella lingua del browser: prende quelle dell'altra lingua.
- Telefono collegato: `settings.news` non è nell'elenco di `LimitLinkedDevice` e porta alla *Scansione*.

### Task 1: server

- [x] Test in `tests/Feature/Settings/ReleaseNotesTest.php` (props dell'indice, pagina, lingua, `member`, ospite, telefono collegato, file rotto o mancante).
- [x] `ReleaseNotes::released()`, `SettingsController` con `release` e `releasedOn`, `ReleaseNotesController` e rotta `settings.news`.
- [x] Via le prop condivise `release` e `release_notes` da `HandleInertiaRequests`.

### Task 2: pagine

- [x] `pages/settings/News.vue` con il suo test: una sezione per versione, la pillola *La tua versione*, la data, le note, il vuoto.
- [x] `Settings.vue`: gruppo *L'app* con la riga *Novità* (*Versione X.Y.Z · data*).
- [x] `FmSettingsPage`: sezione *Novità* dopo *Assistenza*.
- [x] Tolti la voce del menu, il pannello e il composable; `boards.json` senza `FmReleaseNotesSheet`.

### Task 3: tela e documentazione

- [x] Tavole *Impostazioni*, *Impostazioni-pc*, *Imp-novita*, *Imp-novita-pc*; tolte *Novita* e *Novita-pc*, e la voce in *Altro* e *Navigazione*.
- [x] Documentazione nel vault.
