import { observer } from 'mobx-react-lite'
import type { SaveStatus as Status } from '../../../application/drawing/DrawingStore'

const LABELS: Record<Status, string> = {
  saved: 'Сохранено',
  saving: 'Сохранение…',
  error: 'Ошибка сохранения — нажмите, чтобы повторить',
}

/** Чем отвечает документ: статус и повтор отправки. */
export interface Savable {
  readonly saveStatus: Status
  retrySave(): void
}

/**
 * Статус сохранения в панели редактора. Excalidraw вызывает функцию
 * отрисовки по своим правилам, поэтому компонент наблюдает за стором сам,
 * а не полагается на перерисовку родителя.
 */
export const SaveStatus = observer(function SaveStatus({ document }: { document: Savable }) {
  const status = document.saveStatus

  return (
    <button
      type="button"
      className={`editor__status editor__status--${status}`}
      onClick={document.retrySave}
      disabled={status !== 'error'}
      aria-live="polite"
    >
      {LABELS[status]}
    </button>
  )
})
