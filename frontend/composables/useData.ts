import { useData as useDataInternal } from 'vike-vue/useData'

/**
 * Typed wrapper around vike-vue's useData(), which returns whatever the
 * route's +data.ts function returned.
 */
export function useData<T>(): T {
  return useDataInternal<T>()
}
