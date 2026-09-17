import { createContext, useContext } from 'react'
import type { ReactNode } from 'react'
import { BoardStore } from './board/BoardStore'
import type { Dependencies } from './Dependencies'
import { DrawingStore } from './drawing/DrawingStore'

/**
 * Сторы приложения. Доска одна на всё приложение, а рисунок — на страницу:
 * его стор создаётся при открытии заметки и живёт, пока страница открыта.
 */
export interface Stores {
  readonly board: BoardStore
  openDrawing(noteId: string): DrawingStore
}

export function createStores({ notes, drawings, realtime }: Dependencies): Stores {
  return {
    board: new BoardStore(notes, realtime),
    openDrawing: (noteId) => new DrawingStore(noteId, drawings),
  }
}

const Context = createContext<Stores | null>(null)

/**
 * Сторы приходят снаружи (композиционный корень — infrastructure/container),
 * поэтому ни один компонент не создаёт их сам.
 */
export function StoresProvider({ stores, children }: { stores: Stores; children: ReactNode }) {
  return <Context.Provider value={stores}>{children}</Context.Provider>
}

export function useStores(): Stores {
  const stores = useContext(Context)

  if (stores === null) {
    throw new Error('StoresProvider не найден выше по дереву')
  }

  return stores
}
