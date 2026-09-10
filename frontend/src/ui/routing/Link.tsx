import type { AnchorHTMLAttributes, MouseEvent, ReactNode } from 'react'
import { navigate } from './useRoute'

/**
 * Обычная ссылка с перехватом клика: переход без перезагрузки, а открытие
 * в новой вкладке (средняя кнопка, Ctrl/Cmd) браузер обрабатывает сам.
 */
export function Link({
  to,
  children,
  onClick,
  ...rest
}: { to: string; children: ReactNode } & Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'href'>) {
  const handleClick = (event: MouseEvent<HTMLAnchorElement>) => {
    onClick?.(event)

    if (event.defaultPrevented || event.button !== 0) return
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return

    event.preventDefault()
    navigate(to)
  }

  return (
    <a href={to} onClick={handleClick} {...rest}>
      {children}
    </a>
  )
}
