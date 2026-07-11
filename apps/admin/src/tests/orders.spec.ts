import { describe, expect, it } from 'vitest'
import { demoOrders } from '../data/demo'
import {
  buildDashboardSummary,
  buildStatusBreakdown,
  filterWorkOrders,
  formatRelativeTime,
  shortOrderId,
} from '../utils/orders'

describe('order utilities', () => {
  it('filters by text without requiring accents', () => {
    const results = filterWorkOrders(demoOrders, {
      q: 'clinica',
      status: 'all',
      priority: 'all',
    })

    expect(results).toHaveLength(1)
    expect(results[0]?.customer.name).toBe('Clínica San Felipe')
  })

  it('combines status and priority filters', () => {
    const results = filterWorkOrders(demoOrders, {
      q: '',
      status: 'assigned',
      priority: 'urgent',
    })

    expect(results.map((order) => order.customer.name)).toEqual(['Condominio Panorama'])
  })

  it('builds consistent operational metrics', () => {
    const now = new Date(demoOrders[0]!.scheduled_at)

    expect(buildDashboardSummary(demoOrders, now)).toEqual({
      total: 8,
      open: 5,
      completed: 2,
      cancelled: 1,
      urgent_open: 2,
      due_today: 5,
    })
    expect(buildStatusBreakdown(demoOrders)).toEqual({
      pending: 1,
      assigned: 2,
      en_route: 1,
      in_progress: 1,
      completed: 2,
      cancelled: 1,
    })
  })

  it('formats stable identifiers and relative timestamps', () => {
    expect(shortOrderId('01J-FOP-00001-A9C2')).toBe('OT-1A9C2')
    expect(
      formatRelativeTime(new Date('2026-07-10T15:30:00Z'), new Date('2026-07-10T15:00:00Z')),
    ).toContain('30')
  })
})
