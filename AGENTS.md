# Family Manager - app per le famiglie

Repository della nuova app di Family Manager, in Laravel + Inertia + Vue. Analisi, piano e decisioni stanno nel vault Obsidian, voci del ToDo *Nuova app per le famiglie in Laravel* e *Nuova app - base dell'app* (`MyVault/Projects/Family Manager/ToDo/_Opened/`): si leggono prima di lavorare qui. Le regole di `family-manager-cms` (GitNexus, `./fm`, migrazioni di Directus) non valgono qui. È un repository solo di questo progetto: `git add -A` va bene.

La [tela di Claude Design](https://claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr) è il riferimento delle pagine: dove il codice dice cose diverse dalla tavola vale la tavola, e la differenza si scrive nelle *Decisioni prese* del piano.

## Regole del progetto

- **Tutto in inglese, con la traduzione italiana** (regola del proprietario, 27/10/2026). Nomi di classi, metodi, variabili, props, campi dei dati, commenti, PHPDoc/TSDoc e nomi dei test sono in inglese; le rotte (indirizzi e nomi) solo in inglese.
- I testi che vede la persona si scrivono in inglese nel codice e si traducono: `__('…')` in PHP e Blade, `$t('…')`, `$tChoice('…')`, `trans()` e `transChoice()` (da `laravel-vue-i18n`) in Vue e TypeScript. La chiave è il testo inglese; l'italiano sta in `lang/it.json`, che leggono sia Laravel sia le pagine (`resources/js/lib/i18n.ts`); l'inglese non ha file. Le etichette degli enum stanno in `lang/en/enums.php` e `lang/it/enums.php` (il tratto `HasLabel`, chiave `enums.<enum>.<valore>`), perché in italiano cambiano con il genere. `tests/Feature/TranslationsTest.php` fallisce se un testo non ha la traduzione italiana o se un valore di un enum non ha l'etichetta in tutte e due le lingue.
- La lingua la sceglie il middleware `SetLocale` da `Accept-Language` del browser, tra `en` e `it`, altrimenti `APP_LOCALE` (`it` nel `.env`, `en` nei test). Il server la scrive in `<html lang>` e la passa alle pagine come prop condivisa `locale`; le email partono nella lingua di chi le manda (`Mail::to(…)->locale(…)`). Un testo con un nome o un numero dentro usa i segnaposto (`:name`, `:count`), non si compone a pezzi; i plurali con `$tChoice` (`{1} …|[2,*] …`). In `defineOptions` dei layout si usa `trans()`: `app.ts` prepara le traduzioni prima di caricare le pagine.
- Testi semplici, al tu, senza parole tecniche ("record", "collection", "relazione", "campo"). Importi come `84,20 €`, date come `15/10/2026` in tutte e due le lingue: `useFormat` nelle pagine, Carbon nel server. I dati mandano le date come `aaaa-mm-gg` (`expires_on`), le pagine le scrivono. Fuso orario `Europe/Rome`.
- Ogni tabella con dati di una famiglia ha `family_id` (obbligatorio, collegato a `families`, indice) e un modello con il tratto `BelongsToFamily`. Nessuna query sui dati di una famiglia passa fuori dal tratto, con due eccezioni: il comando di copia da Directus, che imposta la famiglia a mano, e `Invitation::findValid()`, che cerca un invito dal link quando chi lo apre non è ancora entrato (poi si lavora con `CurrentFamily::using()` nella famiglia dell'invito).
- I permessi passano dalle *policy*: `FamilyPolicy` vale per tutti i modelli della famiglia (vedere tutti, il resto solo `admin`), `DocumentPolicy` lascia caricare e modificare anche a un `member`. Le assegna `Gate::guessPolicyNamesUsing` in `AppServiceProvider`: un modello che ha bisogno di regole sue prende una *policy* `<Modello>Policy`.
- **Ogni modello nuovo della famiglia entra nel test di separazione** (`tests/Feature/Schema/AllModelsIsolationTest.php`): creato nella famiglia A, non si trova entrando dalla famiglia B.
- Un id di un'altra famiglia risponde 404, mai 403 né i dati.
- Nomi delle tabelle e dei campi come in Directus, con `_id` sui molti-a-uno, i campi di sistema di Laravel (`created_at`, `updated_at`, `created_by`, `updated_by`), `files` al posto di `directus_files`, `documentables` e `remindables` al posto delle tabelle ponte. I tipi polimorfici hanno il nome al singolare della tabella (`Relation::enforceMorphMap`).
- Due livelli di account: `admin` (*Tutto, come te*) e `member` (*Vede tutto e carica i documenti*). Un `member` vede tutto, carica e modifica documenti; non elimina niente e non modifica persone, enti, categorie, impostazioni e inviti. I controlli sono nelle *policy*, non solo nelle pagine.
- Form con FormKit, elenchi con `v-auto-animate`, icone di Lucide (`@lucide/vue`), componenti di `resources/js/components/fm/`.
- Account di prova (solo in locale e online di prova): quelli del seeder dei Bianchi, scritti nel `README.md`. Mai dati veri online.

## Come si scrive il codice

Regole del proprietario del 26/10/2026. Valgono per ogni riga, in PHP e in Vue.

**Test prima del codice (TDD), sempre.** Per ogni classe, azione, query, regola, componente Vue e composable: si scrive il test, lo si vede fallire per il motivo giusto, si scrive il codice minimo che lo fa passare, si ripulisce. Un test non controlla mai un *mock* al posto del comportamento vero.

**PHP e Laravel moderni.**
- PHP 8.4 o più nuovo, `declare(strict_types=1);` in ogni file.
- Proprietà `readonly` e promozione nel costruttore; `match` invece di catene di `if`; `final` sulle classi che non si estendono.
- `enum` con valori stringa per le scelte fisse (`App\Enums\…`), usati nei `casts()` dei modelli.
- `casts()` come metodo, *form request* per la validazione, *policy* per i permessi, azioni (`app/Actions/`) e service (`app/Services/`) per la logica, controller sottili. L'utente nei controller arriva con `#[CurrentUser] User $user`.
- Le pagine ricevono i dati da classi dedicate (`App\Data\…`, con `spatie/laravel-data`), che generano i tipi TypeScript: `sail artisan typescript:transform` scrive `resources/js/generated/generated.d.ts` (tipi globali come `App.Data.CategoryData`). Si rilancia dopo ogni modifica di una classe dati o di un enum, e il file si committa.
- Rector (`driftingly/rector-laravel`) tiene il codice aggiornato: `sail php vendor/bin/rector --dry-run` non propone modifiche.

**Tipi e PHPStan.**
- Larastan al livello 9 (`phpstan.neon`): il livello non si abbassa, niente *baseline* e niente `@phpstan-ignore` per il codice nuovo.
- Ogni parametro, proprietà e ritorno ha il suo tipo; gli array hanno la forma nel PHPDoc (`@param array{title: string, date: ?string} $data`, `@return Collection<int, Document>`).
- I metodi e le proprietà magici si dichiarano nel PHPDoc della classe: `@property` e `@property-read` dei modelli (colonne e relazioni), `@method` degli scope. Per i modelli li scrive `sail artisan ide-helper:models --write --reset`, da rilanciare dopo ogni migrazione.

**Commenti.**
- Ogni classe e ogni metodo, pubblico o privato, ha il PHPDoc: cosa fa, parametri, ritorno, eccezioni (`@throws`).
- Dentro il codice un commento spiega il perché di una logica che non si capisce leggendo, non ripete il cosa.
- I commenti sono in inglese, come il codice.

**Niente codice clonato.** Se una cosa serve due volte o più va in un posto solo: in PHP un service, un'azione, un tratto o una utility in `app/Support/`; in Vue un componente, un composable (`resources/js/composables/`) o una utility (`resources/js/lib/`).

**Librerie.** Una libreria PHP o JS che ottimizza o velocizza è benvenuta: si annota nelle *Decisioni prese* del piano con il motivo.

**Vue e TypeScript.**
- Solo TypeScript (`<script setup lang="ts">`), con `strict` in `tsconfig.json`. Niente `any`: la regola `typescript/no-explicit-any` di Oxlint è un errore.
- Props, emit e slot tipizzati (`defineProps<…>()`, `defineEmits<…>()`), ritorni delle funzioni dichiarati.
- TSDoc su ogni componente (in cima allo `<script>`: cosa mostra e quando si usa), su ogni prop ed emit, su ogni funzione e composable.
- TDD anche qui, con Vitest (quello di Vite+: `import { … } from 'vite-plus/test'`), Testing Library e `jsdom`: ogni componente in `components/fm/` ha il suo `*.test.ts` accanto, scritto prima; ogni composable e utility ha i suoi test; le pagine hanno un test di quello che fanno. Si controlla quello che vede e fa la persona (testi, ruoli, tasti), non i dettagli interni.
- Le classi di un campo FormKit si aggiungono a quelle di `global` in `formkit.config.ts`, non le sostituiscono: per cambiarne una si comincia con `$reset`.

## Comandi

Tutto gira con Sail (Docker), perché sul Mac PHP è più vecchio:

| Comando | Cosa fa |
| --- | --- |
| `./vendor/bin/sail up -d` | Avvia app, MySQL e Mailpit |
| `./vendor/bin/sail artisan …` | Artisan |
| `./vendor/bin/sail test` | Test PHP (Pest) |
| `./vendor/bin/sail npm run test` | Test del frontend (Vitest) |
| `./vendor/bin/sail php vendor/bin/phpstan analyse` | PHPStan al livello 9 |
| `./vendor/bin/sail php vendor/bin/rector --dry-run` | Rector, senza scrivere |
| `./vendor/bin/sail pint` | Formattazione PHP |
| `./vendor/bin/sail npm run build` | Build del frontend |
| `./vendor/bin/sail npm run types` | Controllo dei tipi (vue-tsc) |
| `./vendor/bin/sail npm run lint` | Oxlint |
| `./vendor/bin/sail npx vp check --fix` | Formattazione e lint del frontend |

Dopo ogni task, prima del commit: test PHP e Vitest verdi, PHPStan senza errori, Rector senza modifiche proposte, build, tipi e lint senza errori, Pint pulito.

- Lo starter kit usa Vite+ (`vp`): build, lint (Oxlint), formattazione (Oxfmt) e test (Vitest) sono suoi, configurati in `vite.config.ts`.
- npm: nel container di Sail c'è npm 11. npm 10.8 si fermava installando FormKit (`Cannot read properties of null (reading 'edgesOut')`): se serve npm fuori da Sail si usa `npx npm@11 install`.
- In locale l'app è su http://localhost:8090 e MySQL su `localhost:3308`, perché la 80 e la 3306 del Mac sono già occupate da altri progetti (`APP_PORT` e `FORWARD_DB_PORT` nel `.env`). Mailpit è su http://localhost:8025.
