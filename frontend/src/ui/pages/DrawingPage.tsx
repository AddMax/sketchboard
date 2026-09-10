import type { Note } from '../../domain/note/Note'
import { Link } from '../routing/Link'
import { BOARD_PATH } from '../routing/routes'

/**
 * Страница доски для рисования, привязанная к заметке. Само полотно —
 * отдельная задача: здесь его место и всё окружение (шапка, возврат).
 */
export function DrawingPage({ note, loaded }: { note: Note | undefined; loaded: boolean }) {
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
        <section className="drawing__canvas" aria-label="Доска для рисования">
          <p className="drawing__hint">Здесь будет бесконечная доска для рисования.</p>
        </section>
      )}
    </main>
  )
}
