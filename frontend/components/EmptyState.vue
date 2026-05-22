<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>()
</script>

<template>
  <div class="t-card p-10 text-center max-w-xl mx-auto">
    <div class="text-5xl mb-4">🧭</div>
    <h3 class="text-lg font-semibold mb-2">{{ title }}</h3>
    <p v-if="subtitle" class="text-sm text-surface-600">{{ subtitle }}</p>
    <a href="/" class="inline-flex mt-5 items-center gap-1 text-brand-700 hover:underline text-sm">
      <span class="pi pi-arrow-left"></span>
      Назад к каталогу
    </a>
  </div>
</template>
