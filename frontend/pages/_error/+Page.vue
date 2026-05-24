<script setup lang="ts">
import { usePageContext } from '../../composables/pageContext'

const ctx = usePageContext()
const code = (ctx as any).abortStatusCode ?? 500
const isNotFound = code === 404
</script>

<template>
  <section class="max-w-3xl mx-auto px-4 py-24 text-center">
    <div class="text-6xl mb-4">{{ isNotFound ? '🧭' : '⚠️' }}</div>
    <h1 class="text-3xl font-semibold mb-3">
      {{ isNotFound ? 'Тур не найден' : 'Что-то пошло не так' }}
    </h1>
    <p class="text-surface-600">
      {{ isNotFound
        ? 'Возможно, тур снят с публикации. Вернёмся к каталогу - там много вариантов.'
        : 'Сервис временно недоступен, попробуйте обновить страницу через пару секунд.' }}
    </p>
    <a href="/" class="inline-block mt-6 px-5 py-2.5 rounded-lg bg-brand-600 text-white hover:bg-brand-700 transition">
      В каталог
    </a>
  </section>
</template>
