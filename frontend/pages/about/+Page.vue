<script setup lang="ts">
const stack = [
  ['Бэкенд', 'Laravel 11, Filament 3, PostgreSQL + pgvector'],
  ['Фронт', 'Vue 3, Vike (SSR), Vite, Tailwind 4, PrimeVue 4 (custom preset)'],
  ['Embeddings', 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2 (FastAPI)'],
  ['LLM', 'Claude (haiku) - для генерации черновика тура в админке'],
  ['Карты', 'Яндекс.Карты JS API'],
  ['Инфраструктура', 'Docker Compose, монорепозиторий'],
]
</script>

<template>
  <article class="max-w-3xl mx-auto px-4 sm:px-6 py-12 prose prose-headings:font-display">
    <h1 class="text-3xl font-display font-semibold">О сервисе</h1>
    <p class="text-lg text-surface-700 leading-relaxed">
      Tours - это демо каталога авторских туров с двумя «фишками»:
      <b>семантический поиск</b> по эмбеддингам и <b>LLM-черновик</b>,
      который собирает новый тур из одной строки запроса.
    </p>

    <h2 class="mt-8 text-xl font-semibold">Стек</h2>
    <ul class="not-prose mt-3 space-y-2 text-sm">
      <li v-for="row in stack" :key="row[0]" class="flex gap-3">
        <span class="font-medium w-32 text-surface-600">{{ row[0] }}</span>
        <span class="text-surface-800">{{ row[1] }}</span>
      </li>
    </ul>

    <h2 class="mt-8 text-xl font-semibold">Как работает поиск</h2>
    <ol class="list-decimal list-inside space-y-2 text-surface-800">
      <li>При сохранении тура admin-панель отправляет его описание в FastAPI-сервис эмбеддингов.</li>
      <li>Полученный вектор (384-мерный) сохраняется в PostgreSQL - в колонку типа <code>vector</code>.</li>
      <li>При поиске запрос тоже превращается в вектор, и pgvector сортирует туры по косинусной близости.</li>
      <li>Если сервис эмбеддингов недоступен - каталог автоматически переходит на лексический ILIKE-поиск.</li>
    </ol>
  </article>
</template>
