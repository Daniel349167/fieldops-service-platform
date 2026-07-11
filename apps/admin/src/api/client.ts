import { demoEvidences, demoOrders, demoTimeline, genericTimeline } from '../data/demo'
import type {
  ApiEnvelope,
  DashboardData,
  OperationsGateway,
  TimelineEvent,
  WorkOrder,
  WorkOrderEvidence,
  WorkOrderFilters,
} from '../types/operations'
import { buildDashboardSummary, buildStatusBreakdown, filterWorkOrders } from '../utils/orders'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

function apiBaseUrl(): string {
  const configured = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1').replace(/\/$/, '')
  return configured.endsWith('/api/v1') ? configured : `${configured}/api/v1`
}

function authToken(): string | undefined {
  return localStorage.getItem('fieldops_token') || import.meta.env.VITE_API_TOKEN || undefined
}

async function request<T>(path: string, init?: RequestInit): Promise<ApiEnvelope<T>> {
  const controller = new AbortController()
  const timeout = window.setTimeout(() => controller.abort(), 10_000)
  const token = authToken()

  try {
    const response = await fetch(`${apiBaseUrl()}${path}`, {
      ...init,
      signal: controller.signal,
      headers: {
        Accept: 'application/json',
        ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...init?.headers,
      },
    })
    const payload = (await response.json().catch(() => ({}))) as {
      data?: T
      meta?: ApiEnvelope<T>['meta']
      message?: string
      code?: string
    }

    if (!response.ok) {
      throw new ApiError(payload.message || 'No fue posible completar la solicitud.', response.status, payload.code)
    }
    if (payload.data === undefined) {
      throw new ApiError('La API devolvió una respuesta sin datos.', response.status, 'INVALID_RESPONSE')
    }
    return { data: payload.data, meta: payload.meta }
  } catch (error) {
    if (error instanceof ApiError) throw error
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw new ApiError('La API tardó demasiado en responder.', 408, 'TIMEOUT')
    }
    throw new ApiError('No se pudo conectar con la API de FieldOps.', 0, 'NETWORK_ERROR')
  } finally {
    window.clearTimeout(timeout)
  }
}

function queryString(filters: Partial<WorkOrderFilters> = {}): string {
  const params = new URLSearchParams()
  if (filters.q?.trim()) params.set('q', filters.q.trim())
  if (filters.status && filters.status !== 'all') params.set('status', filters.status)
  if (filters.priority && filters.priority !== 'all') params.set('priority', filters.priority)
  params.set('per_page', '50')
  return params.toString()
}

export const httpGateway: OperationsGateway = {
  async getDashboard(): Promise<DashboardData> {
    return (await request<DashboardData>('/dashboard')).data
  },
  async listWorkOrders(filters = {}): Promise<ApiEnvelope<WorkOrder[]>> {
    return request<WorkOrder[]>(`/work-orders?${queryString(filters)}`)
  },
  async getWorkOrder(id: string): Promise<WorkOrder> {
    return (await request<WorkOrder>(`/work-orders/${encodeURIComponent(id)}`)).data
  },
  async getEvidences(id: string): Promise<WorkOrderEvidence[]> {
    return (await request<WorkOrderEvidence[]>(`/work-orders/${encodeURIComponent(id)}/evidences`)).data
  },
  async getTimeline(id: string): Promise<TimelineEvent[]> {
    return (await request<TimelineEvent[]>(`/work-orders/${encodeURIComponent(id)}/timeline`)).data
  },
}

const pause = (milliseconds = 380): Promise<void> =>
  new Promise((resolve) => window.setTimeout(resolve, milliseconds))

export const demoGateway: OperationsGateway = {
  async getDashboard(): Promise<DashboardData> {
    await pause()
    return {
      summary: buildDashboardSummary(demoOrders),
      by_status: buildStatusBreakdown(demoOrders),
      recent_work_orders: [...demoOrders]
        .sort((first, second) => Date.parse(second.updated_at) - Date.parse(first.updated_at))
        .slice(0, 5),
    }
  },
  async listWorkOrders(filters = {}): Promise<ApiEnvelope<WorkOrder[]>> {
    await pause(460)
    const normalized: WorkOrderFilters = {
      q: filters.q ?? '',
      status: filters.status ?? 'all',
      priority: filters.priority ?? 'all',
    }
    const data = filterWorkOrders(demoOrders, normalized)
    return { data, meta: { current_page: 1, last_page: 1, per_page: 50, total: data.length } }
  },
  async getWorkOrder(id: string): Promise<WorkOrder> {
    await pause(230)
    const order = demoOrders.find((candidate) => candidate.id === id)
    if (!order) throw new ApiError('No encontramos esta orden de servicio.', 404, 'WORK_ORDER_NOT_FOUND')
    return order
  },
  async getEvidences(id: string): Promise<WorkOrderEvidence[]> {
    await pause(180)
    return demoEvidences[id] ?? []
  },
  async getTimeline(id: string): Promise<TimelineEvent[]> {
    await pause(180)
    const order = demoOrders.find((candidate) => candidate.id === id)
    if (!order) throw new ApiError('No encontramos esta orden de servicio.', 404, 'WORK_ORDER_NOT_FOUND')
    return demoTimeline[id] ?? genericTimeline(order)
  },
}

const configuredDemoMode = import.meta.env.VITE_DEMO_MODE

/**
 * The portfolio build is useful out of the box: when no environment variable is
 * provided it serves deterministic local data. API mode must be selected
 * explicitly with VITE_DEMO_MODE=false, which prevents a production-looking UI
 * from silently masking a broken backend connection.
 */
export const isDemoMode = configuredDemoMode === undefined || configuredDemoMode === 'true'

export function createOperationsGateway(): OperationsGateway {
  return isDemoMode ? demoGateway : httpGateway
}
