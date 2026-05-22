import type { OnCreateAppSync } from 'vike-vue/types'
import PrimeVue from 'primevue/config'
import Tooltip from 'primevue/tooltip'
import { ToursPreset } from '../styles/primevue-preset'
import '../styles/global.css'
import 'primeicons/primeicons.css'

// vike-vue calls this once per request (server) and once on hydration
// (client). We install PrimeVue with our custom Aura preset so all PrimeVue
// components inherit brand colours straight from styles/primevue-preset.ts.
const onCreateApp: OnCreateAppSync = (pageContext): ReturnType<OnCreateAppSync> => {
  const { app } = pageContext
  app.use(PrimeVue, {
    ripple: true,
    theme: {
      preset: ToursPreset,
      options: {
        prefix: 'p',
        darkModeSelector: '.app-dark',
        cssLayer: false,
      },
    },
  })
  app.directive('tooltip', Tooltip)
}

export default onCreateApp
