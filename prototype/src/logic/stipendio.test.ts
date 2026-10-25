import { describe, expect, it } from 'vitest'
import { prossimoStipendio, stipendiPassati } from './stipendio'

describe('prossimoStipendio', () => {
  it('è in questo mese se il giorno non è ancora passato', () => {
    expect(prossimoStipendio(27, new Date(2026, 9, 25))).toEqual(new Date(2026, 9, 27))
  })

  it('oggi, se arriva oggi', () => {
    expect(prossimoStipendio(25, new Date(2026, 9, 25))).toEqual(new Date(2026, 9, 25))
  })

  it('passa al mese dopo se il giorno è passato', () => {
    expect(prossimoStipendio(23, new Date(2026, 9, 25))).toEqual(new Date(2026, 10, 23))
  })

  it("nei mesi più corti arriva l'ultimo giorno", () => {
    expect(prossimoStipendio(31, new Date(2026, 10, 5))).toEqual(new Date(2026, 10, 30))
    expect(prossimoStipendio(31, new Date(2027, 1, 1))).toEqual(new Date(2027, 1, 28))
  })
})

describe('stipendiPassati', () => {
  it('dà gli ultimi già arrivati, dal più recente', () => {
    expect(stipendiPassati(27, new Date(2026, 9, 25), 3)).toEqual([
      new Date(2026, 8, 27), new Date(2026, 7, 27), new Date(2026, 6, 27),
    ])
  })

  it('conta anche quello arrivato oggi', () => {
    expect(stipendiPassati(25, new Date(2026, 9, 25), 1)).toEqual([new Date(2026, 9, 25)])
  })

  it("attraversa l'anno", () => {
    expect(stipendiPassati(10, new Date(2027, 0, 5), 2)).toEqual([new Date(2026, 11, 10), new Date(2026, 10, 10)])
  })
})
