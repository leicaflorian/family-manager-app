import { reactive } from 'vue'

// Il messaggio che compare per qualche secondo dopo un'azione
export const messaggio = reactive({ testo: '' })
let timer: ReturnType<typeof setTimeout> | undefined

export function mostraMessaggio(testo: string): void {
  messaggio.testo = testo
  clearTimeout(timer)
  timer = setTimeout(() => (messaggio.testo = ''), 3500)
}
