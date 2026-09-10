import type { Note } from '../../domain/note/Note'
import { Link } from '../routing/Link'
import { drawPath } from '../routing/routes'
import { PencilIcon } from './PencilIcon'

export function NoteCard({ note, onRemove }: { note: Note; onRemove: (id: string) => void }) {
  return (
    <article className="note" style={{ background: note.color }}>
      <p className="note__text">{note.text}</p>
      <footer className="note__meta">
        <span>{note.author}</span>
        <span className="note__actions">
          <Link
            className="note__edit"
            to={drawPath(note.id)}
            aria-label="Редактировать заметку"
            title="Открыть доску для рисования"
          >
            <PencilIcon />
          </Link>
          <button
            className="note__remove"
            type="button"
            onClick={() => onRemove(note.id)}
            aria-label="Удалить заметку"
          >
            ×
          </button>
        </span>
      </footer>
    </article>
  )
}
