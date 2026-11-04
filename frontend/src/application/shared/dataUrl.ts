/**
 * Байты → data-URL для Excalidraw: он хранит картинки именно так и для SVG
 * разбирает содержимое строки. FileReader есть только в браузере, а btoa —
 * и в Node, где живут тесты; строка собирается кусками, чтобы не упереться
 * в предел аргументов у String.fromCharCode на больших файлах.
 */
export async function toDataUrl(blob: Blob): Promise<string> {
  const bytes = new Uint8Array(await blob.arrayBuffer())
  const chunk = 0x8000
  let binary = ''

  for (let offset = 0; offset < bytes.length; offset += chunk) {
    binary += String.fromCharCode(...bytes.subarray(offset, offset + chunk))
  }

  return `data:${blob.type};base64,${btoa(binary)}`
}
