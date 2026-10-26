# Family Manager - app per le famiglie

La nuova app di Family Manager, pensata per genitori che non sono tecnici. Prenderà il posto dell'interfaccia di Directus del repository `family-manager-cms`, e si potrà vendere ad altre famiglie: ogni famiglia vede solo i suoi dati.

È fatta in Laravel 13 dallo starter kit per Vue (Inertia, Vue 3, TypeScript, Tailwind 4, Fortify per l'accesso, Wayfinder per le rotte), e gira in Docker con Laravel Sail. Il prototipo della tappa 1 non c'è più: si recupera dalla storia di git, commit `54a6702` (`git show 54a6702:prototype/…`).

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

## Documentazione

Analisi, piano e decisioni sono nel vault Obsidian del progetto, voci del ToDo *Nuova app per le famiglie in Laravel* e *Nuova app - base dell'app*. Le schermate approvate sono sulla tela di Claude Design *Family Manager - app per le famiglie*.
