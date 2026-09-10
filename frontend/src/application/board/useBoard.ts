import { useCallback, useEffect, useRef, useState } from 'react'
import { applyRealtimeEvent, emptyBoard, replaceBoard } from '../../domain/board/Board'
import type { Board } from '../../domain/board/Board'
import { draftNote } from '../../domain/note/Note'
import { DomainError } from '../../domain/note/errors'
import type { ConnectionState, RealtimeEvent } from '../../domain/realtime/RealtimeEvent'
import { useBoardDependencies } from './BoardDependencies'

export interface BoardState {
  readonly board: Board
  /** Первичная загрузка завершена (успехом или ошибкой): пустая доска — уже не «ещё грузится». */
  readonly loaded: boolean
  readonly connection: ConnectionState
  readonly clients: number
  readonly error: string | null
  addNote(input: { text: string; color: string; author: string }): Promise<void>
  moveNote(id: string, x: number, y: number): Promise<void>
  removeNote(id: string): Promise<void>
  ping(author: string): void
  dismissError(): void
}

/**
 * Сценарий работы с доской: первичная загрузка через репозиторий, дальше —
 * поток событий из realtime-канала. Собственных изменений состояния после
 * команд нет: их вернёт тот же канал, поэтому все клиенты, включая автора,
 * обновляются одинаково.
 */
export function useBoard(): BoardState {
  const { notes, realtime } = useBoardDependencies()

  const [board, setBoard] = useState<Board>(emptyBoard)
  const [loaded, setLoaded] = useState(false)
  const [connection, setConnection] = useState<ConnectionState>('connecting')
  const [clients, setClients] = useState(0)
  const [error, setError] = useState<string | null>(null)

  const realtimeRef = useRef(realtime)
  realtimeRef.current = realtime

  useEffect(() => {
    let cancelled = false

    notes
      .list()
      .then((fetched) => {
        if (!cancelled) {
          setBoard(replaceBoard(fetched))
        }
      })
      .catch((cause: unknown) => {
        if (!cancelled) {
          setError(describe(cause))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoaded(true)
        }
      })

    return () => {
      cancelled = true
    }
  }, [notes])

  useEffect(() => {
    return realtime.connect({
      onEvent(event: RealtimeEvent) {
        if (event.event === 'presence') {
          setClients(event.payload.clients)
        }

        setBoard((current) => applyRealtimeEvent(current, event))
      },
      onStateChange: setConnection,
    })
  }, [realtime])

  const addNote = useCallback(
    async (input: { text: string; color: string; author: string }) => {
      try {
        // Доменные правила проверяются до сети: бессмысленный запрос не уходит
        const draft = draftNote({
          ...input,
          x: Math.floor(Math.random() * 600),
          y: Math.floor(Math.random() * 300),
        })

        await notes.create(draft)
        setError(null)
      } catch (cause) {
        setError(describe(cause))
      }
    },
    [notes],
  )

  const moveNote = useCallback(
    async (id: string, x: number, y: number) => {
      try {
        await notes.move(id, x, y)
        setError(null)
      } catch (cause) {
        setError(describe(cause))
      }
    },
    [notes],
  )

  const removeNote = useCallback(
    async (id: string) => {
      try {
        await notes.remove(id)
        setError(null)
      } catch (cause) {
        setError(describe(cause))
      }
    },
    [notes],
  )

  const ping = useCallback((author: string) => {
    realtimeRef.current.send('ping', { author, at: Date.now() })
  }, [])

  const dismissError = useCallback(() => setError(null), [])

  return { board, loaded, connection, clients, error, addNote, moveNote, removeNote, ping, dismissError }
}

function describe(cause: unknown): string {
  if (cause instanceof DomainError) {
    return cause.message
  }

  return cause instanceof Error ? cause.message : 'Неизвестная ошибка'
}
