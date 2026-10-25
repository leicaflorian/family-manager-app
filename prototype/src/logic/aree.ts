import type { Area, Famiglia, Scadenza, Scheda } from '../data/tipi'
import { giorniTra, spostaGiorni } from './formato'
import { prossime, totale } from './scadenze'

export const AREE: { id: Area; nome: string }[] = [
  { id: 'casa', nome: 'Casa' },
  { id: 'veicoli', nome: 'Veicoli' },
  { id: 'assicurazioni', nome: 'Assicurazioni' },
  { id: 'banche', nome: 'Banche e finanziamenti' },
  { id: 'salute', nome: 'Salute' },
  { id: 'scuola', nome: 'Scuola e attività' },
  { id: 'lavoro', nome: 'Lavoro' },
  { id: 'abbonamenti', nome: 'Abbonamenti' },
  { id: 'pratiche', nome: 'Pratiche' },
  { id: 'trasversali', nome: 'Persone ed enti' },
]

/** Entro quanti giorni un documento che scade chiede attenzione */
const GIORNI_IN_SCADENZA = 60

const plurale = (n: number, uno: string, tanti: string) => (n === 1 ? `1 ${uno}` : `${n} ${tanti}`)

export function nomeArea(area: Area): string {
  return AREE.find((a) => a.id === area)?.nome ?? area
}

export function schedeDiArea(f: Famiglia, area: Area): Scheda[] {
  return f.schede.filter((s) => s.area === area)
}

/** L'etichetta che chiede attenzione su un'area, o null se è tutto a posto */
export function attenzioneArea(f: Famiglia, area: Area, oggi: Date): string | null {
  const ids = new Set(schedeDiArea(f, area).map((s) => s.id))
  const scadute = f.scadenze.filter((s) => ids.has(s.schedaId) && !s.pagata && s.data < oggi).length
  if (scadute) return plurale(scadute, 'scaduta', 'scadute')
  const manca = schedeDiArea(f, area).reduce((n, s) => n + s.manca.length, 0)
  if (manca) return `Manca ${plurale(manca, 'documento', 'documenti')}`
  const inScadenza = f.documenti.filter((d) => {
    if (!d.schedaId || !ids.has(d.schedaId) || !d.scadenza) return false
    const giorni = giorniTra(oggi, d.scadenza)
    return giorni >= 0 && giorni <= GIORNI_IN_SCADENZA
  }).length
  if (inScadenza) return `${inScadenza} in scadenza`
  return null
}

export function conteggioArea(f: Famiglia, area: Area): string {
  if (area === 'trasversali') return `${plurale(f.persone.length, 'persona', 'persone')} · ${plurale(f.enti.length, 'ente', 'enti')}`
  const n = schedeDiArea(f, area).length
  return n === 0 ? 'Nessuna, per ora' : plurale(n, 'scheda', 'schede')
}

/** Le scadenze di una scheda e delle sue collegate */
export function scadenzeDiScheda(f: Famiglia, schedaId: string): Scadenza[] {
  const scheda = f.schede.find((s) => s.id === schedaId)
  const ids = new Set([schedaId, ...(scheda?.collegate ?? [])])
  return f.scadenze.filter((s) => ids.has(s.schedaId))
}

export function prossimaDiScheda(f: Famiglia, schedaId: string, oggi: Date): Scadenza | null {
  return prossime(scadenzeDiScheda(f, schedaId), oggi, 365)[0] ?? null
}

/** Quanto resta da pagare per la scheda nei prossimi 12 mesi */
export function quantoPaghi(f: Famiglia, schedaId: string, oggi: Date): number {
  const fino = spostaGiorni(oggi, 365)
  return totale(scadenzeDiScheda(f, schedaId).filter((s) => !s.pagata && s.data <= fino))
}
