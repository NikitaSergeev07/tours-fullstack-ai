<script setup lang="ts">
import { ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'

const props = defineProps<{ initial?: string }>()
const emit = defineEmits<{ (e: 'search', q: string): void }>()

const q = ref(props.initial || '')

const suggestions = [
  'Зимний Байкал и термы',
  'Горный поход с детьми',
  'Гастро-тур на 3 дня',
  'Велосипед по югу',
]

function pick(value: string) {
  q.value = value
  emit('search', value)
}
</script>

<template>
  <div class="space-y-3">
    <form class="flex gap-2 max-w-2xl" @submit.prevent="emit('search', q)">
      <span class="relative flex-1">
        <InputText
          v-model="q"
          placeholder="Например, «снежные горы с банькой и видом»"
          class="w-full !pl-10 !py-3 !text-base"
        />
        <span class="pi pi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-surface-400"></span>
      </span>
      <Button type="submit" label="Найти" severity="primary" class="!px-6" />
    </form>
    <div class="flex items-center gap-2 flex-wrap text-sm">
      <span class="text-surface-500">Попробуйте:</span>
      <button
        v-for="hint in suggestions"
        :key="hint"
        type="button"
        class="px-3 py-1 rounded-full border border-surface-200 bg-surface-0 hover:bg-brand-50 hover:border-brand-200 hover:text-brand-700 transition"
        @click="pick(hint)"
      >
        {{ hint }}
      </button>
    </div>
  </div>
</template>
