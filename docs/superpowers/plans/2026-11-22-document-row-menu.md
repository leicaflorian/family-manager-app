# Il menu ⋯ di ogni riga di documento - piano

> **Per chi esegue:** `superpowers:subagent-driven-development`, un task alla volta, su `dev` (scelta del proprietario, 22/11/2026: niente worktree).

**Obiettivo:** ogni riga che porta a un documento ha a destra un menu ⋯ con *Fissa in cima* / *Togli dalla cima* (solo documenti personali), *Scarica*, *Apri in una nuova scheda*, *Modifica*; il menu delle schede prende *Scarica*.

**Architettura:** `DocumentData` porta quello che serve al menu (`download_url`, `pinned`, `can_edit`, `can_pin`), con «personale» calcolato nella query per tutta la lista; `FmDocumentRow` prende una prop che accende il menu, accanto al link; la visita di fissa/togli sta in un composable condiviso con `FmDocumentActions`.

**Tecnica:** Laravel 13, Inertia 3, Vue 3 + TypeScript, Pest, Vitest + Testing Library, `spatie/laravel-data`.

**Design:** `docs/superpowers/specs/2026-11-22-document-row-menu-design.md`.

## Vincoli di tutto il lavoro

- Branch `dev`, cartella principale `/Users/florianleica/Dev/family-manager-app` (container: `/var/www/html`). Altre sessioni lavorano nello stesso repository: prima di toccare un file `git status`; commit solo dei propri file con `git commit -- <file>…`; errori dal lavoro aperto di altri si dicono, non si correggono.
- Codice, commenti, PHPDoc/TSDoc e nomi dei test in inglese; `declare(strict_types=1)`, classi `final`, PHPDoc su ogni classe e metodo, PHPStan 9 senza ignore, Rector, Pint.
- Testi in inglese con l'italiano in `lang/it.json`, aggiunto **a testo** prima della `}` finale (solo righe aggiunte; prima si cercano le chiavi esistenti: *Download*, *Open in a new tab*, *Edit*, *Pin at the top*, *Unpin* ci sono già).
- TDD: test prima, visto fallire per il motivo giusto.
- Pest con un database suo: `docker exec -u sail -e DB_DATABASE=testing_rowmenu family-manager-app-laravel.test-1 php artisan test <file>` (scritto per esteso); un Pest alla volta. Funzioni di aiuto dei test con il nome del file davanti.
- Vitest: `docker exec -u sail family-manager-app-laravel.test-1 npx vp test run <file>`. Un clic su un `Link` di Inertia nei test vuole `vi.spyOn(router, 'visit').mockImplementation(() => {})`; mock e stub si rimettono in `afterEach`.
- Dopo una classe dati `docker exec -u sail family-manager-app-laravel.test-1 php artisan typescript:transform` (si committano `generated.d.ts` e il manifest).
- PHPStan con un neon temporaneo (`tmpDir: /tmp/phpstan-rowmenu`, non si committa); Rector `--dry-run` sui file toccati.
- Le tavole stanno in `.superpowers/sdd/2026-11-22-document-row-menu/boards/` (le scarica chi coordina): la tavola vince sul codice.
- Prima del commit: test dei file toccati e di chi li usa, PHPStan, Rector, Pint, `npm run types`, `npx vp check` sui file toccati.

## Punti da controllare nella revisione

1. **Nessuna query per riga**: «personale» e i permessi su una lista di 15 documenti costano un numero fisso di query. Test nel task 2.
2. **Il bottone ⋯ non sta dentro il link**: un clic sul menu non apre il documento. Test nel task 3.
3. **Fissa dal menu di una riga di *Gli altri*** nella pagina della persona: dopo la visita il documento è in cima e non più tra gli altri. Test nel task 4.
4. **Un documento collegato a una scheda** non ha *Fissa in cima* nel menu. Test nei task 2 e 3.
5. **Le righe che scelgono un documento** (pannello *Collega un documento*) non hanno il menu. Test nel task 4.

---

### Task 1: Le tavole (chi coordina, con un fork)

- [ ] *Righe*: nella scheda di `FmDocumentRow` il caso con il ⋯ a destra (chiuso e aperto, con le quattro voci; senza *Fissa in cima* per un documento collegato).
- [ ] *Persona-tanti* e *-pc*, *Ente* e *-pc*, *Documenti* e *Documenti-pc*: il ⋯ sulle righe dei documenti (non sulle tessere in primo piano).
- [ ] *Scheda* e *Scheda-pc*: *Scarica* nel menu ⋮ di un documento.
- [ ] Pubblicare e copiare le tavole nella cartella delle tavole.

### Task 2: Il server manda quello che serve al menu

**Files:** `app/Data/DocumentData.php`, `app/Models/Document.php` (uno scope che aggiunge «collegato» con un `exists` su `documentables`), `app/Queries/DocumentRows.php`, `app/Http/Controllers/DocumentController.php` (`index`), `app/Data/MovementData.php` se costruisce `DocumentData`, `resources/js/generated/generated.d.ts`, `resources/js/test/fixtures.ts`; test in `tests/Feature/Documents/DocumentsTest.php` e `tests/Feature/Family/PeopleOrganizationsTest.php`.

**Interfaces — Produces:** `DocumentData` + `download_url: string` (come `FileData::download_url`), `pinned: bool` (`pinned_at` pieno), `can_edit: bool` (`Gate::allows('update', $document)`), `can_pin: bool` (`can_edit` e documento personale: `person_id` pieno e nessuna riga in `documentables`). `DocumentData::fromDocument` legge «collegato» dall'attributo che mette lo scope; dove lo scope manca (`MovementData`, documenti collegati a un movimento) il chiamante lo aggiunge, mai una query per riga.

- [ ] **Test:** `it sends what the row menu needs` (Archivio: un personale fissato → `pinned` true, `can_pin` true, `can_edit` true, `download_url` = quello del file; uno collegato a una scheda → `can_pin` false); `it reads whether documents are personal without a query per row` (pagina dell'Archivio con 15 documenti: numero di query uguale con 3 e con 15 documenti, `DB::enableQueryLog`); nella persona e nell'ente le righe hanno gli stessi campi.
- [ ] Falliscono; poi il codice; verdi; `typescript:transform`; PHPStan, Rector, Pint, `npm run types`.
- [ ] Commit «Documents: what the row menu needs».

### Task 3: Il menu di `FmDocumentRow`

**Files:** `resources/js/components/fm/FmDocumentRow.vue` e test, `resources/js/composables/useDocumentPin.ts` e test, `resources/js/components/fm/FmDocumentActions.vue` (usa il composable); tavola `Righe.dc.html`.

**Interfaces — Produces:** `useDocumentPin(): { togglePin(document: { id: number; pinned: boolean }): void; pinLabel(pinned: boolean): string }` — `router.put(pin.url(id), {}, { preserveScroll: true })` / `router.delete(unpin.url(id), { preserveScroll: true })`. `FmDocumentRow` prop `menu?: boolean`: con `menu` la riga è un contenitore con il link e accanto `FmMenu` (icona ⋯, nome accessibile *More for «:title»*), voci nell'ordine del design, ognuna solo se vale (`can_pin`, `can_edit`); *Scarica* è un link con `download`, *Apri in una nuova scheda* un link esterno a `file_url`, *Modifica* il link a `documents.edit`.

- [ ] **Test:** senza `menu` niente bottone (come oggi); con `menu` il bottone ⋯ c'è, e il menu aperto ha le voci nell'ordine; *Fissa in cima* manda `router.put`, con `pinned` dice *Unpin* e manda `router.delete`; senza `can_pin` niente voce; senza `can_edit` niente *Edit*; il bottone non è dentro il link (`closest('a')` nullo) e il clic non chiama `router.visit`; `FmDocumentActions` continua a passare i suoi test usando il composable.
- [ ] Falliscono; codice seguendo la tavola *Righe*; verdi (`FmDocumentRow`, `FmDocumentActions`, `useDocumentPin`, `design/*`), tipi, `vp check`.
- [ ] Commit «FmDocumentRow: the ⋯ menu».

### Task 4: Il menu nelle pagine, *Scarica* nelle schede

**Files:** `resources/js/pages/family/Person.vue`, `resources/js/pages/family/Organization.vue`, `resources/js/pages/documents/Documents.vue` e i loro test; `resources/js/components/fm/FmCardDocumentActions.vue` e test; tavole *Persona-tanti*, *Ente*, *Documenti*, *Scheda*.

- [ ] **Test:** nelle tre pagine le righe dei documenti hanno il ⋯ (persona: gli altri e *Altri documenti*; non le tessere); il pannello *Collega un documento* (`FmLinkDocumentSheet`) e le righe di `FmCardFiles` non hanno il ⋯ di `FmDocumentRow`; `FmCardDocumentActions` ha *Download* (link con `download` al `download_url` del documento) nel menu, nella pagina e nel modulo; nella persona, *Fissa in cima* dal menu di una riga di *Gli altri* manda la visita con `only: ['pinned', 'personal', 'personalNext', 'personalTotal']` e `reset: ['personal']` (come la puntina) — se serve, `FmDocumentRow` accetta le opzioni della visita da chi la usa.
- [ ] Un Pest in `PeopleOrganizationsTest`: dopo `PUT documents.pin` il ricaricamento parziale con quelle prop dà il documento in `pinned` e non in `personal`.
- [ ] Falliscono; codice; verdi; tipi; `vp check`.
- [ ] Commit «Document rows: the ⋯ menu on the pages, Download on the cards».

### Task 5: Prova nel browser e chiusura

- [ ] `npm run build` e prova con Playwright su un database di prova riempito dal seeder (container su 8091, `DB_DATABASE=testing_rowmenu_dev`): menu su persona, ente, Archivio, scheda; fissare da una riga; telefono e computer; confronto con le tavole.
- [ ] Revisione finale, documentazione con `project-docs`.

## Decisioni prese

- Il menu si accende con una prop, solo sulle righe che portano a un documento; nelle schede si aggiunge *Scarica* al menu che c'è già, non un secondo menu.
