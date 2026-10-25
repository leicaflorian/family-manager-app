import { describe, expect, it } from 'vitest'
import { leggiImporto } from './importo'

describe('leggiImporto', () => {
  it('accetta la virgola della tastiera italiana', () => {
    expect(leggiImporto('62,40')).toBe(62.4)
    expect(leggiImporto(' 62,4 € ')).toBe(62.4)
  })
  it('accetta anche il punto', () => expect(leggiImporto('62.40')).toBe(62.4))
  it('capisce i punti delle migliaia', () => expect(leggiImporto('1.234,50')).toBe(1234.5))
  it('rifiuta il vuoto e il testo', () => {
    expect(leggiImporto('')).toBeNull()
    expect(leggiImporto('abc')).toBeNull()
    expect(leggiImporto('-5')).toBeNull()
  })
})
