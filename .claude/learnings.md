# Learnings

Cose da ricordare lavorando su questo progetto, scritte dall'agente man mano che le scopre. Una voce per fatto, con la data; quando una voce non è più vera si corregge o si toglie.

## Come si lavora

- **Superpowers per ogni azione** (regola del proprietario, 27/10/2026): ogni volta che il proprietario chiede di fare qualcosa, prima di cominciare si invoca la skill di superpowers adatta e si lascia a lei il modo di eseguire la richiesta, seguendone i passi. Qualche esempio: `superpowers:brainstorming` per una funzione nuova o un cambio di comportamento, `superpowers:systematic-debugging` per un errore, `superpowers:writing-plans` ed `executing-plans` / `subagent-driven-development` per un piano, `superpowers:test-driven-development` per scrivere codice, `superpowers:verification-before-completion` prima di dire che una cosa è fatta, `superpowers:finishing-a-development-branch` a lavoro finito. Le regole di `AGENTS.md` e del proprietario valgono comunque più delle skill.
- **Il piano dopo un'analisi si scrive con `superpowers:writing-plans`** (regola del proprietario, 27/10/2026): quando il proprietario chiede di fare o scrivere il piano di una voce già analizzata, si invoca `superpowers:writing-plans` prima di cominciare e il piano lo si scrive seguendo la skill. Il risultato va nella nota `Piano` della voce del ToDo.

## Documentazione e ToDo

- **Documentazione del progetto** (27/10/2026): è il vault Obsidian in `/Users/florianleica/Library/CloudStorage/GoogleDrive-florian.leica@gmail.com/Il mio Drive/MyVault/Projects/Family Manager`, con `01 - Documentazione funzionale`, `02 - Documentazione tecnica`, `03 - Branding`, `04 - Cli` e `ToDo`. Prima di lavorare si leggono le note che servono; dopo una modifica al codice la documentazione si aggiorna lì, con la skill `project-docs`.
- **ToDo e work items** (27/10/2026): stanno in `Projects/Family Manager/ToDo`. In `_Opened` ci sono le voci aperte e in `_Done` quelle fatte o scartate. Ogni voce ha la sua cartella, con la nota `Analisi` e, dopo le decisioni del proprietario, la nota `Piano`.
  - `_index.md` spiega le proprietà delle note (`tipo`, `stato`, `creato`, `sintesi`, `peso`, `da controllare`, `piano`/`analisi`) e cosa si fa quando una voce è finita: si sposta la cartella in `_Done`, si mette `stato: fatto`, si aggiunge in cima all'analisi il callout `> [!success] Fatto il gg/mm/aaaa` e si descrive la funzione in `01 - Documentazione funzionale`. L'elenco lo fa `View.base`.
  - Le regole per scrivere una voce sono in `.claude/voci-del-todo.md` del repository `family-manager-cms`, non di questo.
  - Le voci di questa app: *Nuova app per le famiglie in Laravel*, *Nuova app - base dell'app*, *Nuova app - le pagine delle schede*.
  - Quando il proprietario nomina una voce, la si cerca lì e se ne leggono analisi e piano prima di scrivere codice.

## Codice

- **Le fixture del frontend hanno la forma che manda il server** (28/10/2026, pagine delle schede): due errori sono passati a tutti i test perché `resources/js/test/fixtures.ts` aveva gli id come testo mentre il server li mandava come numeri, e un interruttore FormKit mai toccato arrivava `null` a una colonna NOT NULL (errore 500). Una fixture nuova si copia da quello che manda il controller (o dal test Pest della classe dati), e un modulo ha un test che parte da valori come quelli del server; un `toggle` non toccato si manda `false`.
- **Un testo che vede la persona ha la chiave sua se il significato cambia**: la stessa chiave inglese (`"Link"`) era sia il campo URL di un abbonamento sia il tasto *Collega*, e in italiano non si possono tradurre tutte e due.
