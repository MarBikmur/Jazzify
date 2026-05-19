import { useApi } from '~/composables/useApi'

export const ADMIN_ENTITIES = ['users', 'artists', 'genres', 'countries', 'albums', 'playlists', 'songs', 'comments'] as const

export type AdminEntity = typeof ADMIN_ENTITIES[number]

export interface AdminFieldMeta {
  key: string
  label: string
  type: 'text' | 'number' | 'boolean' | 'datetime' | 'select'
  editable: boolean
  nullable?: boolean
  options?: Array<{
    value: string | number
    label: string
  }> | null
}

export interface AdminListRow extends Record<string, any> {
  _primary: string | number
  _public_url?: string | null
  _title?: string | null
}

export interface AdminListResponse {
  entity: AdminEntity
  columns: string[]
  rows: AdminListRow[]
  meta: {
    limit: number
    offset: number
    total: number
    has_more: boolean
    search: string
  }
}

export interface AdminRecordResponse {
  entity: AdminEntity
  record: Record<string, any>
  fields: AdminFieldMeta[]
  primary: string
  public_url?: string | null
  title: string
}

export interface AdminSongUploadResponse {
  draft: Record<string, any>
  spotify_used: boolean
  audio_features_used: boolean
  warnings?: string[]
  duplicate?: {
    id: number
    title?: string | null
    artist?: string | null
    album?: string | null
    public_url?: string | null
  } | null
}

export interface AdminSongCreateResponse {
  song: Record<string, any>
  artist: Record<string, any>
  album: Record<string, any>
  genre: Record<string, any>
  draft: Record<string, any>
  public_url?: string | null
  title?: string | null
  reuploaded?: boolean
}

const ENTITY_LABELS: Record<AdminEntity, string> = {
  users: 'Users',
  artists: 'Artists',
  genres: 'Genres',
  countries: 'Countries',
  albums: 'Albums',
  playlists: 'Playlists',
  songs: 'Songs',
  comments: 'Comments',
}

export const useAdmin = () => {
  const { apiFetch } = useApi()

  const isAdminEntity = (value: string): value is AdminEntity => {
    return ADMIN_ENTITIES.includes(value as AdminEntity)
  }

  const getEntityLabel = (entity: AdminEntity) => ENTITY_LABELS[entity]

  const getAdminEntityList = async (entity: AdminEntity, params: { q?: string; offset?: number; limit?: number } = {}) => {
    const searchParams = new URLSearchParams()
    const normalizedQuery = params.q?.trim().toLocaleLowerCase()

    if (normalizedQuery) {
      searchParams.set('q', normalizedQuery)
    }

    if (params.offset !== undefined) {
      searchParams.set('offset', String(params.offset))
    }

    if (params.limit !== undefined) {
      searchParams.set('limit', String(params.limit))
    }

    const query = searchParams.toString()
    return await apiFetch<AdminListResponse>(`/admin/${entity}${query ? `?${query}` : ''}`)
  }

  const getAdminEntityRecord = async (entity: AdminEntity, id: string) => {
    return await apiFetch<AdminRecordResponse>(`/admin/${entity}/${encodeURIComponent(id)}`)
  }

  const updateAdminEntityRecord = async (entity: AdminEntity, id: string, payload: Record<string, any>) => {
    return await apiFetch<AdminRecordResponse>(`/admin/${entity}/${encodeURIComponent(id)}`, {
      method: 'PUT',
      body: payload,
    })
  }

  const createAdminEntityRecord = async (entity: AdminEntity, payload: Record<string, any>) => {
    return await apiFetch<{ success: boolean; primary: string | number; public_url?: string | null; title?: string | null }>(`/admin/${entity}`, {
      method: 'POST',
      body: payload,
    })
  }

  const deleteAdminEntityRecord = async (entity: AdminEntity, id: string) => {
    return await apiFetch<{ success: boolean; message?: string }>(`/admin/${entity}/${encodeURIComponent(id)}`, {
      method: 'DELETE',
    })
  }

  const analyzeAdminSong = async (audioFile: File, duration?: number | null) => {
    const formData = new FormData()
    formData.append('audio_file', audioFile)
    if (duration !== undefined && duration !== null) {
      formData.append('duration', String(duration))
    }

    return await apiFetch<AdminSongUploadResponse>('/admin/songs/upload/analyze', {
      method: 'POST',
      body: formData,
    })
  }

  const createAdminSong = async (audioFile: File, payload: Record<string, any>) => {
    const formData = new FormData()
    formData.append('audio_file', audioFile)

    Object.entries(payload).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        if (typeof value === 'boolean') {
          formData.append(key, value ? '1' : '0')
          return
        }

        formData.append(key, String(value))
      }
    })

    return await apiFetch<AdminSongCreateResponse>('/admin/songs/upload', {
      method: 'POST',
      body: formData,
    })
  }

  return {
    adminEntities: ADMIN_ENTITIES,
    isAdminEntity,
    getEntityLabel,
    getAdminEntityList,
    getAdminEntityRecord,
    updateAdminEntityRecord,
    createAdminEntityRecord,
    deleteAdminEntityRecord,
    analyzeAdminSong,
    createAdminSong,
  }
}
