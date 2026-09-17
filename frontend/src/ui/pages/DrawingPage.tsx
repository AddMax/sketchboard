import { observer } from 'mobx-react-lite'
import { useEffect, useState } from 'react'
import type { DrawingStore } from '../../application/drawing/DrawingStore'
import { useStores } from '../../application/Stores'
import type { Note } from '../../domain/note/Note'
import { InfiniteCanvas } from '../components/canvas/InfiniteCanvas'
import { Link } from '../routing/Link'
import { BOARD_PATH } from '../routing/routes'

/**
 * Страница доски для рисования, привязанная к заметке: шапка с возвратом
 * и полотно, которое появляется, когда сохранённые штрихи загружены.
 */
export const DrawingPage = observer(function DrawingPage({
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
        <DrawingBoard key={noteId} noteId={noteId} />
      )}
    </main>
  )
})

/** key по noteId выше: новая заметка — новый стор и новое полотно, а не мутация старых. */
const DrawingBoard = observer(function DrawingBoard({ noteId }: { noteId: string }) {
  const { openDrawing } = useStores()
  const [drawing] = useState<DrawingStore>(() => openDrawing(noteId))

  useEffect(() => {
    void drawing.load()

    // Уход со страницы отправляет отложенное сохранение сразу
    return () => drawing.dispose()
  }, [drawing])

  if (drawing.loadError !== null) {
    return (
      <p className="drawing__missing" role="alert">
        Не удалось загрузить рисунок: {drawing.loadError}
      </p>
    )
  }

  if (!drawing.loaded) {
    return <p className="drawing__loading">Загружаем рисунок…</p>
  }

  return <InfiniteCanvas drawing={drawing} />
})
