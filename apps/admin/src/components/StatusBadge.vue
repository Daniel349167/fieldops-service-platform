<script setup lang="ts">
import { computed } from 'vue'
import type { WorkOrderPriority, WorkOrderStatus } from '../types/operations'
import { PRIORITY_LABELS, STATUS_LABELS } from '../utils/orders'

const props = defineProps<
  | { kind: 'status'; value: WorkOrderStatus }
  | { kind: 'priority'; value: WorkOrderPriority }
>()

const label = computed(() =>
  props.kind === 'status' ? STATUS_LABELS[props.value] : PRIORITY_LABELS[props.value],
)
</script>

<template>
  <span class="badge" :class="[`badge--${kind}`, `badge--${value}`]">
    <span class="badge__dot" aria-hidden="true" />
    {{ label }}
  </span>
</template>
