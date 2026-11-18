# Avviso di una versione nuova - design

Data: 18/11/2026. Dimensione: piccola modifica.

## Perché

Dopo un rilascio (`./release` → `release.yml` → deploy su Coolify) chi ha l'app aperta resta sul codice vecchio finché non naviga. Alla prima visita Inertia risponde 409 e ricarica tutta la pagina senza dire niente, e un modulo a metà si perde. Il proprietario vuole che la persona sappia che c'è una versione nuova e scelga lei quando ricaricare.

## Obiettivi

- Una pagina aperta si accorge da sola, entro pochi minuti o quando l'app torna in primo piano, che il server ha una versione più nuova della sua.
- Compare un avviso che resta finché la persona non lo chiude: «There's a new version» / «C'è una versione nuova», con il tasto «Reload» / «Ricarica».

## Fuori

- Il segnale delle *Novità* non lette: scartato dal proprietario per ora.
- Avvisi spinti dal server (WebSocket, Web Push) e chiamate da Coolify a fine build: non servono, perché la versione la conosce già il contenitore nuovo (`VERSION`).

## Cosa non deve succedere

- L'avviso non compare in locale (`dev`) né quando la pagina è più nuova del server: durante il *rolling update* una richiesta può ancora arrivare al contenitore vecchio.
- Il controllo non apre sessioni e non manda cookie: va chiesto spesso e da tutti.
- Ricaricare non butta via un modulo a metà senza chiedere.

## Strade considerate

1. **Rotta della versione** (scelta): `GET /version` risponde il numero di `config('app.version')`; la pagina la chiede e la confronta con `__APP_BUILD__`. Poco codice, nessun servizio in più, vale su ogni dispositivo.
2. Avvisi spinti dal server (Reverb o Web Push): immediati, ma con un servizio in più su un droplet che ha poca memoria, e Web Push su iPhone va solo con l'app installata. Scartata.
3. Usare il 409 di Inertia: arriva quando la pagina si sta già ricaricando. Scartata.

## Come

- **Server**: `GET /version`, fuori dal gruppo `web` come `calendar.feed` (`withoutMiddleware('web')`, `throttle`), risponde JSON `{ "version": "2.2.2" }` con `Cache-Control: no-store`. Va nell'elenco delle rotte che il telefono collegato può aprire? No: è fuori dal gruppo `web`, quindi `LimitLinkedDevice` non la vede; il test lo conferma.
- **Pagina**: un composable (`useNewVersion`) avviato da `AppLayout`: chiede `/version` quando la finestra torna visibile (`visibilitychange`) e ogni 10 minuti mentre è visibile. Se la versione del server è più alta di `__APP_BUILD__` (confronto `major.minor.patch`; `dev` o un valore non valido non avvisano mai), mostra una volta sola l'avviso e smette di chiedere. Un errore di rete non fa niente.
- **Avviso**: il toast di `FmToaster` (`vue-sonner`), di tipo informazione, con la durata infinita e il tasto «Reload», che fa `window.location.reload()`. Con un modulo a metà il browser chiede già di confermare (`useUnsavedChanges` ascolta `beforeunload`). Nessun componente nuovo.
- **Tela**: nella scheda di `FmToaster` della tavola *Pannelli* si aggiunge il caso «versione nuova», telefono e computer.
- **Testi**: le chiavi inglesi con la traduzione in `lang/it.json`.

## Lasciato a chi costruisce

Dove sta il confronto delle versioni (una utility in `resources/js/lib/`), i nomi dei file e dei test, l'ordine dei passi.
