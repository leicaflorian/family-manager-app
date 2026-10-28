# Family Manager - app per le famiglie

La nuova app di Family Manager, pensata per genitori che non sono tecnici. Prenderà il posto dell'interfaccia di Directus del repository `family-manager-cms`, e si potrà vendere ad altre famiglie: ogni famiglia vede solo i suoi dati.

È fatta in Laravel 13 dallo starter kit per Vue (Inertia, Vue 3, TypeScript, Tailwind 4, Fortify per l'accesso, Wayfinder per le rotte), e gira in Docker con Laravel Sail. Il prototipo della tappa 1 non c'è più: si recupera dalla storia di git, commit `54a6702` (`git show 54a6702:prototype/…`).

Il codice è in inglese e l'app parla inglese e italiano: prende la lingua del browser, e senza una delle due usa `APP_LOCALE` (`it`). Le traduzioni italiane sono in `lang/it.json` e `lang/it/`; come si aggiunge un testo è in `AGENTS.md`.

## Avviare l'app

Serve Docker. La prima volta:

```bash
cp .env.example .env
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
```

Per le anteprime dei documenti l'app usa `pdftoppm` (`poppler-utils`) e `heif-convert` (`libheif-examples`) e l'estensione `ext-gd` di PHP: l'immagine di Sail (`docker/8.5/`, Ubuntu 24.04, che aggiunge `libheif-plugin-libde265` per leggere gli HEIC) e `nixpacks.toml` (Ubuntu 22.04, dove quel decoder è già in `libheif1`) li installano, quindi non c'è niente da aggiungere. Se in locale l'anteprima non compare dopo un aggiornamento, si ricostruisce l'immagine con `./vendor/bin/sail build --no-cache`.

Poi, ogni volta:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

L'app è su http://localhost:8090, le email che manda su Mailpit, http://localhost:8025.

## Account di prova

Il seeder crea la famiglia Bianchi della tela. La password è `password` per tutti gli account:

| Account | Chi è | Livello |
| --- | --- | --- |
| `marco.bianchi@example.com` | Marco Bianchi | Tutto |
| `laura.bianchi@example.com` | Laura Bianchi | Tutto |

Giulia, la figlia, non ha un account.

## Comandi

L'elenco completo dei comandi, con le regole del codice, è in `AGENTS.md`. I più usati:

| Comando | Cosa fa |
| --- | --- |
| `./vendor/bin/sail test` | Test PHP |
| `./vendor/bin/sail npm run test` | Test del frontend |
| `./vendor/bin/sail npm run build` | Build del frontend |
| `./vendor/bin/sail artisan migrate:fresh --seed` | Rifà il database con la famiglia Bianchi |
| `./vendor/bin/sail artisan directus:copy --family="Leica"` | Copia una famiglia da Directus |

## Copia da Directus

`directus:copy --family="<nome>"` copia nell'app la famiglia che sta in Directus: persone, enti, schede, documenti con i loro file e collegamenti, promemoria e gli account delle persone (con l'email di Directus e una password nuova, da scegliere con *Password dimenticata*). Directus si legge in sola lettura: la connessione `directus` prende le variabili `DIRECTUS_DB_*` del `.env`, e la cartella dei file di Directus si monta in sola lettura da `DIRECTUS_UPLOADS_SOURCE` (di default `../family-manager-cms/uploads`). Se la famiglia c'è già si ferma; `--replace` la elimina con i suoi file e la rifà, `--without-files` non copia il contenuto dei file. Cosa copia e cosa no è nella nota *Copia da Directus* del vault (`02 - Documentazione tecnica/App Laravel/`).

## Documentazione

Analisi, piano e decisioni sono nel vault Obsidian del progetto, voci del ToDo *Nuova app per le famiglie in Laravel* e *Nuova app - base dell'app*. Le schermate approvate sono sulla tela di Claude Design *Family Manager - app per le famiglie*.
