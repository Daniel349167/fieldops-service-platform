<script setup lang="ts">
import type { StatusBreakdown, WorkOrderStatus } from '../types/operations'
import { STATUS_LABELS } from '../utils/orders'

const props = defineProps<{ breakdown: StatusBreakdown; total: number }>()

const statuses: WorkOrderStatus[] = ['pending', 'assigned', 'en_route', 'in_progress', 'completed', 'cancelled']
const percentage = (status: WorkOrderStatus): number =>
  props.total ? Math.max((props.breakdown[status] / props.total) * 100, props.breakdown[status] ? 3 : 0) : 0
</script>

<template>
  <article class="panel status-overview">
    <div class="panel__heading">
      <div>
        <span class="eyebrow">Flujo operativo</span>
        <h2>Estado de las órdenes</h2>
      </div>
      <span class="panel__caption">{{ total }} en total</span>
    </div>

    <div class="status-overview__bar" role="img" :aria-label="`Distribución de ${total} órdenes por estado`">
      <span
        v-for="status in statuses"
        :key="status"
        :class="`status-segment status-segment--${status}`"
        :style="{ width: `${percentage(status)}%` }"
      />
    </div>

    <ul class="status-overview__legend">
      <li v-for="status in statuses" :key="status">
        <span class="legend-dot" :class="`legend-dot--${status}`" aria-hidden="true" />
        <span>{{ STATUS_LABELS[status] }}</span>
        <strong>{{ breakdown[status] }}</strong>
      </li>
    </ul>
  </article>
</template>
