import { makeAutoObservable } from 'mobx'
import { createContext, useContext } from 'react'
import { parseRoute } from './routes'
import type { Route } from './routes'

/**
 * Текущий экран поверх History API. pushState не порождает popstate,
 * поэтому о собственных переходах стор узнаёт сам — из navigate.
 */
export class RouterStore {
  route: Route

  constructor() {
    this.route = parseRoute(window.location.pathname)

    makeAutoObservable(this, {}, { autoBind: true })
  }

  /** Подписка на кнопки «назад/вперёд». @returns функция отписки */
  start(): () => void {
    window.addEventListener('popstate', this.sync)

    return () => window.removeEventListener('popstate', this.sync)
  }

  navigate(path: string): void {
    if (window.location.pathname === path) return

    window.history.pushState(null, '', path)
    this.sync()
  }

  sync(): void {
    this.route = parseRoute(window.location.pathname)
  }
}

const Context = createContext<RouterStore | null>(null)

export const RouterProvider = Context.Provider

export function useRouter(): RouterStore {
  const router = useContext(Context)

  if (router === null) {
    throw new Error('RouterProvider не найден выше по дереву')
  }

  return router
}
