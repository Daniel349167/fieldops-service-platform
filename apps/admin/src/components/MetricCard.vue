<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  label: string
  value: string | number
  helper: string
  icon: Component
  tone?: 'default' | 'attention' | 'success'
}>()
</script>

<template>
  <article class="metric-card" :class="tone ? `metric-card--${tone}` : undefined">
    <div class="metric-card__topline">
      <span class="metric-card__label">{{ label }}</span>
      <span class="metric-card__icon" aria-hidden="true"><component :is="icon" :size="18" :stroke-width="1.8" /></span>
    </div>
    <strong class="metric-card__value">{{ value }}</strong>
    <span class="metric-card__helper">{{ helper }}</span>
  </article>
</template>
