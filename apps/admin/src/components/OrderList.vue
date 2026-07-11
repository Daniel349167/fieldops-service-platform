<script setup lang="ts">
import { ArrowUpRight, CalendarClock, MapPin, UserRound } from '@lucide/vue'
import type { WorkOrder } from '../types/operations'
import { formatDateTime, shortOrderId } from '../utils/orders'
import StatusBadge from './StatusBadge.vue'

defineProps<{ orders: WorkOrder[] }>()
const emit = defineEmits<{ select: [order: WorkOrder] }>()
</script>

<template>
  <div class="order-list">
    <div class="order-table-wrap">
      <table class="order-table">
        <caption class="sr-only">
          Órdenes de servicio y su situación operativa
        </caption>
        <thead>
          <tr>
            <th scope="col">
              Orden
            </th>
            <th scope="col">
              Cliente / ubicación
            </th>
            <th scope="col">
              Técnico
            </th>
            <th scope="col">
              Programación
            </th>
            <th scope="col">
              Prioridad
            </th>
            <th scope="col">
              Estado
            </th>
            <th scope="col">
              <span class="sr-only">Ver detalle</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>
              <button class="order-link" type="button" @click="emit('select', order)">
                <strong>{{ shortOrderId(order.id) }}</strong>
                <span>{{ order.title }}</span>
              </button>
            </td>
            <td>
              <div class="cell-stack">
                <strong>{{ order.customer.name }}</strong>
                <span><MapPin :size="13" aria-hidden="true" /> {{ order.address.district }}</span>
              </div>
            </td>
            <td>
              <div v-if="order.assigned_technician" class="technician-cell">
                <span class="avatar avatar--small">{{ order.assigned_technician.name.split(' ').map((part) => part[0]).slice(0, 2).join('') }}</span>
                <span>{{ order.assigned_technician.name }}</span>
              </div>
              <span v-else class="muted-cell">Sin asignar</span>
            </td>
            <td>
              <time :datetime="order.scheduled_at">{{ formatDateTime(order.scheduled_at) }}</time>
            </td>
            <td><StatusBadge kind="priority" :value="order.priority" /></td>
            <td><StatusBadge kind="status" :value="order.status" /></td>
            <td>
              <button class="icon-button" type="button" :aria-label="`Ver ${shortOrderId(order.id)}`" @click="emit('select', order)">
                <ArrowUpRight :size="18" aria-hidden="true" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="order-cards">
      <article v-for="order in orders" :key="order.id" class="order-card">
        <button class="order-card__button" type="button" @click="emit('select', order)">
          <span class="order-card__top">
            <span>
              <span class="order-card__id">{{ shortOrderId(order.id) }}</span>
              <strong>{{ order.title }}</strong>
            </span>
            <ArrowUpRight :size="19" aria-hidden="true" />
          </span>
          <span class="order-card__customer">{{ order.customer.name }}</span>
          <span class="order-card__meta"><MapPin :size="15" aria-hidden="true" /> {{ order.address.district }}</span>
          <span class="order-card__meta"><CalendarClock :size="15" aria-hidden="true" /> {{ formatDateTime(order.scheduled_at) }}</span>
          <span class="order-card__meta"><UserRound :size="15" aria-hidden="true" /> {{ order.assigned_technician?.name ?? 'Sin asignar' }}</span>
          <span class="order-card__badges">
            <StatusBadge kind="priority" :value="order.priority" />
            <StatusBadge kind="status" :value="order.status" />
          </span>
        </button>
      </article>
    </div>
  </div>
</template>
