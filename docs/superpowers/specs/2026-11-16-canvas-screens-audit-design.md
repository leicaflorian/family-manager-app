# La tela allineata al codice: tutte le schermate di ogni flusso

Data: 16/11/2026. Richiesta del proprietario: «guarda il codice e crea nella tela tutte le schermate necessarie, le schermate di ogni flusso, aggiornate».

## Perché

La tela (`claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr`) è il riferimento delle pagine, ma da inizio novembre il codice ha avuto circa 575 commit: molte tavole sono rimaste indietro e una decina di pagine non ha una tavola. Il proprietario vuole che la tela torni a mostrare ogni flusso come è oggi, così le prossime modifiche partono da tavole vere.

## Obiettivi

- Ogni pagina di `resources/js/pages/` ha le sue tavole (telefono e larga), nella riga del suo tipo, secondo la regola delle righe delle pagine delle schermate.
- Ogni tavola esistente mostra quello che fa il codice oggi: struttura, testi (in italiano, da `lang/it.json`), componenti di `components/fm/`, pannelli e stati che contano (vuoto, errore, `member` senza i tasti dell'`admin`).
- Le tavole toccate passano ai `var(--…)` di `tokens.css` (regola di `AGENTS.md`).

## Non obiettivi

- Non si cambia il codice. Dove tavola e codice non vanno d'accordo **per scelta** (la tavola è più nuova, o è una decisione presa), la tavola resta com'è e la differenza si segnala al proprietario.
- Non si toccano le pagine *Fondamenta* e *Componenti* (tavole dei componenti), salvo che una schermata usi un componente che lì manca: allora si segnala.
- `scan/DewarpCheck` resta fuori: è una pagina di prova per lo sviluppo.

## Anti-obiettivi

- Tavole «inventate»: un campo, un tasto o un testo che il codice non ha (è già successo, vedi `.claude/learnings.md`, *Le tavole le disegna un fork*).
- Sovrascrivere un cambio fatto nella tela da un'altra sessione o a mano: la tela cambia anche mentre si lavora (per esempio `Spese-fisse-filtri-dettaglio-pc.dc.html`, comparsa il 16/11/2026 durante l'analisi).

## Approcci considerati

- **A. Un flusso alla volta (scelto dal proprietario).** Per ogni pagina della tela: confronto codice ↔ tavole, ridisegno, tavole nuove, pubblicazione, resoconto. Il proprietario controlla una decina di tavole alla volta.
- B. Prima l'elenco di tutte le differenze, poi i disegni: quadro intero subito, ma nessuna tavola nuova per molto tempo e un elenco ingestibile.
- C. Partire dalle foto delle pagine vere (Chromium headless, dati dei Bianchi): il più fedele, ma vuole Vite o una build per tutto il lavoro. Si usa solo dove il codice non basta (vedi sotto).

## Ordine (deciso dal proprietario)

1. *Bilancio* (`soldi`) 2. *Pagare* 3. *Agenda* 4. *Schede* 5. *Caricare* 6. *Documenti* 7. *Famiglia* 8. *Accesso* 9. *Impostazioni* 10. *Ogni giorno* 11. *Tablet* 12. *Tema scuro*.

## Come si lavora un flusso

1. **Scaricare** `project/canvas.json` e tutte le tavole della pagina della tela in `.superpowers/sdd/canvas-audit/<pagina>/boards/`.
2. **Confronto**, un subagente per gruppo di schermate (uno per riga della pagina), che legge le tavole da quei file e il codice: la pagina Vue, i componenti che usa, il controller e la classe dati (per i campi e i permessi), `lang/it.json` per i testi. Restituisce per ogni tavola: cosa è diverso (strutturale, testo, componente, stato mancante), se la differenza sembra voluta (tavola più nuova del codice: si guarda `git log` della pagina e la data delle *Decisioni prese*), e le tavole che mancano con quello che devono mostrare.
3. **Disegno**: un fork di chi coordina (ha la tela e il contesto) ridisegna le tavole cambiate e crea le nuove, partendo dalle tavole dei componenti e dai token, con i dati dei Bianchi come nelle tavole vicine. Tavole nuove: posizione nella riga del loro tipo (telefono, tablet, computer, 80 px tra le tavole), voce in `boards` e `order` di `canvas.json`, `title` in italiano.
4. **Pubblicazione**: subito prima si rilegge `canvas.json` e si cambiano solo le proprie chiavi; si manda solo quello che si è toccato. In caso di rifiuto si seguono le strade di `.claude/learnings.md`.
5. **Resoconto** al proprietario: tavole aggiornate, tavole nuove, differenze lasciate di proposito (da decidere lui).

**Foto delle pagine vere**: solo quando una schermata dipende da dati o disposizioni che dal codice non si vedono (liste lunghe, a capo, grafici). Si chiede al proprietario di accendere Vite, oppure si usa una build in un worktree (container temporaneo, `.claude/learnings.md`), mai `npm run dev` lanciato dall'agente.

## Le pagine senza tavola (punto di partenza)

- Accesso: `auth/ResetPassword`, `auth/VerifyEmail`, `auth/ConfirmPassword`, `auth/InvalidInvitation`, primo passo di `welcome/Welcome`.
- Bilancio: `budget/EditPlanned` (nuova e modifica), `budget/Movement` (un movimento e la sua modifica).
- Famiglia: `family/EditPerson`, `family/EditOrganization`, `family/InvitationSent`.
- Impostazioni: `settings/ExpenseCategories`.

Il confronto di ogni flusso può trovarne altre (pannelli e stati).

## Cosa resta a chi lavora

La scelta dei dati di esempio (quelli dei Bianchi, come le tavole vicine), quali stati di una pagina meritano una tavola a sé (regola: uno stato che cambia la disposizione o i tasti sì, un testo diverso no) e la divisione in gruppi per i subagenti.
