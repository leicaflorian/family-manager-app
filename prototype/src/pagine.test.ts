import { autoAnimatePlugin } from '@formkit/auto-animate/vue'
import { plugin as formkit } from '@formkit/vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import App from './App.vue'
import formkitConfig from './formkit.config'
import { router } from './router'
import { completaPrimoAvvio, ricomincia } from './stato'

async function apri(percorso: string) {
  const app = mount(App, { global: { plugins: [router, [formkit, formkitConfig], autoAnimatePlugin] }, attachTo: document.body })
  await router.push(percorso)
  await flushPromises()
  return app
}

describe('le pagine si aprono', () => {
  beforeEach(() => ricomincia())

  it('prima del primo avvio porta al benvenuto', async () => {
    const app = await apri('/')
    expect(router.currentRoute.value.path).toBe('/benvenuto')
    expect(app.text()).toContain('Family Manager')
    app.unmount()
  })

  const pagine: [string, string[]][] = [
    ['/', ['Da pagare entro fine mese', 'Bolletta luce', 'Arrivato per posta', 'Account']],
    ['/carica', ['Scatta una foto', 'Scegli un file']],
    ['/scadenza/s-rc', ['Rata RC auto', '418,00 €', "L'ho pagata"]],
    ['/scadenza/nessuna', ["Questa scadenza non c'è più"]],
    ['/schede', ['Casa', 'Persone ed enti', '1 scaduta', 'Manca 1 documento']],
    ['/schede/casa', ['Luce - Edison', 'Bolletta luce']],
    ['/schede/trasversali', ['Giulia', 'UnipolSai']],
    ['/scheda/panda', ['Fiat Panda', 'Rata RC auto', 'Libretto di circolazione', 'Caricalo']],
    ['/documenti', ["Carta d'identità", 'Scade il']],
    ['/account', ['Famiglia Bianchi']],
  ]
  it.each(pagine)('%s', async (percorso, testi) => {
    completaPrimoAvvio()
    const app = await apri(percorso)
    for (const t of testi) expect(app.text()).toContain(t)
    app.unmount()
  })
})

describe('i flussi', () => {
  beforeEach(() => {
    ricomincia()
    completaPrimoAvvio()
  })

  it("L'ho pagata segna il pagamento e toglie il tasto", async () => {
    const app = await apri('/scadenza/s-gas')
    expect(app.findAll('button').some((b) => b.text() === "L'ho pagata")).toBe(true)
    await app.find('button.h-14').trigger('click')
    await flushPromises()
    await app.find('[role="dialog"] form').trigger('submit')
    await flushPromises()
    expect(app.text()).toContain('Pagata il')
    expect(app.text()).toContain('con Conto BPER')
    expect(app.findAll('button').some((b) => b.text() === "L'ho pagata")).toBe(false)
    app.unmount()
  })

  it('il caricamento finisce tra le scadenze', async () => {
    const app = await apri('/carica')
    const input = app.find('input[type="file"]')
    const file = new File(['x'], 'bolletta.heic', { type: 'image/heic' })
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    expect(app.text()).toContain('Sto leggendo il documento')
    await new Promise((r) => setTimeout(r, 2100))
    await flushPromises()
    expect(app.text()).toContain('Ho trovato una bolletta della luce')
    await app.find('form').trigger('submit')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/')
    // Con replace, il tasto indietro non riapre Carica vuota
    expect(window.history.state.replaced).toBe(true)
    expect(app.text()).toContain('Bolletta luce di ottobre')
    app.unmount()
  })

  it('annullare la scelta del file lascia sul primo passo', async () => {
    const app = await apri('/carica')
    const input = app.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [] })
    await input.trigger('change')
    expect(app.text()).toContain('Scatta una foto')
    app.unmount()
  })
})
