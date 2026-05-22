import vikeVue from 'vike-vue/config'
import type { Config } from 'vike/types'

// vike-vue handles all the Vue + Vike plumbing (SSR render, hydration,
// Layout, Head, onCreateApp). Page-level Layout/Head are picked up from
// the sibling +Layout.vue and +Head.vue files.
export default {
  extends: vikeVue,
  title: 'Tours · каталог авторских туров',
  description: 'Авторские туры с семантическим поиском, гибкими фильтрами и маршрутом на карте.',
  lang: 'ru',
  passToClient: ['data', 'routeParams', 'abortStatusCode', 'documentProps'],
} satisfies Config
