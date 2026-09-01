import type { Note } from '../../domain/note/Note'

export function NoteCard({ note, onRemove }: { note: Note; onRemove: (id: string) => void }) {
  return (
    <article className="note" style={{ background: note.color }}>
      <p className="note__text">{note.text}</p>
      <footer className="note__meta">
        <span>{note.author}</span>
        <button type="button" onClick={() => onRemove(note.id)} aria-label="Удалить заметку">
          ×
        </button>
      </footer>
    </article>
  )
}
