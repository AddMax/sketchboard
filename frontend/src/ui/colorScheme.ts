import { useLocalObservable } from 'mobx-react-lite'
import { useEffect } from 'react'

const DARK_SCHEME = '(prefers-color-scheme: dark)'

/**
 * Системная цветовая схема как наблюдаемое значение: свои стили подхватывают
 * её через CSS, а стороннему редактору тему нужно передать явно.
 */
export function useColorScheme(): { readonly dark: boolean } {
  const scheme = useLocalObservable(() => ({
    dark: window.matchMedia(DARK_SCHEME).matches,
    update(matches: boolean) {
      this.dark = matches
    },
  }))

  useEffect(() => {
    const query = window.matchMedia(DARK_SCHEME)
    const onChange = (event: MediaQueryListEvent) => scheme.update(event.matches)

    query.addEventListener('change', onChange)

    return () => query.removeEventListener('change', onChange)
  }, [scheme])

  return scheme
}
