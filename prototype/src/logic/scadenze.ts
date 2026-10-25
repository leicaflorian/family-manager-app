import type { Scadenza } from '../data/tipi'
import { spostaGiorni } from './formato'

const perData = (a: Scadenza, b: Scadenza) => a.data.getTime() - b.data.getTime()

/** Da pagare entro `fino`, scadute comprese, in ordine di data */
export function daPagareEntro(scadenze: Scadenza[], _oggi: Date, fino: Date): Scadenza[] {
  return scadenze.filter((s) => !s.pagata && s.importo !== null && s.data <= fino).sort(perData)
}

export function totale(scadenze: Scadenza[]): number {
  return scadenze.reduce((somma, s) => somma + (s.importo ?? 0), 0)
}

/** Non pagate entro `giorni` da oggi, scadute comprese, in ordine di data */
export function prossime(scadenze: Scadenza[], oggi: Date, giorni: number): Scadenza[] {
  const fino = spostaGiorni(oggi, giorni)
  return scadenze.filter((s) => !s.pagata && s.data <= fino).sort(perData)
}
