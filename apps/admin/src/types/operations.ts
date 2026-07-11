export type WorkOrderStatus =
  | 'pending'
  | 'assigned'
  | 'en_route'
  | 'in_progress'
  | 'completed'
  | 'cancelled'

export type WorkOrderPriority = 'low' | 'normal' | 'high' | 'urgent'

export interface Customer {
  name: string
  phone: string
  email: string
}

export interface ServiceAddress {
  line: string
  district: string
  city: string
  lat: number
  lng: number
}

export interface Technician {
  id: string
  name: string
  email: string
}

export interface WorkOrder {
  id: string
  title: string
  description: string
  customer: Customer
  address: ServiceAddress
  priority: WorkOrderPriority
  status: WorkOrderStatus
  assigned_technician: Technician | null
  scheduled_at: string
  version: number
  created_at: string
  updated_at: string
  deleted_at: string | null
}

export interface WorkOrderEvidence {
  id: string
  work_order_id: string
  file_name: string
  mime_type: string
  size_bytes: number
  storage_path: string
  captured_at: string | null
  created_at: string
  metadata?: {
    label?: string
    url?: string
    kind?: 'photo' | 'signature' | 'document' | 'note'
  }
}

export interface TimelineActor {
  id?: string
  name: string
  role?: string
}

export interface TimelineEvent {
  id: string
  event_type: string
  from_status: WorkOrderStatus | null
  to_status: WorkOrderStatus | null
  note: string | null
  actor: TimelineActor | null
  occurred_at: string
}

export interface DashboardSummary {
  total: number
  open: number
  completed: number
  cancelled: number
  urgent_open: number
  due_today: number
}

export type StatusBreakdown = Record<WorkOrderStatus, number>

export interface DashboardData {
  summary: DashboardSummary
  by_status: StatusBreakdown
  recent_work_orders: WorkOrder[]
}

export interface WorkOrderFilters {
  q: string
  status: WorkOrderStatus | 'all'
  priority: WorkOrderPriority | 'all'
}

export interface PaginationMeta {
  current_page?: number
  last_page?: number
  per_page?: number
  total?: number
}

export interface ApiEnvelope<T> {
  data: T
  meta?: PaginationMeta
}

export interface WorkOrderDetail {
  order: WorkOrder
  evidences: WorkOrderEvidence[]
  timeline: TimelineEvent[]
}

export interface OperationsGateway {
  getDashboard(): Promise<DashboardData>
  listWorkOrders(filters?: Partial<WorkOrderFilters>): Promise<ApiEnvelope<WorkOrder[]>>
  getWorkOrder(id: string): Promise<WorkOrder>
  getEvidences(id: string): Promise<WorkOrderEvidence[]>
  getTimeline(id: string): Promise<TimelineEvent[]>
}
