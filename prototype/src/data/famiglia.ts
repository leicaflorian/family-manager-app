import { format } from '@formkit/tempo'
import { fineMese, giorniTra, spostaGiorni } from '../logic/formato'
import { stipendiPassati } from '../logic/stipendio'
import type { Categoria, Documento, Famiglia, Scadenza, Scheda } from './tipi'

export { CATEGORIE } from './tipi'

/**
 * La famiglia Bianchi, inventata. Le date sono calcolate da `oggi`, così il
 * prototipo resta credibile qualunque giorno si faccia la prova.
 */
export function creaFamiglia(oggi: Date): Famiglia {
  const g = (giorni: number) => spostaGiorni(oggi, giorni)
  const fine = fineMese(oggi)
  // Due bollette cadono sempre entro fine mese, anche se la prova è l'ultimo giorno
  const gas = g(Math.min(4, giorniTra(oggi, fine)))

  const schede: Scheda[] = [
    { id: 'casa', area: 'casa', titolo: 'Casa di via Roma', sottotitolo: 'Bologna · di proprietà', manca: [], collegate: ['luce', 'gas', 'acqua', 'mutuo'] },
    { id: 'luce', area: 'casa', titolo: 'Luce - Edison', sottotitolo: 'Casa di via Roma', manca: [] },
    { id: 'gas', area: 'casa', titolo: 'Gas - Enel', sottotitolo: 'Casa di via Roma', manca: [] },
    { id: 'acqua', area: 'casa', titolo: 'Acqua - Hera', sottotitolo: 'Casa di via Roma', manca: [] },
    { id: 'panda', area: 'veicoli', titolo: 'Fiat Panda', sottotitolo: 'Targa GH 482 TK · di Marco', manca: ['Libretto di circolazione'], collegate: ['rc-auto'] },
    { id: 'rc-auto', area: 'assicurazioni', titolo: 'RC auto Panda - UnipolSai', sottotitolo: 'Fiat Panda', manca: [] },
    { id: 'mutuo', area: 'banche', titolo: 'Mutuo casa', sottotitolo: 'BPER · fino al 2041', manca: [] },
    { id: 'conto', area: 'banche', titolo: 'Conto BPER', sottotitolo: 'Marco e Laura', manca: [] },
    { id: 'pediatra', area: 'salute', titolo: 'Visite di Giulia', sottotitolo: 'Dott.ssa Neri, pediatra', manca: [] },
    { id: 'sport', area: 'salute', titolo: 'Certificato sportivo di Giulia', sottotitolo: 'Per la danza', manca: [] },
    { id: 'dentista', area: 'salute', titolo: 'Dentista di Laura', sottotitolo: 'Studio Ferri', manca: [] },
    { id: 'danza', area: 'scuola', titolo: 'Danza Giulia', sottotitolo: 'Scuola Passo a Due · 2026/27', manca: [] },
    { id: 'lavoro-marco', area: 'lavoro', titolo: 'Marco - Studio Rinaldi', sottotitolo: 'Impiegato', manca: [], stipendio: { netto: 1850, giorno: 27 } },
    { id: 'lavoro-laura', area: 'lavoro', titolo: 'Laura - Comune di Bologna', sottotitolo: 'Insegnante', manca: [], stipendio: { netto: 1720, giorno: 23 } },
    { id: 'internet', area: 'abbonamenti', titolo: 'Internet - Fastweb', sottotitolo: 'Ogni mese', manca: [] },
    { id: 'netflix', area: 'abbonamenti', titolo: 'Netflix', sottotitolo: 'Ogni mese', manca: [] },
  ]

  const scadenza = (id: string, schedaId: string, titolo: string, importo: number | null, data: Date): Scadenza => ({
    id,
    schedaId,
    titolo,
    importo,
    data,
    pagata: null,
  })
  const scadenze: Scadenza[] = [
    scadenza('s-luce', 'luce', 'Bolletta luce', 71.3, g(-3)),
    scadenza('s-gas', 'gas', 'Bolletta gas', 84.2, gas),
    scadenza('s-acqua', 'acqua', 'Bolletta acqua', 37.9, fine),
    scadenza('s-netflix', 'netflix', 'Netflix', 13.99, g(9)),
    scadenza('s-mutuo', 'mutuo', 'Rata mutuo', 612, g(20)),
    scadenza('s-rc', 'rc-auto', 'Rata RC auto', 418, g(26)),
    scadenza('s-danza', 'danza', 'Retta danza', 45, g(40)),
    scadenza('s-revisione', 'panda', 'Revisione Panda', null, g(75)),
  ]

  const documento = (
    id: string,
    titolo: string,
    schedaId: string | null,
    personaId: string | null,
    categoria: Categoria,
    ente: string | null,
    data: Date,
    scadenza: Date | null = null,
  ): Documento => ({ id, titolo, schedaId, personaId, categoria, ente, data, scadenza })
  const documenti: Documento[] = [
    documento('d-ci-giulia', "Carta d'identità", null, 'giulia', "Documenti d'identità", 'Comune di Bologna', g(-3440), g(210)),
    documento('d-patente-marco', 'Patente', null, 'marco', "Documenti d'identità", null, g(-2700), g(900)),
    documento('d-certificato', 'Certificato medico sportivo', 'sport', 'giulia', 'Salute', 'Dott.ssa Neri', g(-319), g(46)),
    documento('d-danza', 'Iscrizione danza 2026/27', 'danza', 'giulia', 'Scuola e attività', 'Scuola Passo a Due', g(-43)),
    documento('d-polizza', 'Polizza RC auto 2026', 'rc-auto', 'marco', 'Polizze', 'UnipolSai', g(-340)),
    documento('d-revisione', 'Ultima revisione', 'panda', null, 'Veicoli', null, g(-290)),
    documento('d-bollo', 'Bollo 2026', 'panda', null, 'Tasse', null, g(-267)),
    documento('d-gas-settembre', 'Bolletta gas di settembre', 'gas', null, 'Bollette e fatture', 'Enel', g(-1), gas),
    documento('d-fastweb-contratto', 'Contratto Fastweb', 'internet', null, 'Contratti', 'Fastweb', g(-500)),
    documento('d-fastweb-ottobre', 'Fattura Fastweb', 'internet', null, 'Bollette e fatture', 'Fastweb', g(-1)),
    documento('d-cu-marco', 'Certificazione Unica 2026', 'lavoro-marco', 'marco', 'Lavoro e redditi', 'Studio Rinaldi', g(-220)),
    documento('d-cu-laura', 'Certificazione Unica 2026', 'lavoro-laura', 'laura', 'Lavoro e redditi', 'Comune di Bologna', g(-220)),
  ]
  // Le ultime tre buste paga, arrivate con lo stipendio
  for (const s of schede) {
    if (!s.stipendio) continue
    const personaId = s.id.replace('lavoro-', '')
    // Il titolo è «Persona - Datore di lavoro»
    const datore = s.titolo.split(' - ')[1] ?? null
    for (const data of stipendiPassati(s.stipendio.giorno, oggi, 3)) {
      const mese = format(data, 'MMMM', 'it')
      documenti.push(
        documento(`d-busta-${personaId}-${format(data, 'YYYY-MM', 'it')}`, `Busta paga di ${mese}`, s.id, personaId, 'Lavoro e redditi', datore, data),
      )
    }
  }

  return {
    nome: 'Bianchi',
    persone: [
      { id: 'marco', nome: 'Marco', ruolo: 'genitore' },
      { id: 'laura', nome: 'Laura', ruolo: 'genitore' },
      { id: 'giulia', nome: 'Giulia', ruolo: 'figlio' },
    ],
    enti: [
      'Edison', 'Enel', 'Hera', 'Fastweb', 'UnipolSai', 'BPER', 'Netflix',
      'Scuola Passo a Due', 'Studio Rinaldi', 'Comune di Bologna', 'Dott.ssa Neri', 'Studio Ferri',
    ],
    schede,
    scadenze,
    documenti,
    posta: [
      { id: 'p-enel', mittente: 'Enel Energia', oggetto: 'La tua bolletta', arrivata: g(-1), documentoId: 'd-gas-settembre' },
      { id: 'p-fastweb', mittente: 'Fastweb', oggetto: 'Fattura disponibile', arrivata: g(-1), documentoId: 'd-fastweb-ottobre' },
    ],
    conti: ['Conto BPER', 'Carta Laura'],
    primoAvvioFatto: false,
  }
}
