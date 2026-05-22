<script setup lang="ts">
import { computed, ref } from 'vue'

const props = defineProps<{ photos: Array<{ url: string; alt?: string }>; title: string }>()

const active = ref(0)
const main = computed(() => props.photos[active.value] || props.photos[0])

function pick(i: number) {
  active.value = i
}
</script>

<template>
  <div v-if="photos.length" class="space-y-3">
    <div class="aspect-[4/3] rounded-[var(--radius-card)] overflow-hidden bg-surface-100">
      <img :src="main.url" :alt="main.alt || title" class="w-full h-full object-cover" />
    </div>
    <div v-if="photos.length > 1" class="flex gap-2 overflow-x-auto scroll-mask">
      <button
        v-for="(p, i) in photos"
        :key="i"
        type="button"
        @click="pick(i)"
        :class="[
          'flex-shrink-0 w-20 h-16 rounded-lg overflow-hidden border-2 transition',
          active === i ? 'border-brand-500' : 'border-transparent hover:border-surface-200'
        ]"
      >
        <img :src="p.url" :alt="p.alt || ''" class="w-full h-full object-cover" />
      </button>
    </div>
  </div>
  <div v-else class="aspect-[4/3] rounded-[var(--radius-card)] bg-surface-100 flex items-center justify-center text-surface-400">
    Нет фотографий
  </div>
</template>
