import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import App from '../App.vue'

describe('operations dashboard', () => {
  it('labels the demo environment and renders local work orders', async () => {
    const wrapper = mount(App, { attachTo: document.body })

    expect(wrapper.text()).toContain('Datos demo')
    expect(wrapper.text()).toContain('Cargando órdenes de servicio')

    await vi.waitFor(
      () => {
        expect(wrapper.text()).toContain('Mantenimiento preventivo de tablero eléctrico')
      },
      { timeout: 2_000 },
    )

    expect(wrapper.text()).toContain('Explora el flujo con datos de demostración')
    expect(wrapper.findAll('tbody tr')).toHaveLength(8)
  })

  it('filters the table from the search field', async () => {
    const wrapper = mount(App, { attachTo: document.body })

    await vi.waitFor(() => expect(wrapper.findAll('tbody tr')).toHaveLength(8), { timeout: 2_000 })
    await wrapper.get('#order-search').setValue('clinica')

    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.text()).toContain('Clínica San Felipe')
    expect(wrapper.text()).toContain('1 resultado')
  })
})
