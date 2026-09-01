const STORAGE_KEY = 'sketchboard.author'

/**
 * Авторизации в скелете нет: имя участника живёт в браузере и нужно только
 * чтобы подписать заметки на общей доске.
 */
export function currentAuthor(): string {
  const stored = window.localStorage.getItem(STORAGE_KEY)

  if (stored !== null && stored !== '') {
    return stored
  }

  const name = `гость-${Math.floor(Math.random() * 900 + 100)}`

  try {
    window.localStorage.setItem(STORAGE_KEY, name)
  } catch {
    // приватный режим — обойдёмся именем на одну сессию
  }

  return name
}
