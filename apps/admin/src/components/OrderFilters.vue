<script setup lang="ts">
import { Search, SlidersHorizontal, X } from '@lucide/vue'
import type { WorkOrderFilters, WorkOrderPriority, WorkOrderStatus } from '../types/operations'
import { PRIORITY_LABELS, STATUS_LABELS } from '../utils/orders'

defineProps<{ filters: WorkOrderFilters; resultCount: number }>()

const emit = defineEmits<{
  'update:q': [value: string]
  'update:status': [value: WorkOrderStatus | 'all']
  'update:priority': [value: WorkOrderPriority | 'all']
  clear: []
}>()
</script>

<template>
  <div class="filters" aria-label="Filtros de órdenes">
    <div class="filters__search">
      <Search :size="18" aria-hidden="true" />
      <label class="sr-only" for="order-search">Buscar órdenes</label>
      <input
        id="order-search"
        :value="filters.q"
        type="search"
        placeholder="Buscar por orden, cliente o técnico…"
        autocomplete="off"
        @input="emit('update:q', ($event.target as HTMLInputElement).value)"
      >
    </div>

    <div class="filters__select-wrap">
      <SlidersHorizontal :size="16" aria-hidden="true" />
      <label class="sr-only" for="status-filter">Filtrar por estado</label>
      <select
        id="status-filter"
        :value="filters.status"
        @change="emit('update:status', ($event.target as HTMLSelectElement).value as WorkOrderStatus | 'all')"
      >
        <option value="all">
          Todos los estados
        </option>
        <option v-for="(label, value) in STATUS_LABELS" :key="value" :value="value">
          {{ label }}
        </option>
      </select>
    </div>

    <div class="filters__select-wrap">
      <label class="sr-only" for="priority-filter">Filtrar por prioridad</label>
      <select
        id="priority-filter"
        :value="filters.priority"
        @change="emit('update:priority', ($event.target as HTMLSelectElement).value as WorkOrderPriority | 'all')"
      >
        <option value="all">
          Toda prioridad
        </option>
        <option v-for="(label, value) in PRIORITY_LABELS" :key="value" :value="value">
          {{ label }}
        </option>
      </select>
    </div>

    <button
      v-if="filters.q || filters.status !== 'all' || filters.priority !== 'all'"
      class="button button--quiet filters__clear"
      type="button"
      @click="emit('clear')"
    >
      <X :size="16" aria-hidden="true" /> Limpiar
    </button>
    <span class="filters__count" aria-live="polite">{{ resultCount }} resultado{{ resultCount === 1 ? '' : 's' }}</span>
  </div>
</template>
