<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  dates: Array<{
    id: number
    start_date: string
    end_date: string
    price: number
    currency: string
    seats_total: number
    seats_available: number
  }>
  selected: number | null
}>()
const emit = defineEmits<{ (e: 'update:selected', id: number | null): void }>()

const upcoming = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return [...props.dates]
    .sort((a, b) => a.start_date.localeCompare(b.start_date))
    .filter(d => d.end_date >= today)
})

const formatDate = (iso: string) => new Date(iso).toLocaleDateString('ru-RU', {
  day: '2-digit', month: 'short',
})

const formatPrice = (price: number, currency: string) => new Intl.NumberFormat('ru-RU', {
  style: 'currency', currency, maximumFractionDigits: 0,
}).format(price)
</script>

<template>
  <ul class="space-y-2">
    <li v-if="upcoming.length === 0" class="text-sm text-surface-500">
      Ближайших дат нет - следите за обновлениями.
    </li>
    <li
      v-for="d in upcoming"
      :key="d.id"
      :class="[
        'border rounded-lg p-3 cursor-pointer transition',
        selected === d.id
          ? 'border-brand-500 bg-brand-50/40'
          : 'border-surface-200 hover:border-brand-300'
      ]"
      @click="emit('update:selected', d.id)"
    >
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="font-medium">{{ formatDate(d.start_date) }} - {{ formatDate(d.end_date) }}</p>
          <p class="text-xs text-surface-500">
            Свободно: {{ d.seats_available }} / {{ d.seats_total }} мест
          </p>
        </div>
        <p class="font-semibold whitespace-nowrap">{{ formatPrice(d.price, d.currency) }}</p>
      </div>
    </li>
  </ul>
</template>
