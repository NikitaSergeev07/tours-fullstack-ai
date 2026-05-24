import { ofetch, type $Fetch } from 'ofetch'

/**
 * Two-tier API client: server-side renders use the in-container URL
 * (http://backend:8000/api) so requests don't hairpin through the host
 * port-forward, while browser requests use the public URL exposed via env.
 */
export function makeApi(opts: { isServer: boolean }): $Fetch {
  const baseURL = opts.isServer
    ? (process.env.API_URL_SSR || 'http://backend:8000/api')
    : (
      import.meta?.env?.PUBLIC_API_URL || process.env.PUBLIC_API_URL || '/api'
    )
  return ofetch.create({
    baseURL,
    retry: 0,
    timeout: 15_000,
    headers: { accept: 'application/json' },
  })
}

export type Money = number

export interface TourSummary {
  id: number
  slug: string
  title: string
  short_description: string
  duration_days: number
  difficulty: 'easy' | 'moderate' | 'hard'
  cover_image: string | null
  categories?: Array<{ slug: string; name: string; icon?: string | null }>
  price_from: Money | null
  score?: number
  photos?: Array<{ url: string; alt?: string }>
}

export interface TourDetail extends TourSummary {
  description: string
  duration_hours: number | null
  highlights: string[]
  photos: Array<{ url: string; alt?: string }>
  dates: Array<{
    id: number
    start_date: string
    end_date: string
    price: Money
    currency: string
    seats_total: number
    seats_available: number
  }>
  route: {
    points: Array<{ lat: number; lon: number; label?: string }>
    center: { lat: number; lon: number; zoom?: number } | null
  }
}

export interface FilterOptions {
  categories: Array<{ slug: string; name: string; icon: string | null }>
  difficulties: Array<{ value: string; label: string }>
  sort_options: Array<{ value: string; label: string }>
  duration: { min: number; max: number }
  price: { min: number; max: number }
}

export interface Paginated<T> {
  data: T[]
  links?: unknown
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
