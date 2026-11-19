# La mail della settimana

Data: 19/11/2026. Stato: da approvare (dopo la lettura del revisore).

## Perché

Il proprietario vuole sapere **cosa c'è da pagare in settimana** senza aprire l'Agenda. Ogni lunedì mattina gli admin della famiglia ricevono una mail con le spese e i promemoria dei sette giorni, come il blocco *Prossima settimana* / *Questa settimana* dell'Agenda. Visto che fa già il riepilogo, il lunedì sostituisce la mail del mattino per gli admin.

## Obiettivi

- Il lunedì, nel giro del mattino (`reminders:run`, alle 06:00 `Europe/Rome`), ogni account `admin` della famiglia riceve **una sola mail**: la mail della settimana. La ricevono **tutti** gli admin, anche chi ha le email degli avvisi spente in *Impostazioni › Avvisi* (decisione del proprietario): è una mail a parte, con un piè di pagina suo che dice perché arriva (è admin della famiglia), non quello della mail del mattino.
- La mail contiene, in quest'ordine:
  1. **Il totale da pagare** (decisione del proprietario): la somma degli importi delle voci da pagare da lunedì a domenica **più** quelle in ritardo non pagate, cioè quanti soldi servono adesso. Lo stesso numero va nell'oggetto. Le voci con l'importo stimato contano e il totale lo dice, come fa l'Agenda con `estimated`.
  2. **In ritardo** (in rosso): come il blocco *In ritardo* dell'Agenda, cioè tutte le voci passate e ancora aperte: spese non pagate, scadenze di schede e documenti (fino a 90 giorni indietro, `AgendaCalendar::DAYS_BACK`) e cose da fare aperte. Le righe con un importo hanno l'importo.
  3. **Da pagare in settimana**: le voci con un importo da lunedì a domenica, cioè le spese fisse non pagate e le scadenze di schede e documenti con un importo. Ogni riga ha giorno (`23 lun`), titolo, sottotitolo, chi la paga e l'importo, come la riga dell'Agenda, e un link alla sua pagina.
  4. **Promemoria e cose da fare** della settimana: le voci senza importo (scadenze senza importo, promemoria aperti, manuali e automatici), con giorno e titolo.
  5. **Avvisi di oggi**: gli avvisi del mattino che quell'admin avrebbe ricevuto **per email** quel lunedì (`Delivery->email`, cioè `$emails[$userId]` di `SendMorningNotices`) e che non sono già nelle sezioni di sopra (per esempio «la polizza scade fra 30 giorni»). Così non si perde niente; un admin con le email spente non ha questa sezione.
- Un link all'Agenda in fondo, come la mail del mattino.
- La campanella non cambia: il lunedì gli avvisi si scrivono come ogni giorno.

## Non obiettivi

- Niente entrate: non sono cose da pagare.
- Nessuna impostazione per scegliere giorno, orario o chi la riceve, e nessun modo per non riceverla (per ora).
- I `member` non la ricevono: il lunedì hanno la loro mail del mattino come sempre.
- Nessuna pagina nuova nell'app.

## Anti-obiettivi

- Un admin che il lunedì riceve **due** mail (quella della settimana e quella del mattino).
- Un avviso del mattino che il lunedì sparisce perché la mail della settimana l'ha preso al suo posto e non l'ha mostrato.
- Una voce che compare due volte (una scadenza pagata da una spesa fissa lo stesso giorno, o una scadenza con il suo promemoria automatico) o che nella mail c'è e nell'Agenda no, e viceversa.
- Una spesa già pagata o saltata (*Non si paga*) nella mail.

## Vincoli

- Le voci vengono da dove le prende l'Agenda (`AgendaCalendar::open()`: le voci senza entrate e senza scadenze fatte, più `AgendaTasks::open()`), mai da una query nuova: mail e Agenda devono dire la stessa cosa. «Con un importo» vuol dire `amount !== null` (le scadenze di schede e documenti non lo hanno; ce l'hanno i pagamenti, anche quelli uniti a una scadenza).
- Tutto dentro `CurrentFamily::using()` del giro, come oggi; nessuna query fuori dal tratto `BelongsToFamily`.
- La mail parte nella lingua dell'account (`Mail::to(…)->locale(…)`), con i testi in inglese e la traduzione in `lang/it.json`; importi e date con Carbon (`84,20 €`, `23 lun`).
- La mail si disegna con il tema `family-manager` in `resources/views/vendor/mail` (colori del design system in esadecimale, niente proprietà che i programmi di posta non leggono: `AppMailsTest`).
- **Prima la tela**: una tavola nuova `Email-settimana` nella pagina delle email della tela, accanto a `Email-mattino` e partendo da lei; poi il codice la segue.
- Il giro resta idempotente: rilanciarlo lo stesso lunedì (per esempio dopo un riavvio del contenitore, `entrypoint.d`) non deve mandare la mail della settimana una seconda volta, come oggi non manda due volte gli avvisi.

## Strade considerate

1. **Dentro il giro del mattino** *(scelta del proprietario)*: `reminders:run` raccoglie già gli avvisi per account; il lunedì, a un admin, invece della mail del mattino si manda la mail della settimana, con gli avvisi del giorno dentro. Un solo giro, quindi le due mail non possono partire insieme per costruzione, e si prova con `--date`.
2. Un comando a parte (`weekly:summary`) pianificato il lunedì, con il giro del mattino che salta gli admin il lunedì: più separato, ma i due comandi dipendono l'uno dall'altro e se il riepilogo fallisce l'admin quel lunedì non riceve niente.
3. La mail la domenica sera: scartata, il proprietario ha chiesto «a inizio settimana».

## Decisioni

- Dal proprietario: scopo «sapere cosa pagare in settimana»; la ricevono tutti gli `admin`, anche con le email degli avvisi spente; il lunedì sostituisce la mail del mattino; strada 1; il totale comprende settimana e ritardo.
- Mie proposte, accettate nel messaggio delle strade: totale in cima, *In ritardo* in rosso, promemoria senza importo in una sezione loro, niente entrate, *Avvisi di oggi* in fondo, `member` invariati.
- Mia scelta: la mail mostra le voci di tutta la famiglia, uguali per ogni admin (tranne *Avvisi di oggi*), senza i filtri salvati dell'Agenda di ciascuno (persona, aree nascoste): quelli servono a guardare l'Agenda, non a sapere cosa pagare.
- Mia scelta: la settimana è da lunedì (il giorno del giro) a domenica compresa.
- Mia scelta: se un admin non ha niente in nessuna sezione, la mail non parte (come oggi la mail del mattino senza avvisi).
- Mia scelta: la mail della settimana parte solo quando il giorno del giro è un lunedì (anche con `--date`), così si prova con `reminders:run --date=2026-11-23`.

## Lasciato a chi costruisce

- Dove vive la raccolta della settimana (un servizio, per esempio `App\Services\Reminders\WeeklySummary`, che restituisce le sezioni già pronte) e come `SendMorningNotices` passa all'admin la mail nuova al posto di `MorningNoticesMail`.
- Come si evita la seconda mail a un rilancio dello stesso lunedì (per esempio una chiave in cache per famiglia e giorno, o una colonna; con la cache condivisa di produzione).
- Come si riconosce che un avviso del mattino è già nelle sezioni (stesso promemoria / stessa scadenza e stesso giorno).
- L'oggetto della mail (per esempio «Questa settimana: 128,49 € da pagare»).
