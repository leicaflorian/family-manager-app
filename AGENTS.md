# Family Manager - app per le famiglie

Repository della nuova app di Family Manager. Oggi c'è solo il prototipo della tappa 1, in `prototype/`. Analisi, piano e decisioni stanno nel vault Obsidian, voce del ToDo *Nuova app per le famiglie in Laravel* (`MyVault/Projects/Family Manager/ToDo/_Opened/`): si leggono prima di lavorare qui.

## Regole del prototipo

- Tutti i testi in italiano semplice, al tu, senza parole tecniche ("record", "collection", "relazione", "campo").
- Pensato per il telefono: colonna `max-w-md` centrata, bersagli tattili alti almeno 44 px.
- Grafica come le schermate approvate su Claude Design; colori e scelte sono nel `Piano.md` della voce, sezione *Scelte di grafica*, e nei token di `prototype/src/style.css`.
- Importi come `84,20 €`, date come `15/10/2026` (funzioni di `src/logic/formato.ts`).
- Nessun dato vero, nessuna chiamata di rete per i dati, nessuna lettura AI. La famiglia inventata è in `src/data/famiglia.ts`.
- La logica pura sta in file `.ts` con i test Vitest accanto (test prima del codice); le pagine Vue la usano soltanto.
- Form con FormKit, elenchi animati con `v-auto-animate`, date con Tempo.
- Le classi di un campo FormKit (`:classes`, o `submit` e `family:box` in `formkit.config.ts`) si aggiungono a quelle di `global`, non le sostituiscono. Quando due classi si contraddicono vince quella che viene dopo nel CSS: per questo `mb-0` non toglieva `mb-3.5`, e `bg-azione` restava sotto `bg-transparent`. Per cambiare una classe di `global` si comincia con `$reset`, che le toglie tutte, e si riscrivono quelle che servono.
- npm 10.8 si ferma con `Cannot read properties of null (reading 'edgesOut')` installando queste dipendenze: si usa `npx npm@11 install`.
