export type Area =
  | 'casa'
  | 'veicoli'
  | 'assicurazioni'
  | 'banche'
  | 'salute'
  | 'scuola'
  | 'lavoro'
  | 'abbonamenti'
  | 'pratiche'
  | 'trasversali'

export interface Persona {
  id: string
  nome: string
  ruolo: 'genitore' | 'figlio'
}

export interface Scheda {
  id: string
  area: Area
  titolo: string
  sottotitolo: string
  /** Documenti che la scheda dovrebbe avere e non ha */
  manca: string[]
  /** Schede le cui scadenze compaiono anche in questa (la polizza nella scheda dell'auto) */
  collegate?: string[]
}

export interface Pagamento {
  data: Date
  conto: string
  ricevuta: boolean
}

export interface Scadenza {
  id: string
  schedaId: string
  titolo: string
  /** null per le scadenze senza pagamento, come la revisione */
  importo: number | null
  data: Date
  pagata: Pagamento | null
}

export interface Documento {
  id: string
  titolo: string
  schedaId: string | null
  personaId: string | null
  data: Date
  scadenza: Date | null
}

export interface Posta {
  id: string
  mittente: string
  oggetto: string
  arrivata: Date
  documentoId: string
}

export interface Famiglia {
  nome: string
  persone: Persona[]
  enti: string[]
  schede: Scheda[]
  scadenze: Scadenza[]
  documenti: Documento[]
  posta: Posta[]
  conti: string[]
  primoAvvioFatto: boolean
}
