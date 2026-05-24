<script setup lang="ts">
const yandexKey = (
  (typeof import.meta !== 'undefined' && import.meta.env?.PUBLIC_YANDEX_MAPS_API_KEY)
  || (typeof process !== 'undefined' && process.env?.PUBLIC_YANDEX_MAPS_API_KEY)
  || ''
)

const faviconSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">🗺️</text></svg>'
</script>

<template>
  <link rel="icon" :href="`data:image/svg+xml,${encodeURIComponent(faviconSvg)}`" />
  <!-- Yandex Maps API key shipped as a window global so YandexMap.vue can
       read it inside onMounted without a second fetch. -->
  <component :is="'script'" v-html="`window.__PUBLIC_YANDEX_MAPS_API_KEY__=${JSON.stringify(yandexKey)}`" />
</template>
