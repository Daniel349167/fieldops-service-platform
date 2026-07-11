import type { TimelineEvent, WorkOrder, WorkOrderEvidence } from '../types/operations'

const todayAt = (hour: number, minute = 0, dayOffset = 0): string => {
  const date = new Date()
  date.setDate(date.getDate() + dayOffset)
  date.setHours(hour, minute, 0, 0)
  return date.toISOString()
}

export const demoOrders: WorkOrder[] = [
  {
    id: '01J-FOP-00001-A9C2',
    title: 'Mantenimiento preventivo de tablero eléctrico',
    description:
      'Inspección termográfica, ajuste de conexiones y prueba de funcionamiento del tablero principal.',
    customer: { name: 'Clínica San Felipe', phone: '+51 987 450 210', email: 'operaciones@csf.pe' },
    address: { line: 'Av. Gregorio Escobedo 650', district: 'Jesús María', city: 'Lima', lat: -12.087, lng: -77.054 },
    priority: 'urgent',
    status: 'in_progress',
    assigned_technician: { id: 'tech-01', name: 'Luis Ramírez', email: 'luis@fieldops.pe' },
    scheduled_at: todayAt(9, 30),
    version: 7,
    created_at: todayAt(15, 20, -3),
    updated_at: todayAt(10, 42),
    deleted_at: null,
  },
  {
    id: '01J-FOP-00002-E3B8',
    title: 'Instalación de punto de red corporativo',
    description: 'Tendido CAT6, certificación del punto y actualización de plano de red.',
    customer: { name: 'Estudio Arce & Asociados', phone: '+51 946 221 030', email: 'it@arce.pe' },
    address: { line: 'Calle Las Camelias 790', district: 'San Isidro', city: 'Lima', lat: -12.097, lng: -77.027 },
    priority: 'high',
    status: 'en_route',
    assigned_technician: { id: 'tech-02', name: 'María Torres', email: 'maria@fieldops.pe' },
    scheduled_at: todayAt(11, 0),
    version: 4,
    created_at: todayAt(12, 10, -2),
    updated_at: todayAt(10, 35),
    deleted_at: null,
  },
  {
    id: '01J-FOP-00003-D1F4',
    title: 'Diagnóstico de aire acondicionado',
    description: 'Diagnóstico de pérdida de capacidad en equipo split de sala de reuniones.',
    customer: { name: 'Innova Cowork', phone: '+51 955 843 100', email: 'soporte@innovacowork.pe' },
    address: { line: 'Av. Benavides 1180', district: 'Miraflores', city: 'Lima', lat: -12.124, lng: -77.021 },
    priority: 'normal',
    status: 'assigned',
    assigned_technician: { id: 'tech-03', name: 'Diego Mendoza', email: 'diego@fieldops.pe' },
    scheduled_at: todayAt(14, 30),
    version: 2,
    created_at: todayAt(8, 45, -1),
    updated_at: todayAt(9, 58),
    deleted_at: null,
  },
  {
    id: '01J-FOP-00004-C7A6',
    title: 'Revisión de sistema CCTV',
    description: 'Restablecer señal de dos cámaras y verificar almacenamiento del NVR.',
    customer: { name: 'Almacenes Rivera', phone: '+51 930 110 452', email: 'seguridad@rivera.pe' },
    address: { line: 'Av. Argentina 2840', district: 'Callao', city: 'Callao', lat: -12.051, lng: -77.113 },
    priority: 'high',
    status: 'pending',
    assigned_technician: null,
    scheduled_at: todayAt(16, 0),
    version: 1,
    created_at: todayAt(8, 20),
    updated_at: todayAt(8, 20),
    deleted_at: null,
  },
  {
    id: '01J-FOP-00005-F6D9',
    title: 'Cambio de luminarias de emergencia',
    description: 'Sustitución y prueba de autonomía de seis luminarias de evacuación.',
    customer: { name: 'Colegio Horizonte', phone: '+51 922 501 877', email: 'mantenimiento@horizonte.edu.pe' },
    address: { line: 'Jr. Los Pinos 414', district: 'Surco', city: 'Lima', lat: -12.142, lng: -76.992 },
    priority: 'normal',
    status: 'completed',
    assigned_technician: { id: 'tech-01', name: 'Luis Ramírez', email: 'luis@fieldops.pe' },
    scheduled_at: todayAt(8, 0),
    version: 9,
    created_at: todayAt(9, 0, -5),
    updated_at: todayAt(9, 18),
    deleted_at: null,
  },
  {
    id: '01J-FOP-00006-B2K7',
    title: 'Configuración de control de acceso',
    description: 'Alta de usuarios, reglas horarias y prueba de lector biométrico.',
    customer: { name: 'Finanzas Andinas', phone: '+51 911 722 040', email: 'facility@finanzasandinas.pe' },
    address: { line: 'Av. Javier Prado Este 4200', district: 'Santiago de Surco', city: 'Lima', lat: -12.091, lng: -76.971 },
    priority: 'low',
    status: 'cancelled',
    assigned_technician: { id: 'tech-04', name: 'Ana Vega', email: 'ana@fieldops.pe' },
    scheduled_at: todayAt(13, 0, -1),
    version: 3,
    created_at: todayAt(10, 0, -4),
    updated_at: todayAt(18, 5, -1),
    deleted_at: null,
  },
  {
    id: '01J-FOP-00007-P5R1',
    title: 'Inspección de bomba de agua',
    description: 'Medición de presión, revisión de tablero y detección de vibraciones.',
    customer: { name: 'Condominio Panorama', phone: '+51 975 303 899', email: 'junta@panorama.pe' },
    address: { line: 'Av. Arequipa 3270', district: 'San Isidro', city: 'Lima', lat: -12.103, lng: -77.03 },
    priority: 'urgent',
    status: 'assigned',
    assigned_technician: { id: 'tech-04', name: 'Ana Vega', email: 'ana@fieldops.pe' },
    scheduled_at: todayAt(8, 30, 1),
    version: 2,
    created_at: todayAt(16, 30, -1),
    updated_at: todayAt(7, 50),
    deleted_at: null,
  },
  {
    id: '01J-FOP-00008-N8T4',
    title: 'Mantenimiento de grupo electrógeno',
    description: 'Cambio de filtros, prueba en vacío y registro de parámetros.',
    customer: { name: 'Centro Médico Norte', phone: '+51 988 305 410', email: 'infraestructura@cmn.pe' },
    address: { line: 'Av. Carlos Izaguirre 978', district: 'Los Olivos', city: 'Lima', lat: -11.991, lng: -77.064 },
    priority: 'normal',
    status: 'completed',
    assigned_technician: { id: 'tech-03', name: 'Diego Mendoza', email: 'diego@fieldops.pe' },
    scheduled_at: todayAt(10, 0, -1),
    version: 8,
    created_at: todayAt(11, 0, -7),
    updated_at: todayAt(12, 15, -1),
    deleted_at: null,
  },
]

export const demoEvidences: Record<string, WorkOrderEvidence[]> = {
  '01J-FOP-00001-A9C2': [
    {
      id: 'evidence-01', work_order_id: '01J-FOP-00001-A9C2', file_name: 'tablero-antes.svg',
      mime_type: 'image/svg+xml', size_bytes: 145200, storage_path: '/evidence-panel.svg',
      captured_at: todayAt(10, 18), created_at: todayAt(10, 20),
      metadata: { label: 'Estado inicial del tablero', url: '/evidence-panel.svg', kind: 'photo' },
    },
    {
      id: 'evidence-02', work_order_id: '01J-FOP-00001-A9C2', file_name: 'ajuste-conexiones.svg',
      mime_type: 'image/svg+xml', size_bytes: 118800, storage_path: '/evidence-installation.svg',
      captured_at: todayAt(10, 36), created_at: todayAt(10, 37),
      metadata: { label: 'Ajuste de conexiones', url: '/evidence-installation.svg', kind: 'photo' },
    },
  ],
}

export const demoTimeline: Record<string, TimelineEvent[]> = {
  '01J-FOP-00001-A9C2': [
    { id: 'event-04', event_type: 'status_changed', from_status: 'en_route', to_status: 'in_progress', note: 'Ingreso confirmado por seguridad.', actor: { name: 'Luis Ramírez', role: 'Técnico' }, occurred_at: todayAt(10, 5) },
    { id: 'event-03', event_type: 'status_changed', from_status: 'assigned', to_status: 'en_route', note: null, actor: { name: 'Luis Ramírez', role: 'Técnico' }, occurred_at: todayAt(9, 20) },
    { id: 'event-02', event_type: 'technician_assigned', from_status: 'pending', to_status: 'assigned', note: 'Asignación por cercanía y especialidad.', actor: { name: 'Carla Ortiz', role: 'Coordinadora' }, occurred_at: todayAt(17, 30, -1) },
    { id: 'event-01', event_type: 'work_order_created', from_status: null, to_status: 'pending', note: 'Solicitud registrada desde mesa de ayuda.', actor: { name: 'Carla Ortiz', role: 'Coordinadora' }, occurred_at: todayAt(15, 20, -3) },
  ],
}

export function genericTimeline(order: WorkOrder): TimelineEvent[] {
  return [
    {
      id: `${order.id}-event-02`, event_type: 'status_changed', from_status: 'pending', to_status: order.status,
      note: order.status === 'cancelled' ? 'Visita reprogramada por el cliente.' : null,
      actor: { name: order.assigned_technician?.name ?? 'Centro de operaciones', role: order.assigned_technician ? 'Técnico' : 'Coordinación' },
      occurred_at: order.updated_at,
    },
    {
      id: `${order.id}-event-01`, event_type: 'work_order_created', from_status: null, to_status: 'pending',
      note: 'Orden registrada en FieldOps.', actor: { name: 'Centro de operaciones', role: 'Sistema' }, occurred_at: order.created_at,
    },
  ]
}
