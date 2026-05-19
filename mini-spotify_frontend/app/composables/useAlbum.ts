import { useApi } from '~/composables/useApi'

export interface Album {
  id: number
  title: string
  cover_image?: string
  cover_image_path?: string
  cover_image_url?: string
  is_in_library?: boolean
  artist?: {
    id: number
    name: string
    user_uid?: string
  }
  songs?: {
    id: number
    title: string
    genre_id?: number
    audio_path?: string
    audio_url?: string
    duration?: number | null
    play_count?: number | null
    genre?: {
      id: number
      name: string
    }
  }[]
}

interface CreateAlbumData {
  title: string
  cover_image?: File | null
  tracks?: {
    title: string
    genre_id: string | number
    audio_file: File
    duration?: number | null
  }[]
}

interface UpdateAlbumData {
  title: string
  cover_image?: File | null
  tracks?: {
    title: string
    genre_id: string | number
    audio_file: File
    duration?: number | null
  }[]
}

interface AlbumResponse {
  success: boolean
  data?: any
  message?: string
}

interface TrackStreamUrlResponse {
  stream_url: string
  play_count?: number
}

export const useAlbum = () => {
  const { apiFetch } = useApi()

  const createAlbum = async (albumData: CreateAlbumData): Promise<AlbumResponse> => {
    try {
      const formData = new FormData()
      formData.append('title', albumData.title)
      if(albumData.cover_image)
        formData.append('cover_image', albumData.cover_image)
      ;(albumData.tracks ?? []).forEach((track, index) => {
        formData.append(`tracks[${index}][title]`, track.title)
        formData.append(`tracks[${index}][genre_id]`, String(track.genre_id))
        formData.append(`tracks[${index}][audio_file]`, track.audio_file)
        if (track.duration !== undefined && track.duration !== null) {
          formData.append(`tracks[${index}][duration]`, String(track.duration))
        }
      })
      const response = await apiFetch('/albums', {
        method: 'POST',
        body: formData
      })
      return { success: true, data: response, message: 'Album created successfully' }
    } catch (error: any) {
      const message = error?.data?.message || 'Error while creating album'
      return { success: false, message }
    }
  }

  const getAlbums = async (): Promise<Album[]> => {
    return await apiFetch<Album[]>('/albums')
  }

  const getMyAlbums = async (): Promise<Album[]> => {
    return await apiFetch<Album[]>('/artists/me/albums')
  }

  const getArtistAlbums = async (artistId: number | string): Promise<Album[]> => {
    return await apiFetch<Album[]>(`/artists/${artistId}/albums`)
  }

  const getLatestAlbums = async (): Promise<Album[]> => {
    return await apiFetch<Album[]>('/albums/latest')
  }

  const getArtistAlbum = async (artistId: number | string, albumId: number | string): Promise<Album> => {
    return await apiFetch<Album>(`/artists/${artistId}/albums/${albumId}`)
  }

  const updateAlbum = async (albumId: number | string, albumData: UpdateAlbumData): Promise<AlbumResponse> => {
    try {
      const formData = new FormData()
      formData.append('title', albumData.title)

      if (albumData.cover_image) {
        formData.append('cover_image', albumData.cover_image)
      }

      ;(albumData.tracks ?? []).forEach((track, index) => {
        formData.append(`tracks[${index}][title]`, track.title)
        formData.append(`tracks[${index}][genre_id]`, String(track.genre_id))
        formData.append(`tracks[${index}][audio_file]`, track.audio_file)
        if (track.duration !== undefined && track.duration !== null) {
          formData.append(`tracks[${index}][duration]`, String(track.duration))
        }
      })

      const response = await apiFetch(`/albums/${albumId}`, {
        method: 'POST',
        body: formData,
      })

      return { success: true, data: response, message: 'Album updated successfully' }
    } catch (error: any) {
      const message = error?.data?.message || 'Error while updating album'
      return { success: false, message }
    }
  }

  const deleteTrack = async (trackId: number | string): Promise<void> => {
    await apiFetch(`/songs/${trackId}`, {
      method: 'DELETE',
    })
  }

  const deleteAlbum = async (albumId: number | string): Promise<AlbumResponse> => {
    try {
      await apiFetch(`/albums/${albumId}`, {
        method: 'DELETE',
      })

      return { success: true, message: 'Album deleted successfully' }
    } catch (error: any) {
      const message = error?.data?.message || 'Error while deleting album'
      return { success: false, message }
    }
  }

  const getTrackStreamUrl = async (trackId: number): Promise<string> => {
    const response = await apiFetch<TrackStreamUrlResponse>(`/tracks/${trackId}/stream-url`)

    return response.stream_url
  }

  return {
    createAlbum,
    getAlbums,
    getMyAlbums,
    getArtistAlbums,
    getLatestAlbums,
    getArtistAlbum,
    updateAlbum,
    deleteAlbum,
    deleteTrack,
    getTrackStreamUrl
  }
}
 
