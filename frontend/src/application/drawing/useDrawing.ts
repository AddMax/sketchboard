import { useCallback, useEffect, useState } from 'react'
import type { DrawingLine } from '../../domain/drawing/Drawing'
import { useBoardDependencies } from '../board/BoardDependencies'
import { describeError } from '../shared/describeError'

export interface DrawingState {
  /** null, пока рисунок не загружен: полотно нельзя показывать пустым раньше времени */
  readonly lines: DrawingLine[] | null
  readonly error: string | null
  save(lines: DrawingLine[]): Promise<void>
}

/**
 * Сценарий страницы рисования: один раз загрузить штрихи заметки и дать
 * полотну способ сохранить их обратно. Дальше состоянием владеет полотно —
 * оно само решает, когда и что отправлять.
 */
export function useDrawing(noteId: string): DrawingState {
  const { drawings } = useBoardDependencies()

  const [lines, setLines] = useState<DrawingLine[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    setLines(null)
    setError(null)

    drawings
      .load(noteId)
      .then((loaded) => {
        if (!cancelled) {
          setLines(loaded)
        }
      })
      .catch((cause: unknown) => {
        if (!cancelled) {
          setError(describeError(cause))
        }
      })

    return () => {
      cancelled = true
    }
  }, [drawings, noteId])

  const save = useCallback((next: DrawingLine[]) => drawings.save(noteId, next), [drawings, noteId])

  return { lines, error, save }
}
