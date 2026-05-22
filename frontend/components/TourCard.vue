<script setup lang="ts">
import { computed } from 'vue'
import Badge from 'primevue/badge'
import type { TourSummary } from '../api/client'

const props = defineProps<{
  tour: TourSummary
  showScore?: boolean
}>()

const difficulty = computed(() => {
  switch (props.tour.difficulty) {
    case 'easy': return { label: 'Лёгкий', class: 'bg-emerald-50 text-emerald-700' }
    case 'moderate': return { label: 'Средний', class: 'bg-amber-50 text-amber-700' }
    case 'hard': return { label: 'Сложный', class: 'bg-rose-50 text-rose-700' }
    default: return { label: props.tour.difficulty, class: 'bg-surface-100 text-surface-700' }
  }
})

const priceLabel = computed(() => {
  if (props.tour.price_from == null) return 'По запросу'
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency', currency: 'RUB', maximumFractionDigits: 0,
  }).format(props.tour.price_from)
})

const cover = computed(() => props.tour.cover_image || props.tour.photos?.[0]?.url || '/placeholder-cover.jpg')
</script>

<template>
  <a :href="`/tour/${tour.slug}`" class="t-card group flex flex-col overflow-hidden focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
    <div class="aspect-[4/3] overflow-hidden bg-surface-100 relative">
      <img
        :src="cover"
        :alt="tour.title"
        loading="lazy"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
      />
      <span :class="['absolute top-3 left-3 px-2.5 py-1 rounded-full text-xs font-medium', difficulty.class]">
        {{ difficulty.label }}
      </span>
      <span v-if="showScore && tour.score != null"
            class="absolute top-3 right-3 px-2.5 py-1 rounded-full text-xs font-medium bg-surface-900/80 text-white">
        match {{ Math.round(tour.score * 100) }}%
      </span>
    </div>

    <div class="p-5 flex-1 flex flex-col">
      <div class="flex items-center gap-1.5 mb-2 text-xs text-surface-500">
        <template v-for="(c, i) in tour.categories || []" :key="c.slug">
          <span class="font-medium">{{ c.name }}</span>
          <span v-if="i < (tour.categories?.length || 1) - 1">·</span>
        </template>
      </div>
      <h3 class="text-lg font-semibold leading-snug group-hover:text-brand-700 transition">
        {{ tour.title }}
      </h3>
      <p class="mt-2 text-sm text-surface-600 line-clamp-3">
        {{ tour.short_description }}
      </p>
      <div class="mt-auto pt-4 flex items-end justify-between border-t border-surface-100 mt-4">
        <div>
          <p class="text-xs text-surface-500">от</p>
          <p class="text-lg font-semibold">{{ priceLabel }}</p>
        </div>
        <Badge :value="`${tour.duration_days} дн.`" severity="secondary" />
      </div>
    </div>
  </a>
</template>
