import { runInAction } from 'mobx'
import { observer, useLocalObservable } from 'mobx-react-lite'
import { NOTE_TEXT_MAX_LENGTH } from '../../domain/note/Note'

export const Composer = observer(function Composer({
  onSubmit,
}: {
  onSubmit: (text: string) => Promise<void>
}) {
  // Черновик и признак отправки — состояние одной формы, наружу не нужны
  const form = useLocalObservable(() => ({
    text: '',
    busy: false,
    get empty(): boolean {
      return this.text.trim() === ''
    },
    setText(value: string) {
      this.text = value
    },
    async submit(send: (text: string) => Promise<void>) {
      if (this.busy || this.empty) return

      this.busy = true
      try {
        await send(this.text)
        // После await действие уже закончилось: изменения снова оборачиваем
        runInAction(() => {
          this.text = ''
        })
      } finally {
        runInAction(() => {
          this.busy = false
        })
      }
    },
  }))

  return (
    <form
      className="composer"
      onSubmit={(event) => {
        event.preventDefault()
        void form.submit(onSubmit)
      }}
    >
      <input
        className="composer__field"
        value={form.text}
        onChange={(event) => form.setText(event.target.value)}
        placeholder="Текст заметки — появится у всех сразу"
        maxLength={NOTE_TEXT_MAX_LENGTH}
      />
      <button className="composer__submit" type="submit" disabled={form.busy || form.empty}>
        Добавить
      </button>
    </form>
  )
})
