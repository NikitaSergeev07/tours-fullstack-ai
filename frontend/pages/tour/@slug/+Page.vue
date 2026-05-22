<script setup lang="ts">
import { computed, ref } from 'vue'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import PhotoGallery from '../../../components/PhotoGallery.vue'
import YandexMap from '../../../components/YandexMap.vue'
import DateOptions from '../../../components/DateOptions.vue'
import { useData } from '../../../composables/useData'
import type { TourDetail } from '../../../api/client'

const tour = useData<TourDetail>()

const formattedPriceFrom = computed(() => tour.price_from
  ? new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(tour.price_from)
  : 'По запросу'
)

const difficulty = computed(() => {
  return ({ easy: 'Лёгкий', moderate: 'Средний', hard: 'Сложный' } as const)[tour.difficulty] || tour.difficulty
})

const photos = computed(() => {
  if (tour.photos?.length) return tour.photos
  return tour.cover_image ? [{ url: tour.cover_image, alt: tour.title }] : []
})

const selectedDateId = ref<number | null>(tour.dates?.[0]?.id ?? null)
const selectedDate = computed(() => tour.dates?.find(d => d.id === selectedDateId.value) || null)
</script>

<template>
  <article class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <nav class="text-sm mb-5">
      <a href="/" class="text-surface-500 hover:text-brand-700 inline-flex items-center gap-1">
        <span class="pi pi-arrow-left text-xs"></span>
        Все туры
      </a>
    </nav>

    <header class="grid lg:grid-cols-2 gap-8 mb-10">
      <PhotoGallery :photos="photos" :title="tour.title" />
      <div>
        <div class="flex flex-wrap gap-1.5 mb-3">
          <Tag v-for="c in tour.categories || []" :key="c.slug" :value="c.name" severity="secondary" />
        </div>
        <h1 class="text-3xl lg:text-4xl font-display font-semibold leading-tight">{{ tour.title }}</h1>
        <p class="mt-4 text-lg text-surface-700">{{ tour.short_description }}</p>

        <dl class="mt-6 grid grid-cols-3 gap-3">
          <div class="t-card p-4">
            <dt class="text-xs text-surface-500 uppercase">Длительность</dt>
            <dd class="text-lg font-semibold mt-1">
              {{ tour.duration_days }} <span class="text-surface-500 text-sm font-normal">дн.</span>
            </dd>
          </div>
          <div class="t-card p-4">
            <dt class="text-xs text-surface-500 uppercase">Сложность</dt>
            <dd class="text-lg font-semibold mt-1">{{ difficulty }}</dd>
          </div>
          <div class="t-card p-4">
            <dt class="text-xs text-surface-500 uppercase">От</dt>
            <dd class="text-lg font-semibold mt-1">{{ formattedPriceFrom }}</dd>
          </div>
        </dl>

        <section v-if="tour.highlights?.length" class="mt-6">
          <h3 class="font-semibold text-base mb-2">Что вас ждёт</h3>
          <ul class="space-y-1.5 text-surface-700">
            <li v-for="h in tour.highlights" :key="h" class="flex gap-2">
              <span class="pi pi-check-circle text-brand-600 mt-1 text-sm"></span>
              <span>{{ h }}</span>
            </li>
          </ul>
        </section>
      </div>
    </header>

    <section class="grid lg:grid-cols-[1fr_360px] gap-8">
      <div class="space-y-10">
        <div class="t-card p-6">
          <h2 class="text-xl font-semibold mb-3">Описание</h2>
          <div class="prose max-w-none text-surface-800 whitespace-pre-line" v-html="tour.description"></div>
        </div>

        <div class="t-card p-6">
          <h2 class="text-xl font-semibold mb-3">Маршрут</h2>
          <YandexMap :points="tour.route.points" :center="tour.route.center" />
          <ol v-if="tour.route.points?.length" class="mt-5 grid sm:grid-cols-2 gap-2 text-sm">
            <li v-for="(p, i) in tour.route.points" :key="i" class="flex items-start gap-2">
              <span class="inline-flex w-6 h-6 rounded-full bg-brand-50 border border-brand-200 text-brand-700 items-center justify-center text-xs font-semibold flex-shrink-0">{{ i + 1 }}</span>
              <span>{{ p.label || `${p.lat.toFixed(3)}, ${p.lon.toFixed(3)}` }}</span>
            </li>
          </ol>
        </div>
      </div>

      <aside class="lg:sticky lg:top-20 self-start">
        <div class="t-card p-6">
          <h3 class="font-semibold text-base mb-3">Даты и цены</h3>
          <DateOptions :dates="tour.dates" v-model:selected="selectedDateId" />
          <Button
            v-if="selectedDate"
            class="w-full mt-4"
            severity="primary"
            :label="`Хочу в этот тур · ${new Intl.NumberFormat('ru-RU', { style: 'currency', currency: selectedDate.currency, maximumFractionDigits: 0 }).format(selectedDate.price)}`"
            icon="pi pi-send"
          />
          <p class="mt-3 text-xs text-surface-500">
            Демо-каталог: бронирование не реализовано по условиям задания.
          </p>
        </div>
      </aside>
    </section>
  </article>
</template>
