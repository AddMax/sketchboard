import { useState } from 'react'
import { NOTE_TEXT_MAX_LENGTH } from '../../domain/note/Note'

export function Composer({ onSubmit }: { onSubmit: (text: string) => Promise<void> }) {
  const [text, setText] = useState('')
  const [busy, setBusy] = useState(false)

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    if (busy || text.trim() === '') return

    setBusy(true)
    try {
      await onSubmit(text)
      setText('')
    } finally {
      setBusy(false)
    }
  }

  return (
    <form className="composer" onSubmit={submit}>
      <input
        className="composer__field"
        value={text}
        onChange={(event) => setText(event.target.value)}
        placeholder="Текст заметки — появится у всех сразу"
        maxLength={NOTE_TEXT_MAX_LENGTH}
      />
      <button className="composer__submit" type="submit" disabled={busy || text.trim() === ''}>
        Добавить
      </button>
    </form>
  )
}
