import { afterEach } from 'vitest'

afterEach(() => {
  document.body.innerHTML = ''
  document.body.className = ''
  localStorage.clear()
})

Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
  configurable: true,
  value: () => undefined,
})
