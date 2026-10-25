import { CATEGORIE, type Categoria, type Documento, type Famiglia } from '../data/tipi'

/** null vuol dire «tutti» */
export interface FiltroDocumenti {
  categoria?: Categoria | null
  personaId?: string | null
  ente?: string | null
}

const normalizza = (testo: string) =>
  testo.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim()

/** Cerca nel titolo, nella persona, nella scheda, nell'ente e nella categoria, dentro i filtri; dal più recente */
export function cercaDocumenti(f: Famiglia, testo: string, filtro: FiltroDocumenti = {}): Documento[] {
  const cercato = normalizza(testo)
  const trovati = f.documenti.filter((d) => {
    if (filtro.categoria && d.categoria !== filtro.categoria) return false
    if (filtro.personaId && d.personaId !== filtro.personaId) return false
    if (filtro.ente && d.ente !== filtro.ente) return false
    if (!cercato) return true
    const persona = f.persone.find((p) => p.id === d.personaId)?.nome ?? ''
    const scheda = f.schede.find((s) => s.id === d.schedaId)?.titolo ?? ''
    return normalizza(`${d.titolo} ${persona} ${scheda} ${d.ente ?? ''} ${d.categoria}`).includes(cercato)
  })
  return trovati.sort((a, b) => b.data.getTime() - a.data.getTime())
}

/** Le scelte dei filtri: solo categorie ed enti che hanno almeno un documento */
export function opzioniFiltro(f: Famiglia) {
  const usate = new Set(f.documenti.map((d) => d.categoria))
  const enti = [...new Set(f.documenti.map((d) => d.ente).filter((e): e is string => !!e))]
  return {
    categorie: CATEGORIE.filter((c) => usate.has(c)),
    persone: f.persone.map((p) => ({ value: p.id, label: p.nome })),
    enti: enti.sort((a, b) => a.localeCompare(b, 'it')),
  }
}
