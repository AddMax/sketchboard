/**
 * Дебаунс с сохранением последних аргументов: повторный вызов сдвигает
 * таймер, а сработает только последний набор аргументов.
 *
 * Не хук и не зависит от React: им пользуются сторы, которые живут дольше
 * компонента и сами решают, когда выполнить отложенное (flush) или
 * отказаться от него (cancel).
 */
export class Debounce<A extends unknown[]> {
  private timer: ReturnType<typeof setTimeout> | null = null
  private args: A | null = null

  constructor(
    private readonly callback: (...args: A) => void,
    private readonly delayMs: number,
  ) {}

  /** Есть ли отложенный, ещё не выполненный вызов. */
  get pending(): boolean {
    return this.args !== null
  }

  /** Отложить вызов; повторный вызов сдвигает таймер заново. */
  schedule(...args: A): void {
    this.args = args

    if (this.timer !== null) {
      clearTimeout(this.timer)
    }

    this.timer = setTimeout(() => {
      this.timer = null
      this.flush()
    }, this.delayMs)
  }

  /** Выполнить отложенный вызов немедленно, если он есть. */
  flush(): void {
    const args = this.args

    this.cancel()

    if (args !== null) {
      this.callback(...args)
    }
  }

  /** Отменить отложенный вызов. */
  cancel(): void {
    if (this.timer !== null) {
      clearTimeout(this.timer)
      this.timer = null
    }

    this.args = null
  }
}
