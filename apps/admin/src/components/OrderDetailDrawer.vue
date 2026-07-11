<script setup lang="ts">
import {
  CalendarClock,
  CheckCircle2,
  ChevronRight,
  FileImage,
  Mail,
  MapPin,
  Phone,
  RefreshCw,
  UserRound,
  X,
} from '@lucide/vue'
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import type { WorkOrderDetail, WorkOrderStatus } from '../types/operations'
import { formatDateTime, shortOrderId, STATUS_LABELS } from '../utils/orders'
import StatusBadge from './StatusBadge.vue'

defineProps<{
  detail: WorkOrderDetail | null
  loading: boolean
  error: string
  fallbackTitle?: string
}>()

const emit = defineEmits<{ close: []; retry: [] }>()
const closeButton = ref<HTMLButtonElement | null>(null)
const drawer = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const closeOnEscape = (event: KeyboardEvent): void => {
  if (event.key === 'Escape') emit('close')

  if (event.key !== 'Tab' || !drawer.value) return
  const focusable = Array.from(
    drawer.value.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  )
  if (!focusable.length) return

  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

const eventTitle = (eventType: string, nextStatus: WorkOrderStatus | null): string => {
  if (eventType === 'work_order_created') return 'Orden creada'
  if (eventType === 'technician_assigned') return 'Técnico asignado'
  if (eventType === 'evidence_added') return 'Evidencia registrada'
  if (nextStatus) return `Estado actualizado a ${STATUS_LABELS[nextStatus].toLocaleLowerCase('es')}`
  return 'Actividad registrada'
}

const evidenceUrl = (storagePath: string, metadataUrl?: string): string => metadataUrl || storagePath

onMounted(async () => {
  previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null
  window.addEventListener('keydown', closeOnEscape)
  document.body.classList.add('drawer-open')
  await nextTick()
  closeButton.value?.focus()
})

onUnmounted(() => {
  window.removeEventListener('keydown', closeOnEscape)
  document.body.classList.remove('drawer-open')
  previouslyFocused?.focus()
})
</script>

<template>
  <Teleport to="body">
    <div class="drawer-layer">
      <button class="drawer-backdrop" type="button" aria-label="Cerrar detalle" @click="emit('close')" />
      <aside ref="drawer" class="detail-drawer" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
        <header class="detail-drawer__header">
          <div>
            <span class="eyebrow">Detalle de servicio</span>
            <h2 id="drawer-title">
              {{ detail ? shortOrderId(detail.order.id) : (fallbackTitle ?? 'Orden de servicio') }}
            </h2>
          </div>
          <button ref="closeButton" class="icon-button icon-button--large" type="button" aria-label="Cerrar detalle" @click="emit('close')">
            <X :size="21" aria-hidden="true" />
          </button>
        </header>

        <div v-if="loading" class="detail-loading" aria-live="polite" aria-busy="true">
          <span class="sr-only">Cargando detalle de la orden…</span>
          <span class="skeleton skeleton--heading" />
          <span class="skeleton skeleton--wide" />
          <span class="skeleton skeleton--wide" />
          <span class="skeleton skeleton--block" />
          <span class="skeleton skeleton--block" />
        </div>

        <div v-else-if="error" class="drawer-error" role="alert">
          <span class="state-panel__icon" aria-hidden="true"><RefreshCw :size="25" /></span>
          <h3>No pudimos abrir la orden</h3>
          <p>{{ error }}</p>
          <button class="button button--secondary" type="button" @click="emit('retry')">
            <RefreshCw :size="16" aria-hidden="true" /> Reintentar
          </button>
        </div>

        <div v-else-if="detail" class="detail-drawer__body">
          <section class="detail-hero">
            <div class="detail-hero__badges">
              <StatusBadge kind="status" :value="detail.order.status" />
              <StatusBadge kind="priority" :value="detail.order.priority" />
            </div>
            <h3>{{ detail.order.title }}</h3>
            <p>{{ detail.order.description }}</p>
          </section>

          <section class="detail-section" aria-labelledby="service-data-title">
            <h3 id="service-data-title">
              Datos del servicio
            </h3>
            <dl class="detail-facts">
              <div>
                <dt><CalendarClock :size="17" aria-hidden="true" /> Programación</dt>
                <dd>{{ formatDateTime(detail.order.scheduled_at, { year: 'numeric' }) }}</dd>
              </div>
              <div>
                <dt><UserRound :size="17" aria-hidden="true" /> Técnico</dt>
                <dd>{{ detail.order.assigned_technician?.name ?? 'Pendiente de asignación' }}</dd>
              </div>
              <div>
                <dt><MapPin :size="17" aria-hidden="true" /> Dirección</dt>
                <dd>{{ detail.order.address.line }}, {{ detail.order.address.district }}</dd>
              </div>
            </dl>
          </section>

          <section class="detail-section" aria-labelledby="customer-title">
            <h3 id="customer-title">
              Cliente
            </h3>
            <div class="customer-card">
              <span class="avatar">{{ detail.order.customer.name.split(' ').map((part) => part[0]).slice(0, 2).join('') }}</span>
              <div>
                <strong>{{ detail.order.customer.name }}</strong>
                <span>Contacto operativo</span>
              </div>
              <div class="customer-card__actions">
                <a class="icon-button" :href="`tel:${detail.order.customer.phone}`" aria-label="Llamar al cliente"><Phone :size="17" /></a>
                <a class="icon-button" :href="`mailto:${detail.order.customer.email}`" aria-label="Escribir al cliente"><Mail :size="17" /></a>
              </div>
            </div>
          </section>

          <section class="detail-section" aria-labelledby="evidence-title">
            <div class="detail-section__heading">
              <h3 id="evidence-title">
                Evidencias
              </h3>
              <span>{{ detail.evidences.length }} archivo{{ detail.evidences.length === 1 ? '' : 's' }}</span>
            </div>
            <div v-if="detail.evidences.length" class="evidence-grid">
              <a
                v-for="evidence in detail.evidences"
                :key="evidence.id"
                class="evidence-card"
                :href="evidenceUrl(evidence.storage_path, evidence.metadata?.url)"
                target="_blank"
                rel="noreferrer"
              >
                <img
                  v-if="evidence.mime_type.startsWith('image/')"
                  :src="evidenceUrl(evidence.storage_path, evidence.metadata?.url)"
                  :alt="evidence.metadata?.label ?? evidence.file_name"
                >
                <span v-else class="evidence-card__file"><FileImage :size="24" /></span>
                <span class="evidence-card__caption">
                  <strong>{{ evidence.metadata?.label ?? evidence.file_name }}</strong>
                  <small>{{ evidence.captured_at ? formatDateTime(evidence.captured_at) : 'Sin fecha de captura' }}</small>
                </span>
              </a>
            </div>
            <div v-else class="inline-empty">
              <FileImage :size="22" aria-hidden="true" />
              <span><strong>Sin evidencias todavía</strong> Se mostrarán aquí cuando el técnico las sincronice.</span>
            </div>
          </section>

          <section class="detail-section" aria-labelledby="timeline-title">
            <div class="detail-section__heading">
              <h3 id="timeline-title">
                Historial
              </h3>
              <span>Versión {{ detail.order.version }}</span>
            </div>
            <ol class="timeline">
              <li v-for="event in detail.timeline" :key="event.id">
                <span class="timeline__marker" aria-hidden="true"><CheckCircle2 :size="15" /></span>
                <div>
                  <strong>{{ eventTitle(event.event_type, event.to_status) }}</strong>
                  <p v-if="event.note">
                    {{ event.note }}
                  </p>
                  <span>{{ event.actor?.name ?? 'FieldOps' }} · <time :datetime="event.occurred_at">{{ formatDateTime(event.occurred_at) }}</time></span>
                </div>
                <ChevronRight v-if="event.from_status && event.to_status" :size="15" aria-hidden="true" />
              </li>
            </ol>
          </section>
        </div>
      </aside>
    </div>
  </Teleport>
</template>
