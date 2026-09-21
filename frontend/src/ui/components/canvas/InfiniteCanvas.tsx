import { runInAction } from 'mobx'
import { observer, useLocalObservable } from 'mobx-react-lite'
import { useCallback, useEffect, useRef } from 'react'
import type { PointerEvent as ReactPointerEvent } from 'react'
import type { SaveStatus } from '../../../application/drawing/DrawingStore'
import {
  BRUSH_WIDTH_MAX,
  BRUSH_WIDTH_MIN,
  DRAWING_MAX_ELEMENTS,
  newElementId,
  roundPoint,
  shapeBetween,
} from '../../../domain/drawing/Drawing'
import type { DrawingElement, DrawingLine, Point, Shape } from '../../../domain/drawing/Drawing'
import {
  gridStep,
  initialViewport,
  panBy,
  screenToWorld,
  visibleBounds,
  zoomAt,
} from '../../../domain/drawing/Viewport'
import type { Viewport } from '../../../domain/drawing/Viewport'
import { ToolIcon } from './ToolIcons'
import type { Tool } from './ToolIcons'

/**
 * То, что полотну нужно от владельца рисунка. Интерфейс структурный:
 * ему соответствует DrawingStore, а в тестах — любой наблюдаемый объект.
 */
export interface CanvasDocument {
  readonly elements: readonly DrawingElement[]
  readonly saveStatus: SaveStatus
  /** Полный список элементов после изменения; когда сохранять — решает владелец. */
  replaceElements(elements: DrawingElement[]): void
  retrySave(): void
}

export interface InfiniteCanvasProps {
  drawing: CanvasDocument
}

const TOOLS: ReadonlyArray<{ value: Tool; label: string }> = [
  { value: 'brush', label: 'Кисть' },
  { value: 'rect', label: 'Прямоугольник' },
  { value: 'ellipse', label: 'Эллипс' },
  { value: 'triangle', label: 'Треугольник' },
]

const COLOR_PRESETS: ReadonlyArray<{ value: string; label: string }> = [
  { value: '#000000', label: 'Чёрный' },
  { value: '#e53935', label: 'Красный' },
  { value: '#1e88e5', label: 'Синий' },
]

const DEFAULT_WIDTH = 3
const WHEEL_SENSITIVITY = 0.0015
const GRID_MAJOR_EVERY = 5

// Firefox отдаёт deltaY в строках (deltaMode 1), а не в пикселях: без
// приведения один щелчок колеса менял бы масштаб на доли процента
const WHEEL_LINE_PX = 16
const WHEEL_MAX_STEP_PX = 240

function wheelDeltaPixels(event: WheelEvent, pageHeight: number): number {
  const delta =
    event.deltaMode === WheelEvent.DOM_DELTA_LINE
      ? event.deltaY * WHEEL_LINE_PX
      : event.deltaMode === WheelEvent.DOM_DELTA_PAGE
        ? event.deltaY * pageHeight
        : event.deltaY

  return Math.max(-WHEEL_MAX_STEP_PX, Math.min(WHEEL_MAX_STEP_PX, delta))
}

const STATUS_LABELS: Record<SaveStatus, string> = {
  saved: 'Сохранено',
  saving: 'Сохранение…',
  error: 'Ошибка сохранения — нажмите, чтобы повторить',
}

type PanMode = 'none' | 'ready' | 'panning'

interface Stroke {
  line: DrawingLine
  last: Point
}

/** Растягиваемая фигура: точка начала фиксирована, второй угол ведёт курсор. */
interface ShapeDraft {
  start: Point
  shape: Shape
}

interface Drag {
  startClient: Point
  startView: Viewport
}

/**
 * Бесконечная доска для рисования на Canvas API.
 *
 * Дорогие вещи намеренно вынесены из React-состояния: вьюпорт, текущий
 * штрих, растягиваемая фигура и размеры полотна живут в ref, а наблюдаемым
 * остаётся только то, что видно в панели (инструмент, цвет, толщина,
 * масштаб, статус). Пока идёт штрих, сегменты рисуются прямо в контекст —
 * перерисовка всей доски случается при сдвиге, зуме, ресайзе, завершении
 * штриха и на каждом кадре растягивания фигуры (как и при сдвиге доски).
 */
export const InfiniteCanvas = observer(function InfiniteCanvas({ drawing }: InfiniteCanvasProps) {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const canvasRef = useRef<HTMLCanvasElement | null>(null)

  // Состояние панели — локальное: кисть, масштаб для индикатора и режим сдвига.
  // Меняется редко (по клику, по завершении жеста), поэтому ему можно быть наблюдаемым
  const tools = useLocalObservable(() => ({
    tool: 'brush' as Tool,
    color: COLOR_PRESETS[0].value,
    width: DEFAULT_WIDTH,
    zoomPercent: 100,
    panMode: 'none' as PanMode,
    setTool(value: Tool) {
      this.tool = value
    },
    setColor(value: string) {
      this.color = value
    },
    setWidth(value: number) {
      this.width = value
    },
    setZoom(zoom: number) {
      this.zoomPercent = Math.round(zoom * 100)
    },
    setPanMode(mode: PanMode) {
      this.panMode = mode
    },
  }))

  const { elements } = drawing

  const elementsRef = useRef<readonly DrawingElement[]>(elements)
  const viewRef = useRef<Viewport>(initialViewport)
  const strokeRef = useRef<Stroke | null>(null)
  const shapeRef = useRef<ShapeDraft | null>(null)
  const dragRef = useRef<Drag | null>(null)
  const spaceHeldRef = useRef(false)
  const frameRef = useRef<number | null>(null)

  const commitElements = useCallback(
    (next: DrawingElement[]) => {
      elementsRef.current = next
      drawing.replaceElements(next)
    },
    [drawing],
  )

  // ── Отрисовка ─────────────────────────────────────────────────────────

  const strokePath = useCallback((ctx: CanvasRenderingContext2D, line: DrawingLine) => {
    if (line.points.length === 0) return

    ctx.strokeStyle = line.color
    ctx.lineWidth = line.width
    ctx.beginPath()

    const [first, ...rest] = line.points
    ctx.moveTo(first.x, first.y)

    // Точка без продолжения — всё равно видимая отметка
    if (rest.length === 0) {
      ctx.lineTo(first.x + 0.01, first.y)
    }

    for (const point of rest) {
      ctx.lineTo(point.x, point.y)
    }

    ctx.stroke()
  }, [])

  const drawShape = useCallback((ctx: CanvasRenderingContext2D, shape: Shape) => {
    const { x, y, width, height } = shape

    ctx.beginPath()

    switch (shape.kind) {
      case 'rect':
        ctx.rect(x, y, width, height)
        break
      case 'ellipse':
        ctx.ellipse(x + width / 2, y + height / 2, width / 2, height / 2, 0, 0, Math.PI * 2)
        break
      case 'triangle':
        // Вершина в середине верхней стороны, основание — нижняя сторона рамки
        ctx.moveTo(x + width / 2, y)
        ctx.lineTo(x + width, y + height)
        ctx.lineTo(x, y + height)
        ctx.closePath()
        break
    }

    if (shape.fill !== null) {
      ctx.fillStyle = shape.fill
      ctx.fill()
    }

    ctx.strokeStyle = shape.strokeColor
    ctx.lineWidth = shape.strokeWidth
    ctx.stroke()
  }, [])

  const drawElement = useCallback(
    (ctx: CanvasRenderingContext2D, element: DrawingElement) => {
      if (element.type === 'line') {
        strokePath(ctx, element)
      } else {
        drawShape(ctx, element)
      }
    },
    [drawShape, strokePath],
  )

  const drawGrid = useCallback(
    (ctx: CanvasRenderingContext2D, view: Viewport, cssWidth: number, cssHeight: number) => {
      const step = gridStep(view.zoom)
      const bounds = visibleBounds(view, cssWidth, cssHeight)
      const styles = getComputedStyle(ctx.canvas)
      const minor = styles.getPropertyValue('--color-grid').trim() || 'rgba(0,0,0,0.06)'
      const major = styles.getPropertyValue('--color-grid-major').trim() || 'rgba(0,0,0,0.12)'

      ctx.lineWidth = 1

      // Сетка рисуется в экранных координатах, чтобы линии были ровно в пиксель
      const firstColumn = Math.floor(bounds.left / step)
      const lastColumn = Math.ceil(bounds.right / step)
      for (let column = firstColumn; column <= lastColumn; column++) {
        const x = Math.round(column * step * view.zoom + view.pan.x) + 0.5
        ctx.strokeStyle = column % GRID_MAJOR_EVERY === 0 ? major : minor
        ctx.beginPath()
        ctx.moveTo(x, 0)
        ctx.lineTo(x, cssHeight)
        ctx.stroke()
      }

      const firstRow = Math.floor(bounds.top / step)
      const lastRow = Math.ceil(bounds.bottom / step)
      for (let row = firstRow; row <= lastRow; row++) {
        const y = Math.round(row * step * view.zoom + view.pan.y) + 0.5
        ctx.strokeStyle = row % GRID_MAJOR_EVERY === 0 ? major : minor
        ctx.beginPath()
        ctx.moveTo(0, y)
        ctx.lineTo(cssWidth, y)
        ctx.stroke()
      }
    },
    [],
  )

  /** Применяет вьюпорт к контексту: дальше рисуем в координатах доски. */
  const applyView = useCallback((ctx: CanvasRenderingContext2D, view: Viewport) => {
    const dpr = window.devicePixelRatio || 1
    ctx.setTransform(dpr * view.zoom, 0, 0, dpr * view.zoom, dpr * view.pan.x, dpr * view.pan.y)
    ctx.lineCap = 'round'
    ctx.lineJoin = 'round'
  }, [])

  const redraw = useCallback(() => {
    const canvas = canvasRef.current
    const ctx = canvas?.getContext('2d')
    if (!canvas || !ctx) return

    const dpr = window.devicePixelRatio || 1
    const cssWidth = canvas.width / dpr
    const cssHeight = canvas.height / dpr
    const view = viewRef.current

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
    ctx.clearRect(0, 0, cssWidth, cssHeight)
    drawGrid(ctx, view, cssWidth, cssHeight)

    applyView(ctx, view)
    for (const element of elementsRef.current) {
      drawElement(ctx, element)
    }

    // Незавершённый штрих тоже нужно перерисовать, если во время него сдвинули доску
    if (strokeRef.current !== null) {
      strokePath(ctx, strokeRef.current.line)
    }

    // Растягиваемая фигура рисуется поверх всего: она ещё не в списке
    if (shapeRef.current !== null) {
      drawShape(ctx, shapeRef.current.shape)
    }
  }, [applyView, drawElement, drawGrid, drawShape, strokePath])

  const scheduleRedraw = useCallback(() => {
    if (frameRef.current !== null) return

    frameRef.current = window.requestAnimationFrame(() => {
      frameRef.current = null
      redraw()
    })
  }, [redraw])

  // ── Координаты ────────────────────────────────────────────────────────

  /** Экранные clientX/Y → точка на бесконечной доске с учётом pan и zoom. */
  const toWorld = useCallback((clientX: number, clientY: number): Point => {
    const canvas = canvasRef.current
    if (!canvas) return { x: 0, y: 0 }

    const rect = canvas.getBoundingClientRect()

    return screenToWorld(viewRef.current, { x: clientX - rect.left, y: clientY - rect.top })
  }, [])

  const setView = useCallback(
    (view: Viewport) => {
      viewRef.current = view
      tools.setZoom(view.zoom)
      scheduleRedraw()
    },
    [scheduleRedraw, tools],
  )

  // ── Размер полотна ────────────────────────────────────────────────────

  useEffect(() => {
    const container = containerRef.current
    const canvas = canvasRef.current
    if (!container || !canvas) return

    const resize = () => {
      const dpr = window.devicePixelRatio || 1
      const { width: cssWidth, height: cssHeight } = container.getBoundingClientRect()

      // Буфер в физических пикселях, CSS-размер прежний — картинка не мылится на Retina
      canvas.width = Math.max(1, Math.round(cssWidth * dpr))
      canvas.height = Math.max(1, Math.round(cssHeight * dpr))
      canvas.style.width = `${cssWidth}px`
      canvas.style.height = `${cssHeight}px`

      redraw()
    }

    resize()

    const observer = new ResizeObserver(resize)
    observer.observe(container)
    window.addEventListener('resize', resize)

    return () => {
      observer.disconnect()
      window.removeEventListener('resize', resize)
    }
  }, [redraw])

  // ── Зум колесом ───────────────────────────────────────────────────────

  useEffect(() => {
    const canvas = canvasRef.current
    if (!canvas) return

    // Нативный слушатель: у React onWheel пассивный, и preventDefault там не работает
    const onWheel = (event: WheelEvent) => {
      event.preventDefault()

      const rect = canvas.getBoundingClientRect()
      const anchor = { x: event.clientX - rect.left, y: event.clientY - rect.top }
      const factor = Math.exp(-wheelDeltaPixels(event, rect.height) * WHEEL_SENSITIVITY)

      setView(zoomAt(viewRef.current, anchor, factor))
    }

    // Средняя кнопка по умолчанию включает автопрокрутку страницы
    const onMouseDown = (event: MouseEvent) => {
      if (event.button === 1) event.preventDefault()
    }

    canvas.addEventListener('wheel', onWheel, { passive: false })
    canvas.addEventListener('mousedown', onMouseDown)

    return () => {
      canvas.removeEventListener('wheel', onWheel)
      canvas.removeEventListener('mousedown', onMouseDown)
    }
  }, [setView])

  // ── Мышь: движение и отпускание ловим на window ───────────────────────

  useEffect(() => {
    const onPointerMove = (event: PointerEvent) => {
      const drag = dragRef.current
      if (drag !== null) {
        setView(
          panBy(
            drag.startView,
            event.clientX - drag.startClient.x,
            event.clientY - drag.startClient.y,
          ),
        )
        return
      }

      const draft = shapeRef.current
      if (draft !== null) {
        // Фигура меняется целиком, поэтому кадр перерисовывается полностью —
        // как при сдвиге доски; requestAnimationFrame схлопывает лишние события
        draft.shape = shapeBetween(
          draft.shape.id,
          draft.shape.kind,
          draft.start,
          toWorld(event.clientX, event.clientY),
          draft.shape,
          event.shiftKey,
        )
        scheduleRedraw()
        return
      }

      const stroke = strokeRef.current
      const ctx = canvasRef.current?.getContext('2d')
      if (stroke === null || !ctx) return

      const point = roundPoint(toWorld(event.clientX, event.clientY))
      if (point.x === stroke.last.x && point.y === stroke.last.y) return

      stroke.line.points.push(point)

      // Сегмент — сразу в контекст, минуя React: иначе на каждом пикселе был бы рендер
      applyView(ctx, viewRef.current)
      ctx.strokeStyle = stroke.line.color
      ctx.lineWidth = stroke.line.width
      ctx.beginPath()
      ctx.moveTo(stroke.last.x, stroke.last.y)
      ctx.lineTo(point.x, point.y)
      ctx.stroke()

      stroke.last = point
    }

    const onPointerUp = () => {
      if (dragRef.current !== null) {
        dragRef.current = null
        tools.setPanMode(spaceHeldRef.current ? 'ready' : 'none')
      }

      const stroke = strokeRef.current
      if (stroke !== null) {
        strokeRef.current = null
        commitElements([...elementsRef.current, stroke.line])
      }

      const draft = shapeRef.current
      if (draft !== null) {
        shapeRef.current = null
        commitElements([...elementsRef.current, draft.shape])
      }
    }

    window.addEventListener('pointermove', onPointerMove)
    window.addEventListener('pointerup', onPointerUp)
    window.addEventListener('pointercancel', onPointerUp)

    return () => {
      window.removeEventListener('pointermove', onPointerMove)
      window.removeEventListener('pointerup', onPointerUp)
      window.removeEventListener('pointercancel', onPointerUp)
    }
  }, [applyView, commitElements, scheduleRedraw, setView, toWorld, tools])

  // ── Пробел: режим панорамирования ─────────────────────────────────────

  useEffect(() => {
    const isTyping = (target: EventTarget | null) =>
      target instanceof HTMLElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)

    const onKeyDown = (event: KeyboardEvent) => {
      // Esc бросает растягиваемую фигуру: в список она ещё не попала
      if (event.code === 'Escape' && shapeRef.current !== null) {
        shapeRef.current = null
        scheduleRedraw()
        return
      }

      if (event.code !== 'Space' || isTyping(event.target)) return

      event.preventDefault()
      spaceHeldRef.current = true
      // Идущий сдвиг средней кнопкой пробел не прерывает
      runInAction(() => {
        if (tools.panMode !== 'panning') tools.panMode = 'ready'
      })
    }

    const onKeyUp = (event: KeyboardEvent) => {
      if (event.code !== 'Space') return

      spaceHeldRef.current = false
      runInAction(() => {
        if (tools.panMode !== 'panning') tools.panMode = 'none'
      })
    }

    // Переключение вкладки во время зажатого пробела: keyup не придёт
    const onBlur = () => {
      spaceHeldRef.current = false
      tools.setPanMode('none')
    }

    window.addEventListener('keydown', onKeyDown)
    window.addEventListener('keyup', onKeyUp)
    window.addEventListener('blur', onBlur)

    return () => {
      window.removeEventListener('keydown', onKeyDown)
      window.removeEventListener('keyup', onKeyUp)
      window.removeEventListener('blur', onBlur)
    }
  }, [scheduleRedraw, tools])

  // Отложенный кадр не должен пережить компонент. Ref обязательно обнуляем:
  // StrictMode перезапускает эффекты, и с «висящим» id scheduleRedraw
  // считал бы, что кадр уже заказан, и больше никогда не перерисовывал
  useEffect(() => {
    return () => {
      if (frameRef.current !== null) {
        window.cancelAnimationFrame(frameRef.current)
        frameRef.current = null
      }
    }
  }, [])

  // ── Начало действия: только на самом полотне ──────────────────────────

  const onPointerDown = (event: ReactPointerEvent<HTMLCanvasElement>) => {
    const middleButton = event.button === 1
    const primary = event.button === 0

    if (middleButton || (primary && spaceHeldRef.current)) {
      event.preventDefault()
      dragRef.current = {
        startClient: { x: event.clientX, y: event.clientY },
        startView: viewRef.current,
      }
      tools.setPanMode('panning')
      return
    }

    if (!primary || strokeRef.current !== null || shapeRef.current !== null) return
    if (elementsRef.current.length >= DRAWING_MAX_ELEMENTS) return

    const point = roundPoint(toWorld(event.clientX, event.clientY))

    if (tools.tool === 'brush') {
      strokeRef.current = {
        line: { type: 'line', id: newElementId(), points: [point], color: tools.color, width: tools.width },
        last: point,
      }
    } else {
      const style = { strokeColor: tools.color, strokeWidth: tools.width }
      shapeRef.current = {
        start: point,
        shape: shapeBetween(newElementId(), tools.tool, point, point, style),
      }
    }

    // Захват указателя: жест продолжается, даже если курсор вышел за полотно
    event.currentTarget.setPointerCapture(event.pointerId)
  }

  // ── Панель ────────────────────────────────────────────────────────────

  const clearAll = () => {
    if (elementsRef.current.length === 0) return
    if (!window.confirm('Стереть весь рисунок? Отменить это будет нельзя.')) return

    commitElements([])
    scheduleRedraw()
  }

  const resetZoom = () => {
    const canvas = canvasRef.current
    if (!canvas) return

    const rect = canvas.getBoundingClientRect()
    setView(zoomAt(viewRef.current, { x: rect.width / 2, y: rect.height / 2 }, 1 / viewRef.current.zoom))
  }

  // Список элементов изменился — перерисовать (в том числе после «Очистить всё»)
  useEffect(() => {
    elementsRef.current = elements
    scheduleRedraw()
  }, [elements, scheduleRedraw])

  const { tool, panMode, color, width, zoomPercent } = tools
  const status = drawing.saveStatus
  const modifier = panMode === 'panning' ? 'canvas--grabbing' : panMode === 'ready' ? 'canvas--grab' : ''

  return (
    <div ref={containerRef} className={`canvas ${modifier}`.trim()}>
      <canvas
        ref={canvasRef}
        className="canvas__surface"
        onPointerDown={onPointerDown}
        onContextMenu={(event) => event.preventDefault()}
        aria-label="Полотно для рисования"
      />

      <div className="canvas__toolbar" role="toolbar" aria-label="Инструменты">
        <div className="canvas__group" role="radiogroup" aria-label="Инструмент">
          {TOOLS.map((item) => (
            <button
              key={item.value}
              type="button"
              role="radio"
              aria-checked={tool === item.value}
              aria-label={item.label}
              title={item.label}
              className={`canvas__tool ${tool === item.value ? 'canvas__tool--active' : ''}`.trim()}
              onClick={() => tools.setTool(item.value)}
            >
              <ToolIcon tool={item.value} />
            </button>
          ))}
        </div>

        <span className="canvas__divider" />

        <div className="canvas__group" role="radiogroup" aria-label="Цвет">
          {COLOR_PRESETS.map((preset) => (
            <button
              key={preset.value}
              type="button"
              role="radio"
              aria-checked={color === preset.value}
              aria-label={preset.label}
              title={preset.label}
              className={`canvas__swatch ${color === preset.value ? 'canvas__swatch--active' : ''}`.trim()}
              style={{ background: preset.value }}
              onClick={() => tools.setColor(preset.value)}
            />
          ))}
        </div>

        <span className="canvas__divider" />

        <label className="canvas__width">
          <span>Кисть</span>
          <input
            type="range"
            min={BRUSH_WIDTH_MIN}
            max={BRUSH_WIDTH_MAX}
            step={1}
            value={width}
            onChange={(event) => tools.setWidth(Number(event.target.value))}
            aria-label="Толщина кисти"
          />
          <output className="canvas__width-value">{width}</output>
        </label>

        <span className="canvas__divider" />

        <button
          type="button"
          className="canvas__zoom"
          onClick={resetZoom}
          title="Сбросить масштаб к 100%"
        >
          {zoomPercent}%
        </button>

        <span className="canvas__divider" />

        <button
          type="button"
          className="canvas__clear"
          onClick={clearAll}
          disabled={elements.length === 0}
        >
          Очистить всё
        </button>
      </div>

      <button
        type="button"
        className={`canvas__status canvas__status--${status}`}
        onClick={drawing.retrySave}
        disabled={status !== 'error'}
        aria-live="polite"
      >
        {STATUS_LABELS[status]}
      </button>

      <p className="canvas__hint">
        Колесо — масштаб, Пробел + мышь или средняя кнопка — сдвиг, Shift — пропорции фигуры, Esc — отмена
      </p>
    </div>
  )
})
