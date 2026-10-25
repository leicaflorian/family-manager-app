import { describe, expect, it } from 'vitest'
import { creaFamiglia } from '../data/famiglia'
import { CATEGORIE } from '../data/famiglia'
import { cercaDocumenti, opzioniFiltro } from './ricerca'

const f = creaFamiglia(new Date(2026, 9, 25))

describe('cercaDocumenti', () => {
  it('trova i documenti di una persona', () => {
    expect(cercaDocumenti(f, 'giulia').map((d) => d.titolo).sort()).toEqual([
      "Carta d'identità", 'Certificato medico sportivo', 'Iscrizione danza 2026/27',
    ])
  })
  it('ignora maiuscole e accenti', () => {
    // Anche la patente, che sta nei documenti d'identità
    expect(cercaDocumenti(f, 'IDENTITA').map((d) => d.titolo).sort()).toEqual(["Carta d'identità", 'Patente'])
  })
  it('cerca anche nel nome della scheda', () => {
    expect(cercaDocumenti(f, 'panda').map((d) => d.titolo).sort()).toEqual(['Bollo 2026', 'Polizza RC auto 2026', 'Ultima revisione'])
  })
  it('senza testo restituisce tutto, dal più recente', () => {
    const tutti = cercaDocumenti(f, '  ')
    expect(tutti.length).toBe(f.documenti.length)
    expect(tutti[0].data >= tutti[tutti.length - 1].data).toBe(true)
  })
})

describe('cercaDocumenti con i filtri', () => {
  const titoli = (filtro: Parameters<typeof cercaDocumenti>[2], testo = '') => cercaDocumenti(f, testo, filtro).map((d) => d.titolo).sort()

  it('filtra per categoria', () => {
    expect(titoli({ categoria: 'Polizze' })).toEqual(['Polizza RC auto 2026'])
  })
  it('filtra per persona', () => {
    expect(titoli({ personaId: 'marco' })).toContain('Patente')
    expect(titoli({ personaId: 'marco' })).not.toContain("Carta d'identità")
  })
  it('filtra per ente', () => {
    expect(titoli({ ente: 'Fastweb' })).toEqual(['Contratto Fastweb', 'Fattura Fastweb'])
  })
  it('mette insieme i filtri e il testo', () => {
    expect(titoli({ categoria: 'Lavoro e redditi', personaId: 'laura' }, 'certificazione')).toEqual(['Certificazione Unica 2026'])
  })
  it('un filtro vuoto non toglie niente', () => {
    expect(cercaDocumenti(f, '', { categoria: null, personaId: null, ente: null }).length).toBe(f.documenti.length)
  })
  it("cerca anche nell'ente e nella categoria", () => {
    expect(cercaDocumenti(f, 'unipol').map((d) => d.titolo)).toEqual(['Polizza RC auto 2026'])
    expect(cercaDocumenti(f, 'bollette').length).toBeGreaterThan(0)
  })
})

describe('opzioniFiltro', () => {
  const o = opzioniFiltro(f)
  it('propone solo categorie ed enti che hanno documenti, in ordine', () => {
    expect(o.categorie).not.toContain('Ricevute')
    expect(o.categorie.every((c) => CATEGORIE.includes(c))).toBe(true)
    expect(o.enti).toContain('Fastweb')
    expect(o.enti).not.toContain('Netflix')
    expect([...o.enti].sort((a, b) => a.localeCompare(b, 'it'))).toEqual(o.enti)
  })
  it('propone tutte le persone', () => {
    expect(o.persone).toEqual([{ value: 'marco', label: 'Marco' }, { value: 'laura', label: 'Laura' }, { value: 'giulia', label: 'Giulia' }])
  })
})
