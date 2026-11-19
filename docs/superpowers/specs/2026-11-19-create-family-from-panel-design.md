# Creare una famiglia dal pannello del proprietario

19/11/2026. Piccolo cambio.

## Perché

La registrazione è chiusa (`REGISTRATION_OPEN=false`) e il pannello non crea famiglie (`FamilyResource::canCreate()` è `false`): oggi una famiglia nuova non ha modo di entrare. Il proprietario deve far entrare una famiglia nuova, quella dei suoi genitori, che entreranno con un account loro.

## Obiettivi

- Dal pannello (*Famiglie* → *Crea*) il proprietario crea una famiglia con il suo primo account `admin`.
- La persona riceve un'email di benvenuto con un link per scegliere la password. Il proprietario non vede né manda mai la password.

## Fuori da questo lavoro

- **Un account in più famiglie** (il proprietario `admin` anche nella famiglia dei genitori): è un progetto a parte, da analizzare dopo. Quando ci sarà, saranno i genitori a invitare il proprietario. Qui un account resta in una sola famiglia, e l'email deve essere libera.
- Aggiungere account a una famiglia che c'è già: resta il compito degli inviti.

## Approcci per il link della password (scelto: 1)

1. **Link di benvenuto firmato, che porta alla pagina della password che c'è già** (scelto). `GET welcome/{user}`, firmato a tempo (7 giorni). Quando lo si apre, se l'email dell'account non è confermata e è quella a cui è partito il link (`hash`): crea un token normale della password (`Password::broker()->createToken`) e porta a `password.reset` con token ed email, senza cambiare altro; l'email si conferma quando la password si salva (cambiato dopo la revisione finale: prima la confermava l'apertura, che un programma che controlla i link avrebbe consumato, e un'email cambiata riapriva un link vecchio). Se è già confermata o cambiata, il link non vale più: va alla pagina di accesso con un messaggio (si usa *Password dimenticata*). Nessuna pagina nuova, e la password dimenticata resta di 60 minuti.
2. Un secondo tipo di token della password da 7 giorni: andrebbe riscritta o duplicata la pagina di reset di Fortify. Scartato.
3. Portare a 7 giorni tutti i link della password: rende meno sicura la password dimenticata. Scartato.

## Come funziona

- **Modulo del pannello** (azione *Crea* della lista *Famiglie*): nome, cognome, email, nome della famiglia (riempito con il cognome, si può cambiare), lingua dell'email (`it`/`en`, predefinita `it`). L'email non deve appartenere a nessun account (stessa regola della registrazione). Non ha una tavola nella tela: è Filament.
- **Creazione**: un'azione `App\Actions\Admin\CreateFamilyFromPanel` chiama `CreateFamily` (che oggi vuole una password: la si crea casuale e lunga, che nessuno conosce), scrive *Famiglia creata* nel registro (`OwnerActionType::FamilyCreated`, con le etichette in tutte e due le lingue) e manda l'email di benvenuto nella lingua scelta. Il pannello apre la pagina della famiglia nuova.
- **Email di benvenuto** (`WelcomeMail`, con il tema delle email): saluto con il nome, «ti diamo il benvenuto in Fameja.app» con il nome della famiglia, tasto *Scegli la tua password* verso il link firmato, «il link vale 7 giorni». Si disegna prima nella tela, accanto alle altre email (tavole *Le email*), poi si scrive.
- **Manda di nuovo**: nella scheda *Account* della famiglia, un'azione *Manda di nuovo l'email di benvenuto* sull'account creato dal pannello, finché l'email non è confermata. Manda un link nuovo da 7 giorni, nella lingua dell'account (cambiato dopo la consegna: prima era la lingua del pannello, per qualunque account da confermare). Se l'invio fallisce, qui o alla creazione, il pannello lo dice e la famiglia resta.
- **Rotta del link**: tra le rotte dell'app (host `APP_URL`), senza accesso, `signed` e `throttle`, fuori dalle rotte che il telefono collegato può aprire. Un link con la firma sbagliata o scaduto risponde 403 come gli altri link firmati.

## Lasciato a chi costruisce

- I testi esatti in inglese, con la traduzione in `lang/it.json`.
- Dove sta la durata (una costante, come `Invitation::DAYS`).
- I test: azione (famiglia, account, persona, categorie, registro, email nella lingua scelta), modulo di Filament (campi, email già usata), rotta del link (conferma e porta alla pagina della password; link usato, scaduto o falso), *Manda di nuovo* (solo per un'email non confermata), `AllModelsIsolationTest` non cambia (nessun modello nuovo), `TranslationsTest`.
