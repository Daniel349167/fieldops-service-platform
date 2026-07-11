<template>
  <div class="loading-orders" aria-live="polite" aria-busy="true">
    <span class="sr-only">Cargando órdenes de servicio…</span>
    <div v-for="index in 5" :key="index" class="loading-orders__row">
      <span class="skeleton skeleton--main" />
      <span class="skeleton skeleton--medium" />
      <span class="skeleton skeleton--medium" />
      <span class="skeleton skeleton--short" />
      <span class="skeleton skeleton--short" />
    </div>
  </div>
</template>
