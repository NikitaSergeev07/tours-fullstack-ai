<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Slider from 'primevue/slider'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import type { FilterOptions } from '../api/client'

const props = defineProps<{
  filters: FilterOptions
  query: Record<string, string | string[]>
}>()

const emit = defineEmits<{ (e: 'change', next: Record<string, string | string[]>): void }>()

const local = ref({
  categories: Array.isArray(props.query.categories) ? props.query.categories : (props.query.categories ? [String(props.query.categories)] : []),
  difficulty: String(props.query.difficulty || ''),
  duration: [
    Number(props.query.duration_min || props.filters.duration.min),
    Number(props.query.duration_max || props.filters.duration.max),
  ] as [number, number],
  price: [
    Number(props.query.price_min || props.filters.price.min),
    Number(props.query.price_max || props.filters.price.max),
  ] as [number, number],
  date_from: (props.query.date_from as string) || '',
  date_to: (props.query.date_to as string) || '',
})

const hasActive = computed(() =>
  local.value.categories.length > 0
  || local.value.difficulty
  || local.value.duration[0] !== props.filters.duration.min
  || local.value.duration[1] !== props.filters.duration.max
  || local.value.price[0] !== props.filters.price.min
  || local.value.price[1] !== props.filters.price.max
  || local.value.date_from
  || local.value.date_to
)

function toggleCategory(slug: string) {
  const i = local.value.categories.indexOf(slug)
  if (i === -1) local.value.categories.push(slug)
  else local.value.categories.splice(i, 1)
}

function apply() {
  const next: Record<string, string | string[]> = { ...props.query }
  next.categories = local.value.categories
  if (local.value.difficulty) next.difficulty = local.value.difficulty; else delete next.difficulty
  if (local.value.duration[0] !== props.filters.duration.min) next.duration_min = String(local.value.duration[0]); else delete next.duration_min
  if (local.value.duration[1] !== props.filters.duration.max) next.duration_max = String(local.value.duration[1]); else delete next.duration_max
  if (local.value.price[0] !== props.filters.price.min) next.price_min = String(local.value.price[0]); else delete next.price_min
  if (local.value.price[1] !== props.filters.price.max) next.price_max = String(local.value.price[1]); else delete next.price_max
  if (local.value.date_from) next.date_from = local.value.date_from; else delete next.date_from
  if (local.value.date_to) next.date_to = local.value.date_to; else delete next.date_to
  emit('change', next)
}

function reset() {
  local.value = {
    categories: [],
    difficulty: '',
    duration: [props.filters.duration.min, props.filters.duration.max],
    price: [props.filters.price.min, props.filters.price.max],
    date_from: '',
    date_to: '',
  }
  emit('change', { q: props.query.q })
}

const priceFmt = (v: number) => new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(v)
</script>

<template>
  <div class="space-y-6 t-card p-5">
    <header class="flex items-center justify-between">
      <h3 class="font-semibold text-base">Фильтры</h3>
      <button
        v-if="hasActive"
        type="button"
        class="text-xs text-brand-700 hover:underline"
        @click="reset"
      >Сбросить</button>
    </header>

    <section>
      <p class="text-xs font-medium text-surface-500 uppercase mb-2">Категории</p>
      <ul class="flex flex-wrap gap-2">
        <li v-for="c in filters.categories" :key="c.slug">
          <button
            type="button"
            :class="[
              'px-3 py-1.5 rounded-full border text-sm transition',
              local.categories.includes(c.slug)
                ? 'border-brand-500 bg-brand-50 text-brand-800'
                : 'border-surface-200 hover:bg-surface-100'
            ]"
            @click="toggleCategory(c.slug)"
          >{{ c.name }}</button>
        </li>
      </ul>
    </section>

    <section>
      <p class="text-xs font-medium text-surface-500 uppercase mb-2">Сложность</p>
      <div class="flex gap-2">
        <button
          v-for="d in filters.difficulties"
          :key="d.value"
          type="button"
          :class="[
            'flex-1 px-2.5 py-1.5 rounded-lg border text-sm',
            local.difficulty === d.value
              ? 'border-brand-500 bg-brand-50 text-brand-800'
              : 'border-surface-200 hover:bg-surface-100'
          ]"
          @click="local.difficulty = local.difficulty === d.value ? '' : d.value"
        >{{ d.label }}</button>
      </div>
    </section>

    <section>
      <p class="text-xs font-medium text-surface-500 uppercase mb-2 flex justify-between">
        <span>Длительность (дни)</span>
        <span class="text-surface-700">{{ local.duration[0] }}–{{ local.duration[1] }}</span>
      </p>
      <Slider v-model="local.duration" range :min="filters.duration.min" :max="filters.duration.max" />
    </section>

    <section>
      <p class="text-xs font-medium text-surface-500 uppercase mb-2 flex justify-between">
        <span>Цена, ₽</span>
        <span class="text-surface-700">{{ priceFmt(local.price[0]) }}–{{ priceFmt(local.price[1]) }}</span>
      </p>
      <Slider v-model="local.price" range :min="filters.price.min" :max="filters.price.max" :step="1000" />
    </section>

    <section class="grid grid-cols-2 gap-3">
      <div>
        <p class="text-xs font-medium text-surface-500 uppercase mb-1">С даты</p>
        <DatePicker v-model="local.date_from" dateFormat="yy-mm-dd" showIcon fluid />
      </div>
      <div>
        <p class="text-xs font-medium text-surface-500 uppercase mb-1">По дату</p>
        <DatePicker v-model="local.date_to" dateFormat="yy-mm-dd" showIcon fluid />
      </div>
    </section>

    <Button label="Применить" icon="pi pi-check" class="w-full" severity="primary" @click="apply" />
  </div>
</template>
