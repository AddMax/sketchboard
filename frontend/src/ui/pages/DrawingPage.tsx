import { useDrawing } from '../../application/drawing/useDrawing'
import type { Note } from '../../domain/note/Note'
import { InfiniteCanvas } from '../components/canvas/InfiniteCanvas'
import { Link } from '../routing/Link'
import { BOARD_PATH } from '../routing/routes'

/**
 * Страница доски для рисования, привязанная к заметке: шапка с возвратом
 * и полотно, которое появляется, когда сохранённые штрихи загружены.
 */
export function DrawingPage({
  noteId,
  note,
  loaded,
}: {
  noteId: string
  note: Note | undefined
  loaded: boolean
}) {
  return (
    <main className="drawing">
      <nav className="drawing__nav">
        <Link className="drawing__back" to={BOARD_PATH}>
          ← К заметкам
        </Link>
        {note !== undefined && (
          <span className="drawing__note" style={{ background: note.color }}>
            {note.text}
          </span>
        )}
      </nav>

      {loaded && note === undefined ? (
        <p className="drawing__missing" role="alert">
          Заметка не найдена — возможно, её уже удалили.
        </p>
      ) : (
        <DrawingBoard noteId={noteId} />
      )}
    </main>
  )
}

function DrawingBoard({ noteId }: { noteId: string }) {
  const { lines, error, save } = useDrawing(noteId)

  if (error !== null) {
    return (
      <p className="drawing__missing" role="alert">
        Не удалось загрузить рисунок: {error}
      </p>
    )
  }

  if (lines === null) {
    return <p className="drawing__loading">Загружаем рисунок…</p>
  }

  // key: новая заметка — новое полотно с её штрихами, а не мутация старого
  return <InfiniteCanvas key={noteId} initialLines={lines} onSave={save} />
}
