import type {
  DashboardSummary,
  StatusBreakdown,
  WorkOrder,
  WorkOrderFilters,
  WorkOrderPriority,
  WorkOrderStatus,
} from '../types/operations'

export const STATUS_LABELS: Record<WorkOrderStatus, string> = {
  pending: 'Pendiente',
  assigned: 'Asignada',
  en_route: 'En camino',
  in_progress: 'En curso',
  completed: 'Completada',
  cancelled: 'Cancelada',
}

export const PRIORITY_LABELS: Record<WorkOrderPriority, string> = {
  low: 'Baja',
  normal: 'Normal',
  high: 'Alta',
  urgent: 'Urgente',
}

export const OPEN_STATUSES: WorkOrderStatus[] = ['pending', 'assigned', 'en_route', 'in_progress']

function normalizeSearchText(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('es')
}

function localDateKey(value: string | Date): string {
  const date = value instanceof Date ? value : new Date(value)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

export function shortOrderId(id: string): string {
  const compact = id.replaceAll('-', '')
  return `OT-${compact.slice(-5).toUpperCase()}`
}

export function filterWorkOrders(orders: WorkOrder[], filters: WorkOrderFilters): WorkOrder[] {
  const query = normalizeSearchText(filters.q.trim())

  return orders.filter((order) => {
    const matchesStatus = filters.status === 'all' || order.status === filters.status
    const matchesPriority = filters.priority === 'all' || order.priority === filters.priority
    const haystack = normalizeSearchText(
      [
        order.id,
        order.title,
        order.customer.name,
        order.address.district,
        order.assigned_technician?.name ?? '',
      ].join(' '),
    )

    return matchesStatus && matchesPriority && (!query || haystack.includes(query))
  })
}

export function buildStatusBreakdown(orders: WorkOrder[]): StatusBreakdown {
  return orders.reduce<StatusBreakdown>(
    (summary, order) => {
      summary[order.status] += 1
      return summary
    },
    { pending: 0, assigned: 0, en_route: 0, in_progress: 0, completed: 0, cancelled: 0 },
  )
}

export function buildDashboardSummary(orders: WorkOrder[], now = new Date()): DashboardSummary {
  const today = localDateKey(now)
  return {
    total: orders.length,
    open: orders.filter((order) => OPEN_STATUSES.includes(order.status)).length,
    completed: orders.filter((order) => order.status === 'completed').length,
    cancelled: orders.filter((order) => order.status === 'cancelled').length,
    urgent_open: orders.filter(
      (order) => order.priority === 'urgent' && OPEN_STATUSES.includes(order.status),
    ).length,
    due_today: orders.filter(
      (order) => localDateKey(order.scheduled_at) === today && order.status !== 'cancelled',
    ).length,
  }
}

export function formatDateTime(value: string, options?: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat('es-PE', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    ...options,
  }).format(new Date(value))
}

export function formatRelativeTime(value: string | Date, now = new Date()): string {
  const difference = new Date(value).getTime() - now.getTime()
  const minutes = Math.round(difference / 60_000)
  const formatter = new Intl.RelativeTimeFormat('es', { numeric: 'auto' })

  if (Math.abs(minutes) < 60) return formatter.format(minutes, 'minute')
  const hours = Math.round(minutes / 60)
  if (Math.abs(hours) < 24) return formatter.format(hours, 'hour')
  return formatter.format(Math.round(hours / 24), 'day')
}
