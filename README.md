# Family Manager - app per le famiglie

La nuova app di Family Manager, pensata per genitori che non sono tecnici. Prenderà il posto dell'interfaccia di Directus del repository `family-manager`.

Oggi contiene solo **il prototipo** (`prototype/`): un'app cliccabile con i dati di una famiglia inventata, senza backend, da far provare ad alcuni genitori prima di scrivere l'applicazione vera in Laravel + Inertia + Vue.

## Avviare il prototipo

```bash
cd prototype
npm install
npm run dev
```

Vite stampa due indirizzi: `Local` per il computer, `Network` per il telefono collegato alla stessa rete Wi-Fi.

Ricaricare la pagina riporta tutto all'inizio: ogni prova parte dagli stessi dati.

## Comandi

| Comando | Cosa fa |
| --- | --- |
| `npm run dev` | Avvia il prototipo in locale |
| `npm test` | Lancia i test della logica (date, importi, scadenze, stato) |
| `npm run build` | Controlla i tipi e crea il sito statico in `dist/` |

## Strumenti

Vue 3, vue-router, Tailwind CSS 4, [FormKit](https://formkit.com) per i form, [AutoAnimate](https://auto-animate.formkit.com) per gli elenchi, [Tempo](https://tempo.formkit.com) per le date, Vitest per i test.

## Documentazione

Analisi, piano e decisioni sono nel vault Obsidian del progetto, voce del ToDo *Nuova app per le famiglie in Laravel*. Le schermate approvate sono sulla tela di Claude Design *Family Manager - app per le famiglie*.
