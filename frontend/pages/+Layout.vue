<script setup lang="ts">
import { computed } from 'vue'
import { usePageContext } from '../composables/pageContext'

const ctx = usePageContext()

function isActive(prefix: string): boolean {
  return (ctx.urlPathname || '').startsWith(prefix)
}

// The admin lives on the Laravel host. On the server we still use the public
// URL because the link is rendered into HTML and clicked by the user — not
// fetched by the SSR runtime.
const adminUrl = computed(() => {
  // @ts-expect-error – injected by import.meta.env on the client
  const fromVite = typeof import.meta !== 'undefined' && import.meta.env?.PUBLIC_ADMIN_URL
  return (fromVite as string) || process.env.PUBLIC_ADMIN_URL || 'http://localhost:8000/admin'
})
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-40 backdrop-blur bg-surface-0/85 border-b border-surface-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <a href="/" class="flex items-center gap-2 group">
          <span class="text-2xl">🗺️</span>
          <span class="font-semibold tracking-tight text-lg group-hover:text-brand-700 transition">
            Tours
          </span>
        </a>

        <nav class="hidden md:flex items-center gap-1 text-sm">
          <a href="/"
             :class="['px-3 py-2 rounded-lg transition',
                      isActive('/tour') || ctx.urlPathname === '/'
                        ? 'text-brand-700 bg-brand-50'
                        : 'text-surface-700 hover:text-surface-900 hover:bg-surface-100']">
            Каталог
          </a>
          <a href="/about"
             :class="['px-3 py-2 rounded-lg transition',
                      isActive('/about')
                        ? 'text-brand-700 bg-brand-50'
                        : 'text-surface-700 hover:text-surface-900 hover:bg-surface-100']">
            О сервисе
          </a>
        </nav>

        <div class="flex items-center gap-3">
          <a :href="adminUrl"
             class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm
                    border border-surface-200 text-surface-700 hover:bg-surface-100 transition">
            <span class="pi pi-cog text-xs"></span>
            Админка
          </a>
        </div>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="border-t border-surface-200 bg-surface-0 mt-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-sm text-surface-500 flex flex-col sm:flex-row justify-between gap-4">
        <span>© {{ new Date().getFullYear() }} Tours · авторские туры с семантическим поиском</span>
        <span>Бэкенд: Laravel + pgvector · Фронт: Vue + Vike · LLM: Anthropic</span>
      </div>
    </footer>
  </div>
</template>
