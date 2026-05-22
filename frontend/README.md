# frontend · Vue + Vike + Tailwind 4 + PrimeVue

SSR with Vike (formerly `vite-plugin-ssr`), Vue 3 components, Tailwind 4
via `@tailwindcss/vite`, PrimeVue 4 with a custom Aura preset.

## Local commands

```bash
docker compose exec frontend npm run dev         # SSR dev server (Express + Vite middleware)
docker compose exec frontend npm run typecheck   # vue-tsc
docker compose exec frontend npm run build       # production bundle (dist/)
```

## Page structure (Vike, file-based routing)

```
pages/
├── +config.ts            global Vike config (passToClient, clientRouting)
├── +Layout.vue           header / footer chrome
├── +onRenderHtml.ts      SSR HTML shell + design-time globals
├── +onRenderClient.ts    client-side hydration entry
├── app.ts                creates Vue app, mounts PrimeVue + preset
├── index/
│   ├── +Page.vue         catalog + filters
│   └── +data.ts          server data loader (calls /api/tours + /api/tours/filters)
├── tour/@slug/
│   ├── +Page.vue         detail page + gallery + map + dates
│   └── +data.ts          fetches /api/tours/{slug}
├── about/
│   └── +Page.vue
└── _error/
    └── +Page.vue         404 / 500
```

## Conventions

- **Tailwind 4 only.** Design tokens live in `styles/global.css` under
  `@theme { ... }`. There is no `tailwind.config.js`.
- **PrimeVue preset** in `styles/primevue-preset.ts` extends Aura and
  rebinds the primary colour to the emerald brand palette.
- **API client** in `api/client.ts` picks the right base URL:
  - server-side: `API_URL_SSR` (in-container, e.g. `http://backend:8000/api`)
  - client-side: `PUBLIC_API_URL` (host-visible, e.g.
    `http://localhost:8000/api`).
- **URL is the filter state.** Filter changes do a full navigation
  (`window.location.search = …`) so SSR re-runs and links are
  shareable.
- **Yandex Maps** loads lazily on `onMounted`. The key is injected via
  `window.__PUBLIC_YANDEX_MAPS_API_KEY__` from `+onRenderHtml.ts`.

## Components

| Component | Role |
|---|---|
| `SearchBox.vue` | Hero search bar + suggestion chips |
| `FilterPanel.vue` | Categories / difficulty / duration / price / date range |
| `TourCard.vue` | Catalog tile with cover, badges, semantic score |
| `PhotoGallery.vue` | Detail-page gallery with thumbnails |
| `DateOptions.vue` | Selectable date+price tiles |
| `YandexMap.vue` | SSR-safe lazy map with placemarks and polyline |
| `EmptyState.vue` | "Nothing found" placeholder |

## Env vars

`PUBLIC_*` keys are baked into the client bundle (configured via
`envPrefix` in `vite.config.ts`). See `.env.example`.
