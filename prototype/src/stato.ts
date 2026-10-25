import { dayStart } from '@formkit/tempo'
import { reactive } from 'vue'
import { creaFamiglia } from './data/famiglia'
import type { Categoria, Famiglia, Pagamento } from './data/tipi'

export interface PropostaDocumento {
  titolo: string
  schedaId: string
  categoria: Categoria
  ente: string | null
  importo: number
  scadenza: Date
}

export function oggi(): Date {
  return dayStart(new Date())
}

// Una sola famiglia in memoria: ricaricare la pagina la ricrea da capo
const famiglia = reactive<Famiglia>(creaFamiglia(oggi()))
let progressivo = 0

export function useFamiglia(): Famiglia {
  return famiglia
}

/** false se la scadenza non esiste o è già pagata */
export function segnaPagata(id: string, pagamento: Pagamento): boolean {
  const scadenza = famiglia.scadenze.find((s) => s.id === id)
  if (!scadenza || scadenza.pagata) return false
  scadenza.pagata = { ...pagamento }
  return true
}

export function aggiungiDocumento(proposta: PropostaDocumento): void {
  const n = ++progressivo
  famiglia.documenti.push({
    id: `d-nuovo-${n}`,
    titolo: proposta.titolo,
    schedaId: proposta.schedaId,
    personaId: null,
    categoria: proposta.categoria,
    ente: proposta.ente,
    data: oggi(),
    scadenza: proposta.scadenza,
  })
  if (proposta.importo > 0) {
    famiglia.scadenze.push({
      id: `s-nuova-${n}`,
      schedaId: proposta.schedaId,
      titolo: proposta.titolo,
      importo: proposta.importo,
      data: proposta.scadenza,
      pagata: null,
    })
  }
}

export function completaPrimoAvvio(): void {
  famiglia.primoAvvioFatto = true
}

export function ricomincia(): void {
  Object.assign(famiglia, creaFamiglia(oggi()))
}
