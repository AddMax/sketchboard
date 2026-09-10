import { createContext, useContext } from 'react'
import type { ReactNode } from 'react'
import type { DrawingRepository } from '../ports/DrawingRepository'
import type { NoteRepository } from '../ports/NoteRepository'
import type { RealtimeChannel } from '../ports/RealtimeChannel'

export interface BoardDependencies {
  readonly notes: NoteRepository
  readonly drawings: DrawingRepository
  readonly realtime: RealtimeChannel
}

const Context = createContext<BoardDependencies | null>(null)

/**
 * Адаптеры приходят снаружи (композиционный корень — infrastructure/container),
 * поэтому ни один компонент не создаёт их сам.
 */
export function BoardDependenciesProvider({
  dependencies,
  children,
}: {
  dependencies: BoardDependencies
  children: ReactNode
}) {
  return <Context.Provider value={dependencies}>{children}</Context.Provider>
}

export function useBoardDependencies(): BoardDependencies {
  const dependencies = useContext(Context)

  if (dependencies === null) {
    throw new Error('BoardDependenciesProvider не найден выше по дереву')
  }

  return dependencies
}
