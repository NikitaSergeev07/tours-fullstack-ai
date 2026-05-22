<script setup lang="ts">
import { computed, ref } from 'vue'
import { useData } from '../../composables/useData'
import FilterPanel from '../../components/FilterPanel.vue'
import TourCard from '../../components/TourCard.vue'
import SearchBox from '../../components/SearchBox.vue'
import EmptyState from '../../components/EmptyState.vue'
import type { IndexData } from './+data'

const data = useData<IndexData>()

const query = ref<Record<string, string | string[]>>({ ...data.query })

const semantic = computed(() => typeof query.value.q === 'string' && query.value.q.trim().length > 0)

function applyQuery(next: Record<string, string | string[]>) {
  query.value = next
  const qs = new URLSearchParams()
  for (const [k, v] of Object.entries(next)) {
    if (Array.isArray(v)) v.forEach(x => qs.append(`${k}[]`, String(x)))
    else if (v !== undefined && v !== null && v !== '') qs.append(k, String(v))
  }
  const search = qs.toString()
  // Full navigation so SSR re-runs and the URL stays shareable.
  window.location.search = search ? '?' + search : ''
}
</script>

<template>
  <section class="relative overflow-hidden border-b border-surface-200">
    <div class="absolute inset-0 -z-10 bg-gradient-to-br from-brand-50 via-surface-0 to-surface-50"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20">
      <div class="max-w-3xl">
        <p class="text-brand-700 font-medium text-sm uppercase tracking-wider">Tours · Каталог</p>
        <h1 class="mt-2 text-4xl lg:text-5xl font-display font-semibold tracking-tight">
          Найдите свой тур —
          <span class="text-brand-700">по смыслу</span>, а не по словам
        </h1>
        <p class="mt-4 text-surface-600 text-lg">
          Семантический поиск, гибкие фильтры и маршрут на карте. Опишите идеальное
          путешествие своими словами — наша модель подберёт совпадения, даже если в
          названии тура нет ваших ключевых слов.
        </p>
        <SearchBox class="mt-8" :initial="String(query.q || '')" @search="(q) => applyQuery({ ...query, q })" />
      </div>
    </div>
  </section>

  <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-8">
    <aside class="lg:sticky lg:top-20 self-start">
      <FilterPanel :filters="data.filters" :query="query" @change="applyQuery" />
    </aside>

    <div class="min-h-[60vh]">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
          <h2 class="text-xl font-semibold">
            {{ semantic ? 'Семантическая выдача' : 'Туры в каталоге' }}
          </h2>
          <p class="text-sm text-surface-500">
            Найдено: {{ data.pagination.total }}{{ semantic ? ' · сортировка по релевантности' : '' }}
          </p>
        </div>
        <select
          v-if="!semantic"
          class="border border-surface-200 rounded-lg px-3 py-2 text-sm bg-surface-0"
          :value="String(query.sort || 'newest')"
          @change="(e) => applyQuery({ ...query, sort: (e.target as HTMLSelectElement).value })"
        >
          <option v-for="opt in data.filters.sort_options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <div v-if="data.tours.length === 0">
        <EmptyState
          title="Подходящих туров не найдено"
          subtitle="Попробуйте сбросить фильтры или сформулировать поиск иначе. Например: «зимний поход в горы с термами» или «гастро-выходные на юге»."
        />
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <TourCard
          v-for="tour in data.tours"
          :key="tour.slug"
          :tour="tour"
          :show-score="semantic"
        />
      </div>
    </div>
  </section>
</template>
