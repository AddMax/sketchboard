import { useCallback, useEffect, useMemo, useRef } from 'react'

export interface DebouncedCallback<A extends unknown[]> {
  /** Отложить вызов; повторный вызов сдвигает таймер заново. */
  schedule(...args: A): void
  /** Выполнить отложенный вызов немедленно, если он есть. */
  flush(): void
  /** Отменить отложенный вызов. */
  cancel(): void
}

/**
 * Дебаунс с сохранением последних аргументов. Коллбэк читается через ref,
 * поэтому его можно передавать инлайном без пересоздания таймера.
 *
 * При размонтировании отложенный вызов по умолчанию выполняется, а не
 * теряется: для сохранения на сервер потерять последний штрих хуже,
 * чем отправить его на секунду раньше.
 */
export function useDebouncedCallback<A extends unknown[]>(
  callback: (...args: A) => void,
  delayMs: number,
  { flushOnUnmount = true }: { flushOnUnmount?: boolean } = {},
): DebouncedCallback<A> {
  const callbackRef = useRef(callback)
  const timerRef = useRef<number | null>(null)
  const argsRef = useRef<A | null>(null)

  useEffect(() => {
    callbackRef.current = callback
  }, [callback])

  const cancel = useCallback(() => {
    if (timerRef.current !== null) {
      window.clearTimeout(timerRef.current)
      timerRef.current = null
    }

    argsRef.current = null
  }, [])

  const flush = useCallback(() => {
    const args = argsRef.current

    cancel()

    if (args !== null) {
      callbackRef.current(...args)
    }
  }, [cancel])

  const schedule = useCallback(
    (...args: A) => {
      argsRef.current = args

      if (timerRef.current !== null) {
        window.clearTimeout(timerRef.current)
      }

      timerRef.current = window.setTimeout(() => {
        timerRef.current = null
        flush()
      }, delayMs)
    },
    [delayMs, flush],
  )

  useEffect(() => {
    return () => {
      if (flushOnUnmount) {
        flush()
      } else {
        cancel()
      }
    }
  }, [flush, cancel, flushOnUnmount])

  return useMemo(() => ({ schedule, flush, cancel }), [schedule, flush, cancel])
}
