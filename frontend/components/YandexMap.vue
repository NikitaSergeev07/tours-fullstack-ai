<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'

declare global { interface Window { ymaps?: any; __PUBLIC_YANDEX_MAPS_API_KEY__?: string } }

const props = defineProps<{
  points: Array<{ lat: number; lon: number; label?: string }>
  center: { lat: number; lon: number; zoom?: number } | null
}>()

const mapEl = ref<HTMLDivElement | null>(null)
const error = ref<string | null>(null)
const loaded = ref(false)
let mapInstance: any = null

const fallbackCenter = () => {
  if (props.center) return [props.center.lat, props.center.lon] as [number, number]
  if (props.points.length) {
    const lat = props.points.reduce((s, p) => s + p.lat, 0) / props.points.length
    const lon = props.points.reduce((s, p) => s + p.lon, 0) / props.points.length
    return [lat, lon] as [number, number]
  }
  return [55.751244, 37.618423] as [number, number]
}

async function loadScript(apiKey: string): Promise<void> {
  if (window.ymaps) return
  await new Promise<void>((resolve, reject) => {
    const s = document.createElement('script')
    s.src = `https://api-maps.yandex.ru/2.1/?apikey=${encodeURIComponent(apiKey)}&lang=ru_RU`
    s.async = true
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('Не удалось загрузить Яндекс Карты'))
    document.head.appendChild(s)
  })
  await new Promise<void>((resolve) => window.ymaps.ready(resolve))
}

function initMap() {
  if (!mapEl.value) return
  mapInstance?.destroy()
  mapInstance = new window.ymaps.Map(mapEl.value, {
    center: fallbackCenter(),
    zoom: props.center?.zoom ?? 8,
    controls: ['zoomControl', 'fullscreenControl'],
  })

  if (props.points.length > 0) {
    const coords = props.points.map(p => [p.lat, p.lon] as [number, number])
    const route = new window.ymaps.Polyline(coords, {}, {
      strokeColor: '#10b981', strokeWidth: 4, strokeOpacity: 0.85,
    })
    mapInstance.geoObjects.add(route)
    props.points.forEach((p, i) => {
      const placemark = new window.ymaps.Placemark([p.lat, p.lon],
        { balloonContent: p.label || `Точка ${i + 1}`, iconCaption: String(i + 1) },
        { preset: 'islands#emeraldStretchyIcon' })
      mapInstance.geoObjects.add(placemark)
    })
    mapInstance.setBounds(route.geometry.getBounds(), { checkZoomRange: true, zoomMargin: 40 })
  }
}

onMounted(async () => {
  const apiKey = window.__PUBLIC_YANDEX_MAPS_API_KEY__ || ''
  if (!apiKey) {
    error.value = 'API ключ Яндекс Карт не настроен — задайте YANDEX_MAPS_API_KEY в .env.'
    return
  }
  try {
    await loadScript(apiKey)
    initMap()
    loaded.value = true
  } catch (e: any) {
    error.value = e?.message || 'Ошибка карты'
  }
})

watch(() => [props.points, props.center], () => {
  if (loaded.value) initMap()
}, { deep: true })

onBeforeUnmount(() => {
  mapInstance?.destroy()
})
</script>

<template>
  <div class="relative w-full aspect-[16/9] rounded-xl overflow-hidden border border-surface-200 bg-surface-100">
    <div ref="mapEl" class="absolute inset-0"></div>
    <div
      v-if="error"
      class="absolute inset-0 flex items-center justify-center p-4 text-sm text-surface-700 bg-surface-50/95 text-center"
    >
      <div>
        <div class="text-2xl mb-2">🗺️</div>
        <p>{{ error }}</p>
        <p class="text-xs text-surface-500 mt-2">
          Маршрут содержит {{ points.length }} {{ points.length === 1 ? 'точку' : 'точек' }}.
        </p>
      </div>
    </div>
  </div>
</template>
