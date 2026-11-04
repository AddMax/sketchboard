import { describe, expect, it } from 'vitest'
import { toDataUrl } from '../src/application/shared/dataUrl'

describe('data-URL из байтов', () => {
  it('кодирует содержимое и тип', async () => {
    expect(await toDataUrl(new Blob(['привет'], { type: 'text/plain' }))).toBe(
      `data:text/plain;base64,${Buffer.from('привет').toString('base64')}`,
    )
  })

  it('не ломается на файле больше одного куска', async () => {
    const bytes = new Uint8Array(200_000).map((_, i) => i % 256)

    const url = await toDataUrl(new Blob([bytes], { type: 'image/png' }))

    expect(Buffer.from(url.split(',')[1], 'base64')).toEqual(Buffer.from(bytes))
  })
})
