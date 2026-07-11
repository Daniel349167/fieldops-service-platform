<script setup lang="ts">
import {
  Activity,
  AlertTriangle,
  BarChart3,
  CalendarDays,
  CheckCircle2,
  ClipboardList,
  LayoutDashboard,
  Menu,
  RefreshCw,
  Route,
  UsersRound,
  X,
} from '@lucide/vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { createOperationsGateway, isDemoMode } from './api/client'
import LoadingOrders from './components/LoadingOrders.vue'
import MetricCard from './components/MetricCard.vue'
import OrderDetailDrawer from './components/OrderDetailDrawer.vue'
import OrderFilters from './components/OrderFilters.vue'
import OrderList from './components/OrderList.vue'
import StatePanel from './components/StatePanel.vue'
import StatusOverview from './components/StatusOverview.vue'
import type {
  DashboardData,
  StatusBreakdown,
  WorkOrder,
  WorkOrderDetail,
  WorkOrderFilters,
  WorkOrderPriority,
  WorkOrderStatus,
} from './types/operations'
import { filterWorkOrders, formatRelativeTime, shortOrderId } from './utils/orders'

const gateway = createOperationsGateway()
const dashboard = ref<DashboardData | null>(null)
const orders = ref<WorkOrder[]>([])
const loading = ref(true)
const error = ref('')
const updatedAt = ref<Date | null>(null)
const mobileMenuOpen = ref(false)

const filters = reactive<WorkOrderFilters>({ q: '', status: 'all', priority: 'all' })
const filteredOrders = computed(() => filterWorkOrders(orders.value, filters))
const hasFilters = computed(() => Boolean(filters.q || filters.status !== 'all' || filters.priority !== 'all'))

const selectedId = ref<string | null>(null)
const selectedLabel = ref('')
const detail = ref<WorkOrderDetail | null>(null)
const detailLoading = ref(false)
const detailError = ref('')

const blankBreakdown: StatusBreakdown = {
  pending: 0,
  assigned: 0,
  en_route: 0,
  in_progress: 0,
  completed: 0,
  cancelled: 0,
}

const completionRate = computed(() => {
  if (!dashboard.value?.summary.total) return '0%'
  return `${Math.round((dashboard.value.summary.completed / dashboard.value.summary.total) * 100)}%`
})

const todayLabel = new Intl.DateTimeFormat('es-PE', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
}).format(new Date())

async function loadOperations(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const [dashboardResponse, ordersResponse] = await Promise.all([
      gateway.getDashboard(),
      gateway.listWorkOrders(),
    ])
    dashboard.value = dashboardResponse
    orders.value = ordersResponse.data
    updatedAt.value = new Date()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'No fue posible cargar el centro de operaciones.'
  } finally {
    loading.value = false
  }
}

async function openDetail(order: WorkOrder): Promise<void> {
  selectedId.value = order.id
  selectedLabel.value = shortOrderId(order.id)
  detail.value = null
  detailError.value = ''
  detailLoading.value = true
  try {
    const [selectedOrder, evidences, timeline] = await Promise.all([
      gateway.getWorkOrder(order.id),
      gateway.getEvidences(order.id),
      gateway.getTimeline(order.id),
    ])
    if (selectedId.value === order.id) detail.value = { order: selectedOrder, evidences, timeline }
  } catch (caught) {
    detailError.value = caught instanceof Error ? caught.message : 'No fue posible cargar esta orden.'
  } finally {
    detailLoading.value = false
  }
}

function closeDetail(): void {
  selectedId.value = null
  detail.value = null
  detailError.value = ''
}

function retryDetail(): void {
  const order = orders.value.find((candidate) => candidate.id === selectedId.value)
  if (order) void openDetail(order)
}

function clearFilters(): void {
  filters.q = ''
  filters.status = 'all'
  filters.priority = 'all'
}

function navigateTo(id: string): void {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  mobileMenuOpen.value = false
}

onMounted(() => void loadOperations())
</script>

<template>
  <div class="app-shell">
    <a class="skip-link" href="#main-content">Saltar al contenido</a>
    <aside id="main-sidebar" class="sidebar" :class="{ 'sidebar--open': mobileMenuOpen }" aria-label="Navegación principal">
      <div class="brand">
        <span class="brand__mark"><Route :size="22" :stroke-width="2" aria-hidden="true" /></span>
        <span><strong>FieldOps</strong><small>Service platform</small></span>
        <button class="sidebar__close" type="button" aria-label="Cerrar menú" @click="mobileMenuOpen = false">
          <X :size="20" />
        </button>
      </div>

      <nav class="side-nav">
        <span class="side-nav__label">Operaciones</span>
        <button
          class="side-nav__item side-nav__item--active"
          type="button"
          aria-current="page"
          @click="navigateTo('overview')"
        >
          <LayoutDashboard :size="19" aria-hidden="true" /> Resumen
        </button>
        <button class="side-nav__item" type="button" @click="navigateTo('orders')">
          <ClipboardList :size="19" aria-hidden="true" /> Órdenes
          <span v-if="dashboard" class="side-nav__count">{{ dashboard.summary.open }}</span>
        </button>
        <button class="side-nav__item" type="button" @click="navigateTo('team')">
          <UsersRound :size="19" aria-hidden="true" /> Equipo en campo
        </button>
      </nav>

      <section id="team" class="team-card" aria-labelledby="team-title">
        <span class="team-card__pulse" aria-hidden="true" />
        <span>
          <strong id="team-title">4 técnicos activos</strong>
          <small>3 zonas cubiertas ahora</small>
        </span>
        <div class="avatar-stack" aria-hidden="true">
          <span>LR</span><span>MT</span><span>DM</span><span>AV</span>
        </div>
      </section>

      <div class="sidebar__footer">
        <span class="avatar">CO</span>
        <span><strong>Carla Ortiz</strong><small>Coordinación</small></span>
        <span class="online-dot" title="En línea" />
      </div>
    </aside>

    <button v-if="mobileMenuOpen" class="mobile-overlay" type="button" aria-label="Cerrar menú" @click="mobileMenuOpen = false" />

    <main id="main-content" class="main-content" tabindex="-1">
      <header class="topbar">
        <button
          class="menu-button"
          type="button"
          aria-label="Abrir menú"
          aria-controls="main-sidebar"
          :aria-expanded="mobileMenuOpen"
          @click="mobileMenuOpen = true"
        >
          <Menu :size="21" />
        </button>
        <div>
          <span class="topbar__context"><span class="live-dot" /> Centro de operaciones</span>
          <h1>Buenos días, Carla</h1>
          <p>{{ todayLabel.charAt(0).toUpperCase() + todayLabel.slice(1) }}</p>
        </div>
        <div class="topbar__actions">
          <span class="source-chip" :class="{ 'source-chip--demo': isDemoMode }">
            <span /> {{ isDemoMode ? 'Datos demo' : 'API conectada' }}
          </span>
          <span v-if="updatedAt" class="last-update">Actualizado {{ formatRelativeTime(updatedAt) }}</span>
          <button class="button button--secondary" type="button" :disabled="loading" @click="loadOperations">
            <RefreshCw :size="17" :class="{ spin: loading }" aria-hidden="true" /> Actualizar
          </button>
        </div>
      </header>

      <div id="overview" class="content-area">
        <StatePanel
          v-if="error && !loading"
          kind="error"
          title="No pudimos cargar las operaciones"
          :description="error"
          @action="loadOperations"
        />

        <template v-else>
          <section v-if="isDemoMode && !loading" class="demo-notice" aria-label="Entorno de demostración">
            <span class="demo-notice__mark" aria-hidden="true"><span /></span>
            <div>
              <strong>Explora el flujo con datos de demostración</strong>
              <p>Las órdenes, evidencias e indicadores son locales y no modifican información real.</p>
            </div>
            <span class="demo-notice__tag">Modo demo</span>
          </section>

          <section class="metrics-grid" aria-label="Indicadores operativos">
            <template v-if="loading">
              <article v-for="index in 4" :key="index" class="metric-card metric-card--loading">
                <span class="skeleton skeleton--wide" /><span class="skeleton skeleton--metric" /><span class="skeleton skeleton--medium" />
              </article>
            </template>
            <template v-else-if="dashboard">
              <MetricCard label="En operación" :value="dashboard.summary.open" helper="Órdenes activas" :icon="Activity" />
              <MetricCard label="Programadas hoy" :value="dashboard.summary.due_today" helper="Incluye completadas" :icon="CalendarDays" />
              <MetricCard label="Atención urgente" :value="dashboard.summary.urgent_open" helper="Requieren seguimiento" :icon="AlertTriangle" tone="attention" />
              <MetricCard label="Completadas" :value="completionRate" :helper="`${dashboard.summary.completed} del total`" :icon="CheckCircle2" tone="success" />
            </template>
          </section>

          <div class="overview-grid" :aria-busy="loading">
            <template v-if="loading">
              <article v-for="index in 2" :key="`overview-${index}`" class="panel overview-loading" aria-hidden="true">
                <span class="skeleton skeleton--medium" />
                <span class="skeleton skeleton--heading" />
                <span class="skeleton skeleton--block" />
              </article>
            </template>
            <template v-else>
              <StatusOverview
                :breakdown="dashboard?.by_status ?? blankBreakdown"
                :total="dashboard?.summary.total ?? 0"
              />

              <article class="panel dispatch-card">
                <div class="panel__heading">
                  <div><span class="eyebrow">Despacho</span><h2>Capacidad del equipo</h2></div>
                  <BarChart3 :size="20" aria-hidden="true" />
                </div>
                <div class="dispatch-card__body">
                  <div class="dispatch-stat">
                    <strong>75%</strong><span>Utilización actual</span>
                  </div>
                  <div class="dispatch-progress" role="progressbar" aria-label="Utilización del equipo" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">
                    <span />
                  </div>
                  <dl class="dispatch-grid">
                    <div><dt>Disponibles</dt><dd>1</dd></div><div><dt>En ruta</dt><dd>{{ dashboard?.by_status.en_route ?? 0 }}</dd></div><div><dt>En servicio</dt><dd>{{ dashboard?.by_status.in_progress ?? 0 }}</dd></div>
                  </dl>
                </div>
              </article>
            </template>
          </div>

          <section id="orders" class="panel orders-panel" aria-labelledby="orders-heading">
            <div class="panel__heading orders-panel__heading">
              <div>
                <span class="eyebrow">Operación diaria</span>
                <h2 id="orders-heading">
                  Órdenes de servicio
                </h2>
              </div>
              <span v-if="!loading" class="panel__caption">{{ orders.length }} registradas</span>
            </div>

            <OrderFilters
              :filters="filters"
              :result-count="filteredOrders.length"
              @update:q="filters.q = $event"
              @update:status="filters.status = $event as WorkOrderStatus | 'all'"
              @update:priority="filters.priority = $event as WorkOrderPriority | 'all'"
              @clear="clearFilters"
            />

            <LoadingOrders v-if="loading" />
            <OrderList v-else-if="filteredOrders.length" :orders="filteredOrders" @select="openDetail" />
            <StatePanel
              v-else
              kind="empty"
              :title="hasFilters ? 'No hay coincidencias' : 'Todavía no hay órdenes'"
              :description="hasFilters ? 'Prueba con otro término o elimina alguno de los filtros.' : 'Las nuevas órdenes aparecerán aquí cuando sean registradas.'"
              @action="hasFilters ? clearFilters() : loadOperations()"
            >
              {{ hasFilters ? 'Limpiar filtros' : 'Actualizar lista' }}
            </StatePanel>
          </section>
        </template>
      </div>
    </main>

    <OrderDetailDrawer
      v-if="selectedId"
      :detail="detail"
      :loading="detailLoading"
      :error="detailError"
      :fallback-title="selectedLabel"
      @close="closeDetail"
      @retry="retryDetail"
    />
  </div>
</template>
