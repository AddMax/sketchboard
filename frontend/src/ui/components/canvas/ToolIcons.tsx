import type { ShapeKind } from '../../../domain/drawing/Drawing'

export type Tool = 'brush' | ShapeKind

const COMMON = {
  width: 18,
  height: 18,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 2,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  'aria-hidden': true,
} as const

/** Пиктограммы инструментов: контур в цвете текста, чтобы работали в обеих схемах. */
export function ToolIcon({ tool }: { tool: Tool }) {
  switch (tool) {
    case 'brush':
      return (
        <svg {...COMMON}>
          <path d="M4 20c2-1 3-2 4-4M20 4l-9 9 2 2 9-9-2-2Z" />
        </svg>
      )
    case 'rect':
      return (
        <svg {...COMMON}>
          <rect x="4" y="5" width="16" height="14" rx="1" />
        </svg>
      )
    case 'ellipse':
      return (
        <svg {...COMMON}>
          <ellipse cx="12" cy="12" rx="8" ry="6" />
        </svg>
      )
    case 'triangle':
      return (
        <svg {...COMMON}>
          <path d="M12 5 20 19H4L12 5Z" />
        </svg>
      )
  }
}
