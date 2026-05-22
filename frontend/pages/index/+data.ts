import { makeApi, type FilterOptions, type Paginated, type TourSummary } from '../../api/client'
import type { PageContextServer } from 'vike/types'

const ALLOWED_KEYS = new Set([
  'q', 'categories', 'duration_min', 'duration_max',
  'price_min', 'price_max', 'difficulty', 'date_from', 'date_to',
  'sort', 'page', 'per_page',
])

export type IndexData = {
  tours: TourSummary[]
  filters: FilterOptions
  pagination: { current: number; last: number; total: number }
  query: Record<string, string | string[]>
}

export async function data(pageContext: PageContextServer): Promise<IndexData> {
  const url = new URL('http://_'+(pageContext.urlOriginal || '/'))
  const params: Record<string, string | string[]> = {}
  for (const [k, v] of url.searchParams.entries()) {
    if (!ALLOWED_KEYS.has(k.replace('[]', ''))) continue
    if (k.endsWith('[]')) {
      const key = k.slice(0, -2)
      ;(params[key] ||= []) as string[]
      ;(params[key] as string[]).push(v)
    } else if (params[k] !== undefined) {
      params[k] = Array.isArray(params[k]) ? [...(params[k] as string[]), v] : [params[k] as string, v]
    } else {
      params[k] = v
    }
  }

  const api = makeApi({ isServer: true })

  // Build a flat query string Laravel can understand (categories[]=a&categories[]=b).
  const qs = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) {
    if (Array.isArray(v)) v.forEach(x => qs.append(`${k}[]`, x))
    else qs.append(k, v)
  }

  const [toursRes, filters] = await Promise.all([
    api<Paginated<TourSummary>>('/tours?' + qs.toString()),
    api<FilterOptions>('/tours/filters'),
  ])

  const meta = (toursRes as any).meta ?? { current_page: 1, last_page: 1, total: toursRes.data.length, per_page: 12 }

  return {
    tours: toursRes.data,
    filters,
    pagination: { current: meta.current_page, last: meta.last_page, total: meta.total },
    query: params,
  }
}
