import type { Documento, Famiglia } from '../data/tipi'

const normalizza = (testo: string) =>
  testo.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()

/** Cerca nel titolo, nella persona e nella scheda; dal più recente */
export function cercaDocumenti(f: Famiglia, testo: string): Documento[] {
  const cercato = normalizza(testo)
  const trovati = f.documenti.filter((d) => {
    if (!cercato) return true
    const persona = f.persone.find((p) => p.id === d.personaId)?.nome ?? ''
    const scheda = f.schede.find((s) => s.id === d.schedaId)?.titolo ?? ''
    return normalizza(`${d.titolo} ${persona} ${scheda}`).includes(cercato)
  })
  return trovati.sort((a, b) => b.data.getTime() - a.data.getTime())
}
