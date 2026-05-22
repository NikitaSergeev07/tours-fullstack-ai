import { makeApi, type TourDetail } from '../../../api/client'
import type { PageContextServer } from 'vike/types'
import { render } from 'vike/abort'

export async function data(pageContext: PageContextServer): Promise<TourDetail> {
  const slug = pageContext.routeParams?.slug
  if (!slug) throw render(404)
  const api = makeApi({ isServer: true })
  try {
    const res = await api<{ data: TourDetail }>(`/tours/${encodeURIComponent(slug)}`)
    return res.data
  } catch (e: any) {
    if (e?.response?.status === 404) throw render(404)
    throw e
  }
}
