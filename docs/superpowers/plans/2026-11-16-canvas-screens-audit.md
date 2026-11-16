# La tela allineata al codice — piano

> **Per chi esegue:** si lavora come dice lo spec (*Come si lavora un flusso*): chi coordina scarica le tavole, i subagenti fanno il confronto leggendo file locali, un fork di chi coordina disegna e pubblica. Le caselle (`- [ ]`) si spuntano qui dopo ogni flusso pubblicato.

**Obiettivo:** ogni pagina dell'app ha nella tela le sue tavole, telefono e larga, uguali a quello che fa il codice oggi.

**Come:** un flusso (pagina della tela) alla volta, nell'ordine dello spec; per ognuno confronto, ridisegno, tavole nuove, pubblicazione e resoconto al proprietario.

**Strumenti:** Artifact tool sulla tela `https://claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr` (tipo *Design*), file `.dc.html`, token di `project/ds/family-manager/tokens.css`.

**Spec:** `docs/superpowers/specs/2026-11-16-canvas-screens-audit-design.md`

## Vincoli di tutto il lavoro

- Il codice non si tocca; nessun commit tranne questo piano e le sue caselle.
- Colori e ombre solo con `var(--…)` di `tokens.css`; raggi in px con i valori dei token; testo con misura, interlinea e peso degli stili di `type` di `tokens.json`.
- Testi in italiano presi da `lang/it.json`, nome dell'app sempre «Fameja.app».
- Telefono 390 px di larghezza, versione larga come le tavole `-pc` vicine; nella riga: telefono, tablet, computer, 80 px tra le tavole; 120 px più il titolo tra le righe.
- Una tavola nuova: file in `project/`, voce in `boards` (`x`, `y`, `w`, `h` = `$preview`, `title`, `page`) e in `order` di `canvas.json`.
- Prima di pubblicare si rilegge `canvas.json` e si cambiano solo le proprie chiavi; si mandano solo i file toccati; con `canvas.json`, `file_path` su `canvas.json` e le tavole in `files` (`.claude/learnings.md`).
- Le tavole scaricate stanno in `.superpowers/sdd/canvas-audit/<pagina>/boards/`.

## Da controllare nel resoconto (Review Focus)

1. Un `member` vede meno tasti di un `admin`: se la tavola mostra solo l'`admin`, lo si dice nel titolo della tavola o si aggiunge la variante quando cambia la disposizione.
2. Stati vuoti ed errori delle pagine (lista vuota, 404 di un id sbagliato non serve): tavola solo se esiste nel codice e cambia la disposizione.
3. Pannelli (`FmBottomSheet`) e pannelli di lato della versione larga: ognuno che ha un modulo suo prende una tavola.
4. Testi lunghi e importi grandi: le tavole usano i dati dei Bianchi, che devono stare nella riga come nel codice (`splitTail` per i titoli lunghi).
5. Una tavola modificata da altri dopo il download: il rifiuto della pubblicazione lo dice; si riscarica e si rifà la modifica su quella copia.

---

## Il giro di ogni flusso (vale per tutte le task)

1. Scaricare `project/canvas.json` e tutte le tavole della pagina (`Artifact read`, `paths`, `out_dir` nella cartella del flusso).
2. Lanciare i subagenti di confronto, uno per riga della pagina (titoli `title1`), in parallelo. Prompt: percorsi delle tavole, pagine Vue e controller della task, `lang/it.json`, `resources/design/boards.json`; uscita per tavola: differenze (struttura / testo / componente / stato), voluta o no (`git log -1 --format=%cs` della pagina contro la tavola), e tavole mancanti con il contenuto.
3. Leggere i risultati, togliere quello che è voluto, decidere le tavole nuove (stato che cambia disposizione o tasti).
4. Fork di disegno: modifica le copie scaricate, crea le nuove, aggiorna `canvas.json`, pubblica.
5. Resoconto al proprietario: aggiornate, nuove, differenze lasciate da decidere. Spuntare la task.

## Task 1: Bilancio (`soldi`)
Codice: `budget/Movements.vue`, `budget/Movement.vue`, `budget/Comparison.vue`, `budget/Planned.vue`, `budget/PlannedItem.vue`, `budget/EditPlanned.vue`, `components/budget/`, `app/Http/Controllers/Budget/`.
Tavole nuove già note: un movimento e la sua modifica (`Movement`), nuova e modifica di una spesa fissa (`EditPlanned`). Controllare `Spese-fisse-filtri-dettaglio-pc.dc.html` (nuova, di un'altra sessione).
- [x] Giro del flusso fatto e pubblicato

## Task 2: Pagare (`pagare`)
Codice: `budget/Deadline.vue` (cambiata con `b5eea95`), `cards/CardPayments.vue`, `FmChangeDeadlineSheet`, il pannello del pagamento, entrata incassata, premio di una polizza.
- [x] Giro del flusso fatto e pubblicato

## Task 3: Agenda (`agenda`)
Codice: `Agenda.vue`, `reminders/EditReminder.vue` (nuovo e modifica), pannelli di riga, calendario, polizza rinnovata.
- [x] Giro del flusso fatto e pubblicato

## Task 4: Schede (`schede`)
Codice: `Areas.vue`, `Area.vue`, `cards/Card.vue` per ogni tipo di `app/Cards/Types/`, `cards/NewCard.vue`, `cards/EditCard.vue`. Una tavola per tipo di scheda che ha un aspetto suo.
- [x] Giro del flusso fatto e pubblicato

## Task 5: Caricare (`caricare`)
Codice: `documents/Upload.vue`, `documents/ToSort.vue`, `documents/Import.vue`, `scan/Scan.vue`, `scan/Scanner.vue`, `connect/Connect.vue`, `connect/Linked.vue`, `mail/ReceivedMails.vue`.
- [x] Giro del flusso fatto e pubblicato

## Task 6: Documenti (`documenti`)
Codice: `documents/Documents.vue`, `documents/Document.vue`, `documents/EditDocument.vue`.
- [ ] Giro del flusso fatto e pubblicato

## Task 7: Famiglia (`famiglia`)
Codice: `family/People.vue`, `Person.vue`, `EditPerson.vue`, `Organizations.vue`, `Organization.vue`, `EditOrganization.vue`, `Invite.vue`, `InvitationSent.vue`.
Tavole nuove già note: modifica persona, modifica ente, invito mandato.
- [ ] Giro del flusso fatto e pubblicato

## Task 8: Accesso (`accesso`)
Codice: `auth/*.vue`, `welcome/Welcome.vue`, le email (`resources/views/vendor/mail`, `app/Mail/`).
Tavole nuove già note: password nuova dal link, conferma dell'email, conferma della password, invito non valido, primo passo del benvenuto.
- [ ] Giro del flusso fatto e pubblicato

## Task 9: Impostazioni (`impostazioni`)
Codice: `settings/*.vue`, `FmFeedbackSheet`.
Tavola nuova già nota: categorie delle spese (`ExpenseCategories`).
- [ ] Giro del flusso fatto e pubblicato

## Task 10: Ogni giorno (`giorno`)
Codice: `Home.vue`, `notices/Notices.vue`, `Search.vue`, il menu del +, *Altro*, la campanella.
- [ ] Giro del flusso fatto e pubblicato

## Task 11: Tablet e Tema scuro (`tablet`, `scuro`)
Codice: `useLayoutMode()` e il layout (`AppLayout`), tema scuro di `tokens.css`. Si allineano alle tavole già aggiornate nelle task di sopra.
- [ ] Giro del flusso fatto e pubblicato
