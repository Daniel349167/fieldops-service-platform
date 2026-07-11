<script setup lang="ts">
import { CircleOff, CloudOff, RefreshCw } from '@lucide/vue'

withDefaults(
  defineProps<{
    kind: 'empty' | 'error'
    title: string
    description: string
    actionLabel?: string
  }>(),
  { actionLabel: 'Reintentar' },
)

const emit = defineEmits<{ action: [] }>()
</script>

<template>
  <div class="state-panel" :role="kind === 'error' ? 'alert' : 'status'">
    <span class="state-panel__icon" aria-hidden="true">
      <CloudOff v-if="kind === 'error'" :size="26" />
      <CircleOff v-else :size="26" />
    </span>
    <h3>{{ title }}</h3>
    <p>{{ description }}</p>
    <button v-if="kind === 'error'" class="button button--secondary" type="button" @click="emit('action')">
      <RefreshCw :size="16" aria-hidden="true" /> {{ actionLabel }}
    </button>
    <button v-else-if="$slots.default" class="button button--quiet" type="button" @click="emit('action')">
      <slot />
    </button>
  </div>
</template>
