# Creare una famiglia dal pannello - Piano

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** dal pannello del proprietario si crea una famiglia con il suo primo account `admin`, che riceve un'email di benvenuto con un link di 7 giorni per scegliere la password.

**Architecture:** un'azione `CreateFamilyFromPanel` usa `CreateFamily` (password casuale), scrive il registro e manda `WelcomeMail`. Il link è una rotta firmata dell'app (`welcome/{user}`) che conferma l'email, crea un token normale della password e porta alla pagina `password.reset` che c'è già. Nel pannello: azione *Crea una famiglia* nella lista e *Manda di nuovo l'email di benvenuto* nella scheda *Account*.

**Tech Stack:** Laravel 12, Filament 5, Fortify, Pest 4.

**Spec:** `docs/superpowers/specs/2026-11-19-create-family-from-panel-design.md`

## Global Constraints

- Tutte le regole di `AGENTS.md`: `declare(strict_types=1)`, classi `final`, PHPDoc su ogni classe e metodo, codice e commenti in inglese, testi con `__()` e la traduzione in `lang/it.json` (righe aggiunte a mano prima della `}` finale, mai riscritto con un parser), PHPStan livello 9 senza ignore, Rector e Pint puliti, TDD.
- «Fameja.app» dal segnaposto `:app` (`config('app.name')`), mai scritto a mano.
- Il link vale **7 giorni**; la password dimenticata resta di 60 minuti.
- Pest si lancia con un database suo: `docker exec -u sail -e DB_DATABASE=testing_newfamily family-manager-app-laravel.test-1 php artisan test <file>` (scritto per esteso). Un Pest alla volta.
- Commit con i percorsi (`git commit -- <file>…`): altre sessioni lavorano nel repository (in questo momento hanno aperti `FmMovementPanel.*`).
- Nomi delle funzioni di aiuto dei test Pest unici in tutta `tests/` (prefisso del file).

## Review Focus

1. **Lingua delle categorie**: `CreateFamily` scrive i nomi delle categorie con `__()` nella lingua corrente, che nel pannello è quella del browser del proprietario. La famiglia deve nascere con le categorie nella lingua scelta nel modulo. Test nel Task 3.
2. **Host del link**: il pannello sta su `admin.fameja.app`; `route()` durante una richiesta del pannello scriverebbe l'host del pannello, e lì `SeparateHosts` risponde 404. Il link si costruisce sull'host di `APP_URL`. Test nel Task 1.
3. **Link riusato**: dopo la prima apertura (email confermata) lo stesso link non crea più token della password. Test nel Task 1.
4. **Email con maiuscole o già usata**: `Laura@Example.com` si salva minuscola, e un'email già di un account (anche scritta con le maiuscole) viene rifiutata dal modulo. Test nei Task 3 e 4.
5. **Manda di nuovo su un account confermato**: l'azione non compare, e il link vecchio non riapre niente. Test nel Task 4.

---

### Task 0: L'email di benvenuto nella tela (lo fa chi coordina, con un fork)

**Files:** tela `claude.ai/artifact/XwCgtJdtBEgWrGDbLAmRrr`, tavole delle email (*Le email*: elenco con `Artifact list`, `scope: "files"`).

- [ ] **Step 1:** scaricare la tavola dell'email *Scegli una nuova password* e quella dell'invito; un fork disegna accanto a loro *Benvenuto*: icona `party-popper` (si aggiunge a `resources/design/mail-icons.json` nel Task 2), «Ciao Laura», «Ti diamo il benvenuto in Fameja.app: la famiglia Rossi è pronta. Scegli la tua password per entrare.», tasto *Scegli la tua password*, riquadro «Il link vale 7 giorni. Non te l'aspettavi? Ignora questa email.», piè di pagina con l'indirizzo da copiare. Colori con `var(--…)`.
- [ ] **Step 2:** pubblicare la tela (con l'indirizzo della tela) e salvare la tavola in `.superpowers/sdd/create-family/boards/`, da dove la legge il Task 2.

### Task 1: Il link di benvenuto

**Files:**
- Create: `app/Support/AppHostUrl.php`, `app/Support/WelcomeLink.php`, `app/Http/Controllers/WelcomeLinkController.php`, `tests/Feature/Admin/WelcomeLinkTest.php`
- Modify: `app/Support/VisitHandoff.php` (usa `AppHostUrl`, togliendo `onAppHost()`), `routes/web.php` (nel gruppo `guest` accanto all'invito), `lang/it.json`

**Interfaces:**
- Produces: `AppHostUrl::generator(): Illuminate\Routing\UrlGenerator` (il corpo di `VisitHandoff::onAppHost()`, spostato); `WelcomeLink::DAYS = 7`; `WelcomeLink::for(User $user): string` (URL firmato a tempo della rotta `welcome`, `['user' => $user->id]`, scadenza `now()->addDays(self::DAYS)`, costruito con `AppHostUrl::generator()`).
- Rotta: `GET welcome/{user}` → `WelcomeLinkController`, nome `welcome`, middleware `signed` e `throttle:6,1`, nel gruppo `guest`.

- [ ] **Step 1: test che falliscono** in `WelcomeLinkTest.php`:
  - `the link is on the host of the app`: con `config(['app.url' => 'https://my.fameja.app'])` e la richiesta corrente su `admin.fameja.app`, `WelcomeLink::for($user)` comincia con `https://my.fameja.app/welcome/`.
  - `opening the link confirms the email and opens the choice of the password`: account con `email_verified_at` null; `get(WelcomeLink::for($user))` fa redirect a un URL che comincia con `route('password.reset', ['token' => ''])` e contiene `email=laura%40example.com`; `email_verified_at` non è più null; `Password::broker()->tokenExists($user, <token del redirect>)` è vero.
  - `the token of the link lasts like a forgotten password`: nessun cambio a `config('auth.passwords.users.expire')` (resta 60).
  - `a used link goes to the sign in with a message`: account già confermato; il link porta a `route('login')` con `session('status')` = `'This link was already used. To sign in, use Forgot password.'`, e nessuna riga in `password_reset_tokens`.
  - `an expired or forged link answers 403`: `travel(8)->days()` prima di aprirlo → 403; URL con la firma cambiata → 403.
  - `the password page then sets the password`: dopo il redirect, `post(route('password.update'), [token, email, password, password_confirmation])` e `Hash::check` sulla password nuova.
  - `VisitTest` continua a passare (refactor di `VisitHandoff`).
- [ ] **Step 2:** lanciarli e vederli fallire (rotta `welcome` che non esiste).
- [ ] **Step 3:** scrivere `AppHostUrl`, `WelcomeLink`, il controller (`__invoke(User $user): RedirectResponse`: se `email_verified_at` non è null → `redirect()->route('login')->with('status', __('…'))`; altrimenti `forceFill(['email_verified_at' => now()])->save()`, `Password::broker()->createToken($user)` e redirect a `route('password.reset', ['token' => $token, 'email' => $user->email])`), la rotta e la traduzione: «Questo link è già stato usato. Per entrare usa *Password dimenticata*.» (senza asterischi nel testo).
- [ ] **Step 4:** test verdi, poi `VisitTest`, PHPStan, Rector, Pint sui file toccati.
- [ ] **Step 5:** commit «Welcome link: a signed link of 7 days that opens the choice of the password».

### Task 2: L'email di benvenuto

**Files:**
- Create: `app/Mail/WelcomeMail.php`, `resources/views/mail/welcome.blade.php`, `tests/Feature/Mail/WelcomeMailTest.php`
- Modify: `resources/design/mail-icons.json` (+ `party-popper`, poi `npm run mail-icons` nel container), `public/mail-icons/` (il PNG nuovo), `lang/it.json`, `tests/Feature/Mail/AppMailsTest.php` se elenca le email

**Interfaces:**
- Consumes: `WelcomeLink::for(User)`, `WelcomeLink::DAYS`.
- Produces: `new WelcomeMail(User $user)` (Mailable, `Queueable`, `SerializesModels`, come `InvitationMail`). Oggetto: `__('Welcome to :app', ['app' => config('app.name')])`.

- [ ] **Step 1: test che falliscono**: l'oggetto «Welcome to Fameja.app»; il testo ha «Hi Laura», il nome della famiglia («Rossi»), il tasto «Choose your password» con l'indirizzo che comincia con `config('app.url').'/welcome/'`, «The link is valid for 7 days.»; con `->locale('it')` l'oggetto è «Ti diamo il benvenuto in Fameja.app» e il tasto «Scegli la tua password».
- [ ] **Step 2:** vederli fallire.
- [ ] **Step 3:** scrivere mail e vista seguendo la tavola del Task 0 (`.superpowers/sdd/create-family/boards/`) e la forma di `mail/reset-password.blade.php` (`x-mail.icon`, `x-mail::button`, `x-mail::panel`, `subcopy`, `why` se le altre lo usano); durata con `trans_choice('{1} The link is valid for one day.|[2,*] The link is valid for :count days.', WelcomeLink::DAYS)`; traduzioni.
- [ ] **Step 4:** test verdi, `AppMailsTest` e `TranslationsTest` verdi; mandare una mail vera su Mailpit da tinker (`DB_DATABASE=testing_newfamily_dev`) e guardarla su `http://localhost:8026` accanto alla tavola.
- [ ] **Step 5:** commit «Welcome mail: the link to choose the password of a new family».

### Task 3: L'azione che crea la famiglia

**Files:**
- Create: `app/Actions/Admin/CreateFamilyFromPanel.php`, `tests/Feature/Admin/CreateFamilyFromPanelTest.php`
- Modify: `app/Enums/OwnerActionType.php` (+ `case FamilyCreated = 'family_created';`), `lang/en/enums.php` («Family created»), `lang/it/enums.php` («Famiglia creata»)

**Interfaces:**
- Consumes: `CreateFamily::handle(string $familyName, string $firstName, string $lastName, string $email, string $password): User`, `LogOwnerAction::handle(OwnerActionType, Family)`, `WelcomeMail`.
- Produces: `CreateFamilyFromPanel::handle(string $familyName, string $firstName, string $lastName, string $email, string $locale): User` (`$locale` è `'it'` o `'en'`).

- [ ] **Step 1: test che falliscono** (con `Mail::fake()` e il proprietario entrato con la guardia `owner`):
  - `it creates the family with its admin account`: famiglia «Rossi», account `admin` «Laura Rossi», persona collegata, `email_verified_at` null.
  - `nobody knows the password`: `Hash::check('', …)` falso e la password non è uguale a nessun dato del modulo (si controlla che `password` sia un hash lungo).
  - `the email is saved in lowercase`: `Laura@Example.com` → `laura@example.com`.
  - `the categories are in the language chosen`: con `app()->setLocale('en')` e `$locale = 'it'`, dentro la famiglia c'è la categoria di spesa con il nome italiano di una delle `DefaultCategories::expenses()`; e viceversa. Dopo l'azione `app()->getLocale()` è di nuovo `'en'`.
  - `it writes the log`: una riga `OwnerAction` con `FamilyCreated`, la famiglia e il proprietario.
  - `it sends the welcome mail in the language chosen`: `Mail::assertSent(WelcomeMail::class, fn ($m) => $m->hasTo('laura@example.com') && $m->locale === 'it')`.
  - `TranslationsTest` vede le etichette nuove dell'enum.
- [ ] **Step 2:** vederli fallire.
- [ ] **Step 3:** scrivere l'azione: dentro `App::withLocale($locale, …)` chiama `CreateFamily` con `Str::lower($email)` e `Str::password(64)`, poi `LogOwnerAction`; fuori manda `Mail::to($user)->locale($locale)->send(new WelcomeMail($user))`. Tutto tranne la mail in una `DB::transaction` (`CreateFamily` ne apre già una: va bene annidata).
- [ ] **Step 4:** test verdi, PHPStan, Rector, Pint.
- [ ] **Step 5:** commit «Owner panel: an action that creates a family with its first account».

### Task 4: Il pannello

**Files:**
- Modify: `app/Filament/Resources/Families/Pages/ListFamilies.php` (`getHeaderActions()`), `app/Filament/Resources/Families/RelationManagers/UsersRelationManager.php` (azione per riga), `app/Filament/Resources/Families/FamilyResource.php` (solo il commento di `canCreate()`, che resta `false`: si crea con l'azione, non con una pagina di Filament), `lang/it.json`
- Test: `tests/Feature/Admin/FamiliesTest.php` (o un file nuovo `CreateFamilyPanelTest.php`)

**Interfaces:**
- Consumes: `CreateFamilyFromPanel::handle(…)`, `WelcomeLink`, `WelcomeMail`.
- Produces: azione `createFamily` su `ListFamilies`; azione `resendWelcome` su `UsersRelationManager`.

- [ ] **Step 1: test che falliscono** (Livewire, come `FamiliesTest`):
  - `the owner creates a family from the list`: `Livewire::test(ListFamilies::class)->callAction('createFamily', ['first_name' => 'Laura', 'last_name' => 'Rossi', 'family_name' => 'Rossi', 'email' => 'laura@example.com', 'locale' => 'it'])` → nessun errore, la famiglia c'è, redirect alla pagina `view` della famiglia nuova.
  - `the family name starts as the last name`: dopo `fillForm(['last_name' => 'Rossi'])` il campo `family_name` vale «Rossi» (finché non lo si cambia a mano).
  - `an email already used is refused`: account esistente `laura@example.com`; `callAction` con `LAURA@example.com` → `assertHasActionErrors(['email'])`, nessuna famiglia nuova.
  - campi obbligatori: nome, cognome, nome della famiglia, email (formato email), lingua (`it`/`en`, predefinita `it`).
  - `the welcome mail can be sent again`: `Livewire::test(UsersRelationManager::class, ['ownerRecord' => $family, 'pageClass' => ViewFamily::class])->callTableAction('resendWelcome', $user)` → `Mail::assertSent(WelcomeMail::class)` nella lingua del pannello.
  - `not for a confirmed account`: `assertTableActionHidden('resendWelcome', $confirmedUser)`.
- [ ] **Step 2:** vederli fallire.
- [ ] **Step 3:** scrivere le azioni. *Crea una famiglia* (`heroicon-o-plus`): `TextInput` per nome, cognome (`live(onBlur: true)` che riempie `family_name` se l'utente non l'ha toccato), nome della famiglia, email (`email()`, `unique(User::class, 'email')` sul valore minuscolo), `ToggleButtons` per la lingua (*Italiano*/*English*); `action` chiama `CreateFamilyFromPanel` e fa `redirect(FamilyResource::getUrl('view', ['record' => $user->family_id]))`, con la notifica «Family created. We sent :email the link to choose the password.». *Manda di nuovo l'email di benvenuto* (`heroicon-o-envelope`, `requiresConfirmation`, visibile se `email_verified_at` è null): `Mail::to($record)->locale(app()->getLocale())->send(new WelcomeMail($record))` e notifica «Sent.». Testi tradotti.
- [ ] **Step 4:** test verdi, poi tutto `tests/Feature/Admin/` (uno alla volta, un database suo), PHPStan, Rector, Pint.
- [ ] **Step 5:** prova nel browser con Playwright sul pannello locale (`/admin`, account del proprietario, in locale senza TOTP): crea una famiglia, apri l'email su Mailpit, apri il link, scegli la password, entra. Foto del modulo e della pagina della password.
- [ ] **Step 6:** commit «Owner panel: create a family, and send the welcome mail again».

### Task 5: Documentazione

**Files:** `AGENTS.md`, `.claude/learnings.md` (solo se si è scoperto qualcosa), vault con la skill `project-docs`.

- [ ] **Step 1:** in `AGENTS.md`, nel passo 1 del passaggio («il pannello non crea famiglie») e nella voce del pannello del proprietario: il pannello crea una famiglia con il primo account e manda l'email di benvenuto (link di 7 giorni, `WelcomeLink`).
- [ ] **Step 2:** `project-docs`: pannello del proprietario nella documentazione funzionale e tecnica (azione, link, email, registro).
- [ ] **Step 3:** commit «Docs: creating a family from the owner panel».
