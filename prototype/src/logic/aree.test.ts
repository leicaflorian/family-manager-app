import { describe, expect, it } from 'vitest'
import { creaFamiglia } from '../data/famiglia'
import { AREE, attenzioneArea, conteggioArea, prossimaDiScheda, quantoPaghi } from './aree'

const oggi = new Date(2026, 9, 25)
const f = creaFamiglia(oggi)

describe('AREE', () => {
  it('elenca le 10 aree della griglia', () => {
    expect(AREE.map((a) => a.nome)).toEqual([
      'Casa', 'Veicoli', 'Assicurazioni', 'Banche e finanziamenti', 'Salute',
      'Scuola e attività', 'Lavoro', 'Abbonamenti', 'Pratiche', 'Persone ed enti',
    ])
  })
})

describe('attenzioneArea', () => {
  it('segnala prima le scadenze passate', () => expect(attenzioneArea(f, 'casa', oggi)).toBe('1 scaduta'))
  it('poi i documenti che mancano', () => expect(attenzioneArea(f, 'veicoli', oggi)).toBe('Manca 1 documento'))
  it('poi i documenti in scadenza entro 60 giorni', () => expect(attenzioneArea(f, 'salute', oggi)).toBe('1 in scadenza'))
  it('tace quando è tutto a posto', () => {
    expect(attenzioneArea(f, 'assicurazioni', oggi)).toBeNull()
    expect(attenzioneArea(f, 'pratiche', oggi)).toBeNull()
  })
})

describe('conteggioArea', () => {
  it('conta le schede', () => {
    expect(conteggioArea(f, 'casa')).toBe('4 schede')
    expect(conteggioArea(f, 'veicoli')).toBe('1 scheda')
    expect(conteggioArea(f, 'pratiche')).toBe('Nessuna, per ora')
  })
  it('per persone ed enti conta persone ed enti', () => {
    expect(conteggioArea(f, 'trasversali')).toBe('3 persone · 12 enti')
  })
})

describe('una scheda e le sue collegate', () => {
  it('la Panda mostra la rata RC auto come prossima scadenza', () => {
    expect(prossimaDiScheda(f, 'panda', oggi)?.titolo).toBe('Rata RC auto')
  })
  it('quanto paghi conta i prossimi 12 mesi', () => {
    expect(quantoPaghi(f, 'panda', oggi)).toBeCloseTo(418)
    expect(quantoPaghi(f, 'gas', oggi)).toBeCloseTo(84.2)
  })
})
