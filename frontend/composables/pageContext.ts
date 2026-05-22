// vike-vue exposes a Vue composable that returns the reactive pageContext
// — we re-export it under a project-local name so the rest of the app
// only depends on `composables/pageContext`.
export { usePageContext } from 'vike-vue/usePageContext'
