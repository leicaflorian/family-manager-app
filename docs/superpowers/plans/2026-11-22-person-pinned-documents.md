---
tipo: piano d'azione
stato: da iniziare
analisi: "[[Projects/Family Manager/ToDo/_Opened/Persona - documenti in primo piano/Analisi|Persona - documenti in primo piano]]"
da controllare:
---
← [[Projects/Family Manager/ToDo/_index|ToDo]]

# Persona - documenti in primo piano - piano

> **Per chi esegue:** si usa `superpowers:subagent-driven-development` (consigliato) o `superpowers:executing-plans`, un task alla volta; le caselle si spuntano qui dopo il commit di ogni task.

**Obiettivo:** nei *Documenti personali* di una persona i documenti fissati a mano stanno in cima (*In primo piano*), sopra *Gli altri, dal più recente*; nel menu ⋯ del documento c'è *Fissa in cima* / *Togli dalla cima*. Nella pagina di un ente i documenti arrivano 10 alla volta, con il numero, *Apri nell'Archivio* e *Mostra altri 10 · ne restano N*.

**Architettura:** due colonne nuove su `documents` (`pinned_at`, `pinned_by`); un'azione `PinDocument` dietro due rotte (`PUT`/`DELETE documents/{document}/pin`); un filtro `pinned` in `FilterDocuments` che divide i personali in fissati (lista intera, prop `pinned`) e altri (pagine, come oggi). Le pagine di un elenco di documenti passano da un posto solo, nel server (`PagesDocuments`) e nella pagina (`useMorePages`), usato da *Una persona* e da *Un ente*.

**Tecnica:** Laravel 13, Inertia 3 (`Inertia::merge`, ricaricamento parziale), Vue 3 + TypeScript, Pest, Vitest + Testing Library, `spatie/laravel-data`.

**Design:** `docs/superpowers/specs/2026-11-22-person-pinned-documents-design.md` (repository, `dev`), da leggere insieme a questo piano.

## Vincoli di tutto il lavoro

- Branch `dev`. Prima di toccare un file: `git status` e `ListAgents`; con altre sessioni aperte il commit si fa con i percorsi (`git commit -- <file>…`).
- Codice, commenti, PHPDoc/TSDoc e nomi dei test in inglese; `declare(strict_types=1)`, classi `final`, PHPDoc su ogni classe e metodo, PHPStan 9 senza ignore, Rector senza proposte, Pint.
- Testi in inglese con la traduzione in `lang/it.json`, aggiunta **a testo** prima della `}` finale (chiave doppia: niente parser JSON; `git diff --stat lang/it.json` solo righe aggiunte).
- TDD: il test prima, visto fallire per il motivo giusto.
- Pest con un database suo: `docker exec -u sail -e DB_DATABASE=testing_pinned family-manager-app-laravel.test-1 php artisan test <file>` (scritto per esteso); un Pest alla volta.
- Le funzioni di aiuto in cima a un test Pest hanno un nome che comincia con quello del file (`grep -rhoE "^function [a-zA-Z0-9_]+" tests | sort | uniq -d` non stampa niente).
- Un clic su un `Link` di Inertia nei test vuole `vi.spyOn(router, 'visit').mockImplementation(() => {})`.
- Dopo la migrazione `ide-helper:models --write --reset </dev/null` (si committa solo il PHPDoc di `Document`), dopo una classe dati `typescript:transform` (si committa `generated.d.ts`).
- Mai `migrate` sul database di sviluppo: a fine lavoro si chiede al proprietario `! ./vendor/bin/sail artisan migrate`.
- Prima del commit di ogni task: i test dei file toccati e di quelli che li usano, PHPStan, Rector, Pint, `npm run types`, `npx vp check` sui file toccati.
- I subagenti non aprono la tela: le tavole stanno in `.superpowers/sdd/pinned-documents/boards/` (le scarica chi coordina, task 1).

## Punti da controllare nella revisione

1. **Il documento fissato cambia persona**: deve sparire dalla cima della persona vecchia e non comparire fissato in quella nuova (le colonne si svuotano). Test nel task 2.
2. **Il documento fissato viene collegato a una scheda**: esce da *In primo piano* e non entra in *Gli altri* (non è più personale); se lo si scollega torna fissato. Test nel task 3.
3. **Un `member`** fissa e toglie (può modificare i documenti); **un id di un'altra famiglia** risponde 404; **un documento non personale** risponde 422 e non cambia niente. Test nel task 2.
4. **Il numero accanto al titolo** conta fissati più altri, e *ne restano N* conta solo gli altri ancora da mostrare (Marco: 42 = 6 + 5 mostrati + 31). Test nel task 4.
5. **Fissare due volte** non cambia `pinned_at` (l'ordine resta quello della prima volta). Test nel task 2.

---

### Task 1: Le tavole (chi coordina, con un fork)

**Files:** tela `https://claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr` — `project/Persona-tanti.dc.html`, `project/Persona-tanti-pc.dc.html`, `project/Ente.dc.html`, `project/Ente-pc.dc.html`, `project/Righe.dc.html`, `project/Documento.dc.html`, `project/Documento-pc.dc.html`; copie in `.superpowers/sdd/pinned-documents/boards/`.

- [ ] **Step 1:** *Persona-tanti* e *-pc*: le quattro tessere senza tasto (carta d'identità, patente, tessera sanitaria, codice fiscale) prendono la puntina come contratto e 730, e l'icona della categoria (*Anagrafica e pratiche* senza tipo di scheda: fondo `--canvas`, icona `--ink-soft`, `FileText`); il codice fiscale mostra `Agenzia Entrate · 12/03/1980` al posto del numero. Titolo del tasto: *Fissato da Laura · togli dalla cima*.
- [ ] **Step 2:** *Ente* e *Ente-pc*: Edison con 14 documenti; il riquadro *Documenti* prende il numero accanto al titolo (`14`, stile di *Altri documenti* di *Persona-tanti-pc*), *Apri nell'Archivio* a destra, 10 righe e sotto *Mostra altri 4 · ne restano 4* (il bottone di *Persona-tanti*). Altezze in `canvas.json` uguali a `$preview`.
- [ ] **Step 3:** *Documento* e *-pc*: nel menu ⋯ aperto la voce *Fissa in cima* (icona `Pin`) prima di *Elimina*; una nota a lato: per un documento già fissato *Togli dalla cima* (`PinOff`).
- [ ] **Step 4:** *Righe*: la scheda di `FmPinnedDocument` (task 5) con i casi: con scadenza futura, scaduto (rosso), senza scadenza, con l'icona di un'area, senza area, in due colonne.
- [ ] **Step 5:** Pubblicare (`file_path` su `project/canvas.json`, tavole in `files`, `root` sulla cartella) e copiare le tavole in `.superpowers/sdd/pinned-documents/boards/`.

### Task 2: Fissare un documento (dato, azione, rotte)

**Files:**
- Create: `database/migrations/2026_11_22_000001_add_pinned_to_documents.php`, `app/Actions/Documents/PinDocument.php`, `app/Http/Controllers/DocumentPinController.php`, `tests/Feature/Documents/PinDocumentTest.php`
- Modify: `app/Models/Document.php`, `routes/web.php` (accanto a `documents.file`), `lang/it.json`, `tests/Feature/RouteThrottleTest.php` solo se serve

**Interfaces:**
- Produces: colonne `documents.pinned_at` (timestamp, nullable) e `documents.pinned_by` (`foreignId` verso `users`, nullable, `nullOnDelete`), indice `(person_id, pinned_at)`; cast `pinned_at => 'immutable_datetime'`; relazione `Document::pinner(): BelongsTo<User, $this>` su `pinned_by`; `Document::isPersonal(): bool` (ha `person_id` e nessuna riga in `documentables`); `PinDocument::handle(Document $document, User $user, bool $pinned): void`; rotte `documents.pin` (`PUT documents/{document}/pin`) e `documents.unpin` (`DELETE documents/{document}/pin`), middleware `throttle:30,1,document-pin`, tutte e due `back()`.

- [ ] **Step 1: I test** in `PinDocumentTest.php`:
  - `it pins a personal document and remembers who and when` — `put(route('documents.pin', $doc))` → redirect indietro, `pinned_at` non vuoto, `pinned_by` = l'account.
  - `it keeps the first pinned_at when pinned again` — con `travelTo` due volte, `pinned_at` resta il primo.
  - `it unpins` — `delete(route('documents.unpin', $doc))` → `pinned_at` e `pinned_by` `null`.
  - `it lets a member pin and unpin` — account `member`, tutte e due 302.
  - `it refuses a document linked to a record` — 422 con errore su `document` (*Only a personal document can be pinned.*), niente cambia; anche un documento senza persona.
  - `it answers 404 for a document of another family`.
  - `it clears the pin when the document changes person` — `update(['person_id' => $other->id])` → `pinned_at` e `pinned_by` `null`.
- [ ] **Step 2:** lanciarli: falliscono (rotta mancante).
- [ ] **Step 3:** migrazione, modello (cast, `pinner()`, `isPersonal()`, in `booted()` un `updating` che svuota le due colonne quando cambia `person_id`), `PinDocument` (`Gate::authorize('update', $document)`; se non personale `ValidationException::withMessages(['document' => __('Only a personal document can be pinned.')])`; fissare scrive `pinned_at = now()` e `pinned_by` solo se `pinned_at` è vuoto; togliere svuota tutte e due), controller sottile (`store` e `destroy` con `#[CurrentUser]`), rotte, traduzione.
- [ ] **Step 4:** test verdi, poi `ide-helper:models` per `Document`, PHPStan, Rector, Pint.
- [ ] **Step 5:** commit «Documents: pin a personal document at the top».

### Task 3: Il server divide i personali in fissati e altri

**Files:**
- Create: `app/Data/PinnedDocumentData.php`, `app/Http/Controllers/Concerns/PagesDocuments.php`
- Modify: `app/Queries/FilterDocuments.php`, `app/Queries/DocumentRows.php`, `app/Http/Controllers/PersonController.php`, `app/Data/DocumentDetailData.php`, `app/Http/Controllers/DocumentController.php` (dove costruisce `DocumentDetailData`), `resources/js/generated/generated.d.ts`
- Test: `tests/Feature/Family/PeopleOrganizationsTest.php`, `tests/Feature/Documents/DocumentsTest.php`

**Interfaces:**
- Consumes: task 2 (`pinned_at`, `pinner`, `isPersonal()`).
- Produces:
  - filtro `pinned` di `FilterDocuments::apply()`: `true` → `whereNotNull('pinned_at')` ordinato per `pinned_at` crescente (al posto dell'ordine di sempre), `false` → `whereNull('pinned_at')`.
  - `DocumentRows::pinned(int $personId): list<PinnedDocumentData>` — i documenti della persona, personali (`linked => false`) e fissati, nell'ordine in cui sono stati fissati.
  - `PinnedDocumentData` estende i campi di `DocumentData` con `?string $area` (l'area del tipo di scheda della categoria: `DocumentCategory::cardTypeOf()` → `CardTypes::find()?->area()->value`, `null` senza) e `?string $pinned_by` (nome dell'account che l'ha fissato, `null` se l'account non c'è più). Le aree delle categorie si leggono una volta per richiesta, non per documento.
  - tratto `PagesDocuments` con `pageAsked(Request $request, string $list): int` (spostato da `PersonController`, stessa regola: la pagina chiesta solo nel ricaricamento parziale che chiede quella lista).
  - prop di *Una persona*: `pinned` (lista intera, `PinnedDocumentData[]`), `personal`/`personalNext`/`personalTotal` ora solo degli **altri** (filtro `pinned => false`); tutto il resto uguale.
  - `DocumentDetailData`: `bool $pinned` e `bool $can_pin` (`isPersonal()` e `can('update')`).
- [ ] **Step 1: I test**
  - in `PeopleOrganizationsTest`: `it sends the pinned personal documents apart, in the order they were pinned` (tre documenti personali, due fissati in ordine B poi A: `pinned` = [B, A], `personal` = solo il terzo, `personalTotal` = 1, `pinned.0.pinned_by` = nome dell'account, `pinned.0.area` per una categoria con tipo di scheda, `null` per una senza);
  - `it leaves a pinned document linked to a record out of both lists` e `it shows it pinned again once unlinked`;
  - in `DocumentsTest`: `it tells whether a document can be pinned` (`can_pin` vero per un personale, falso per uno collegato e per uno senza persona; `pinned` segue `pinned_at`).
- [ ] **Step 2:** falliscono.
- [ ] **Step 3:** filtro, `DocumentRows::pinned()`, `PinnedDocumentData`, tratto (`PersonController` lo usa e perde il suo `pageAsked`), prop `pinned` come closure (non si calcola nei ricaricamenti di *Mostra altri*), `DocumentDetailData`. Poi `typescript:transform`.
- [ ] **Step 4:** test verdi (anche quelli già esistenti di *Una persona* e dell'Archivio), PHPStan, Rector, Pint.
- [ ] **Step 5:** commit «A person: the pinned documents apart from the others».

### Task 4: Un ente, i documenti 10 alla volta (server)

**Files:**
- Modify: `app/Http/Controllers/OrganizationController.php`
- Test: `tests/Feature/Family/PeopleOrganizationsTest.php`

**Interfaces:**
- Consumes: `PagesDocuments::pageAsked()`, `DocumentRows::page()`.
- Produces: `OrganizationController::DOCUMENTS_PER_PAGE = 10`; prop di *Un ente*: `documents` (`Inertia::merge(...)->matchOn('id')`), `documentsNext` (`?int`), `documentsTotal` (`int`), `pageSizes: { documents: 10 }`; le altre uguali.
- [ ] **Step 1: I test:** `it sends the documents of an organization ten at a time` (12 documenti: `documents` 10, `documentsNext` 2, `documentsTotal` 12); `it adds the second page on the partial reload` (header `X-Inertia-Partial-Data: documents,documentsNext,documentsTotal`, `documents_page=2` → 2 documenti, `documentsNext` `null`); `it starts from the first page on a full visit even with documents_page in the address`.
- [ ] **Step 2:** falliscono.
- [ ] **Step 3:** il controller come `PersonController::show` (closure ricordata, una query per richiesta).
- [ ] **Step 4:** verdi, PHPStan, Rector, Pint.
- [ ] **Step 5:** commit «An organization: its documents ten at a time».

### Task 5: `FmPinnedDocument`, la tessera in primo piano

**Files:**
- Create: `resources/js/components/fm/FmPinnedDocument.vue`, `FmPinnedDocument.test.ts`
- Modify: `resources/design/boards.json` (`"FmPinnedDocument": "Righe"`), `lang/it.json`, *Decisioni prese* di questo piano
- Tavole: `.superpowers/sdd/pinned-documents/boards/Persona-tanti.dc.html`, `Persona-tanti-pc.dc.html`, `Righe.dc.html`

**Interfaces:**
- Consumes: `App.Data.PinnedDocumentData` (task 3), `areaTile`/`areaIcon`/`areaOf` di `lib/areas.ts`, la riga sotto di `FmDocumentRow` con `expiry`.
- Produces: `<FmPinnedDocument :document :href :can-unpin />`, emit `unpin: []`; `@group rows`.

Perché un componente nuovo: la tessera è una scheda a sé (fondo, raggio 16, ombra) con un tasto a destra, mentre `FmDocumentRow`/`FmRow` sono righe dentro un riquadro senza tasti; prima di crearlo si riguardano *Righe* e *Basi*, e se uno si estende con una variante si fa quello. La riga sotto (scadenza con colore, o cosa è e la data) **non si copia**: si sposta da `FmDocumentRow` in una funzione di `lib/documents.ts` (`documentLine(document, { expiry, today }): { text?: string; tone: RowTone }`) usata da tutti e due.

- [ ] **Step 1: I test:** il link con il titolo porta a `href`; con `expiry_date` passata la riga dice *Expired on 03/11/2026*, futura *Expires on 24/05/2029*, senza scadenza `documentSubtitle` e la data; con `area: 'work'` l'icona ha i colori dell'area, con `null` quelli neutri; con `canUnpin` c'è il bottone *Unpin «Patente B»* (`aria-pressed="true"`, `title` *Pinned by Laura · unpin*, senza nome *Pinned · unpin*) e un clic emette `unpin`; senza `canUnpin` il bottone non c'è.
- [ ] **Step 2:** falliscono.
- [ ] **Step 3:** il componente, seguendo la tavola (raggio `rounded-card`, `shadow-card`, icona 36 px `rounded-md`, puntina 36 px su `bg-action-soft text-action`, testi `text-row` e `text-meta`), e `documentLine()` con il suo test in `lib/documents.test.ts`; `FmDocumentRow` la usa (i suoi test restano verdi).
- [ ] **Step 4:** verdi (`FmPinnedDocument`, `FmDocumentRow`, `lib/documents`, `design/boards.test.ts`, `text-styles`, `edges`), tipi, `vp check`.
- [ ] **Step 5:** commit «FmPinnedDocument: a pinned document as a tile».

### Task 6: La pagina di una persona e quella di un ente

**Files:**
- Create: `resources/js/composables/useMorePages.ts`, `useMorePages.test.ts`
- Modify: `resources/js/pages/family/Person.vue`, `Person.test.ts`, `resources/js/pages/family/Organization.vue`, `Organization.test.ts`, `lang/it.json`
- Tavole: `Persona-tanti*.dc.html`, `Ente*.dc.html`

**Interfaces:**
- Consumes: prop dei task 3 e 4, `FmPinnedDocument`, rotte `documents.unpin` (Wayfinder `@/routes/documents`).
- Produces: `useMorePages<L extends string>(lists: readonly L[]): { loading: Ref<Record<L, boolean>>; loadMore(list: L, page: number): void }` — `router.reload({ data: { [`${list}_page`]: page }, only: [list, `${list}Next`, `${list}Total`], preserveUrl: true })`, `loading` vero fino a `onFinish`. `Person.vue` perde il suo `loadMore`.

- [ ] **Step 1: I test**
  - `useMorePages.test.ts`: chiama `router.reload` con `data`, `only` e `preserveUrl: true`; `loading[list]` vero finché non arriva `onFinish`; due liste indipendenti.
  - `Person.test.ts`: con `pinned` non vuoto compaiono *Pinned* e *The others, most recent first*, le tessere nell'ordine ricevuto; il numero del titolo è `pinned.length + personalTotal` (6 + 36 → `42`); *Show 5 more · 31 left* con 5 altri mostrati su 36; la puntina manda `router.delete` a `documents.unpin` con `preserveScroll: true`; senza `pinned` non ci sono né *Pinned* né *The others…* (la lista come oggi); nella disposizione larga la griglia delle tessere ha due colonne e c'è il suggerimento *Pin a document at the top from its menu*.
  - `Organization.test.ts`: numero accanto a *Documents* (`documentsTotal`), link *Open in the Archive* a `/documents?organization=<id>`, *Show 10 more · 2 left* con 10 su 12 che chiama `loadMore('documents', 2)`; senza documenti il testo di oggi e niente link.
- [ ] **Step 2:** falliscono.
- [ ] **Step 3:** `useMorePages`; `Person.vue` (le due parti dentro il riquadro *Personal documents*, tessere in `grid gap-2.5 wide:grid-cols-2`, la puntina solo se il documento si può togliere: chi vede la pagina può modificare i documenti, cioè `canEdit` già usato per *Add*, altrimenti si aggiunge la prop); `Organization.vue` come *Other documents* di *Person.vue*. Testi nuovi in `lang/it.json`: *Pinned* → «In primo piano», *The others, most recent first* → «Gli altri, dal più recente», *Pin a document at the top from its menu* → «Fissa in cima un documento dal suo menu», *Unpin «:title»* → «Togli «:title» dalla cima», *Pinned by :name · unpin* → «Fissato da :name · togli dalla cima», *Pinned · unpin* → «Fissato · togli dalla cima».
- [ ] **Step 4:** verdi (le due pagine, il composable, `AppLayout.test.ts` se tocca), tipi, `vp check`.
- [ ] **Step 5:** commit «A person: the pinned documents at the top; an organization: show more».

### Task 7: *Fissa in cima* nel menu del documento

**Files:**
- Modify: `resources/js/components/fm/FmDocumentActions.vue`, `FmDocumentActions.test.ts`, `lang/it.json`
- Tavole: `Documento.dc.html`, `Documento-pc.dc.html`

**Interfaces:**
- Consumes: `DocumentDetailData.pinned` / `can_pin` (task 3), rotte `documents.pin` / `documents.unpin`.

- [ ] **Step 1: I test:** con `can_pin` e non fissato il menu ⋯ ha *Pin at the top* (sulla pagina, `menuOnly` delle azioni, e nel pannello compatto) e la scelta manda `router.put` a `documents.pin` con `preserveScroll: true`; fissato dice *Unpin* e manda `router.delete`; senza `can_pin` la voce non c'è.
- [ ] **Step 2:** falliscono.
- [ ] **Step 3:** la voce (icone `Pin` / `PinOff`) nelle azioni e nel menu compatto, prima di *Delete*. Traduzioni: *Pin at the top* → «Fissa in cima», *Unpin* → «Togli dalla cima».
- [ ] **Step 4:** verdi (anche `Document.test.ts` e `Documents.test.ts`), tipi, `vp check`.
- [ ] **Step 5:** commit «Documents: pin and unpin from the menu».

### Task 8: Prova nel browser e chiusura

- [ ] **Step 1:** `migrate:status --pending` (solo lettura) e, se serve, chiedere al proprietario `! ./vendor/bin/sail artisan migrate`.
- [ ] **Step 2:** Playwright (Chromium headless, account dei Bianchi): fissare tre documenti personali di Marco dal menu, vederli in cima nell'ordine, toglierne uno dalla puntina, telefono (390×844) e computer (1440×900); un ente con più di 10 documenti, *Mostra altri* e il link all'Archivio. Confronto con le tavole.
- [ ] **Step 3:** revisione finale di tutto il ramo, con i punti da controllare sopra.
- [ ] **Step 4:** documentazione con `project-docs` (pagina di una persona, di un ente, menu del documento), *Decisioni prese* qui sotto, analisi e piano con lo stato `da controllare`.

## Decisioni prese

- Fissato è un dato del documento, uguale per tutta la famiglia, con chi l'ha fissato (proprietario, 22/11/2026). Solo a mano, nessun documento in cima da solo: le quattro tessere senza tasto della tela prendono la puntina.
- L'ordine di *In primo piano* è quello in cui si fissa; fissare di nuovo non lo cambia.
- Cambiare persona svuota il fissato; collegare a una scheda lo nasconde e scollegare lo rimette.
- Il link dei documenti dell'ente si chiama *Apri nell'Archivio*, come quello di *Una persona*.
