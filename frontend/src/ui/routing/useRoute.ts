import { useCallback, useEffect, useState } from 'react'
import { parseRoute } from './routes'
import type { Route } from './routes'

const NAVIGATE_EVENT = 'sketchboard:navigate'

/**
 * pushState не порождает popstate, поэтому о собственных переходах
 * подписчикам сообщаем сами — через событие на window.
 */
export function navigate(path: string): void {
  if (window.location.pathname === path) return

  window.history.pushState(null, '', path)
  window.dispatchEvent(new Event(NAVIGATE_EVENT))
}

export function useRoute(): Route {
  const [route, setRoute] = useState<Route>(() => parseRoute(window.location.pathname))

  const sync = useCallback(() => setRoute(parseRoute(window.location.pathname)), [])

  useEffect(() => {
    window.addEventListener('popstate', sync)
    window.addEventListener(NAVIGATE_EVENT, sync)

    return () => {
      window.removeEventListener('popstate', sync)
      window.removeEventListener(NAVIGATE_EVENT, sync)
    }
  }, [sync])

  return route
}
