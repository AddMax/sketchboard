import type { FileRepository } from '../../application/ports/FileRepository'
import type { FileDraft, StoredFile } from '../../domain/file/StoredFile'
import { jsonRequest } from './jsonRequest'

/** Адаптер порта файлов: multipart на загрузку, JSON на остальное. */
export class HttpFileRepository implements FileRepository {
  constructor(private readonly baseUrl: string) {}

  upload(data: Blob, draft: FileDraft): Promise<StoredFile> {
    const body = new FormData()
    body.append('file', data, draft.name)
    body.append('visibility', draft.visibility)
    body.append('owner', draft.owner)

    // Content-Type с boundary выставит сам браузер; заголовок JSON из
    // jsonRequest тут только помешал бы, поэтому его убираем
    return jsonRequest<StoredFile>(`${this.baseUrl}/files`, { method: 'POST', body, headers: {} })
  }

  get(id: string, requester: string): Promise<StoredFile> {
    return jsonRequest<StoredFile>(this.url(id, '', requester))
  }

  async remove(id: string, requester: string): Promise<void> {
    await jsonRequest<void>(this.url(id, '', requester), { method: 'DELETE' })
  }

  contentUrl(id: string, requester: string): string {
    return this.url(id, '/content', requester)
  }

  private url(id: string, suffix: string, requester: string): string {
    const query = requester === '' ? '' : `?requester=${encodeURIComponent(requester)}`

    return `${this.baseUrl}/files/${encodeURIComponent(id)}${suffix}${query}`
  }
}
