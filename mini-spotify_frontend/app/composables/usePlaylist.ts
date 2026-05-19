import { useApi } from '~/composables/useApi'

export interface Playlist {
  id: number
  title: string
  user_uid: string
  is_private?: boolean
  cover_image_path?: string | null
  cover_image_url?: string | null
  songs_count?: number
  is_in_library?: boolean
  user?: {
    uid: string
    name: string
    avatar_path?: string | null
    avatar_url?: string | null
    artist?: {
      id: number
      name: string
    } | null
  }
  songs?: PlaylistSong[]
}

export interface PlaylistSong {
  id: number
  title: string
  audio_path?: string
  audio_url?: string
  duration?: number | null
  play_count?: number | null
  tempo?: number | null
  energy?: number | null
  danceability?: number | null
  valence?: number | null
  popularity?: number | null
  release_date?: string | null
  artist?: { id: number; name: string }
  album?: { id: number; title: string; cover_image_path?: string | null; cover_image_url?: string | null }
  genre?: { id: number; name: string }
}

interface PlaylistResult {
  success: boolean
  data?: any
  message?: string
}

export const usePlaylist = () => {
  const { apiFetch } = useApi()

  const getMyPlaylists = async (): Promise<Playlist[]> => {
    return await apiFetch<Playlist[]>('/playlists/mine')
  }

  const getPlaylists = async (): Promise<Playlist[]> => {
    return await apiFetch<Playlist[]>('/playlists')
  }

  const getPlaylist = async (id: number | string): Promise<Playlist> => {
    return await apiFetch<Playlist>(`/playlists/${id}`)
  }

  const createPlaylist = async (data: { title: string; cover?: File | null; is_private?: boolean }): Promise<PlaylistResult> => {
    try {
      const formData = new FormData()
      formData.append('title', data.title)
      if (data.cover) {
        formData.append('cover_image_path', data.cover)
      }
      formData.append('is_private', data.is_private ? '1' : '0')
      const response = await apiFetch('/playlists', {
        method: 'POST',
        body: formData,
      })
      return { success: true, data: response, message: 'Playlist created' }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not create playlist' }
    }
  }

  const updatePlaylist = async (
    id: number | string,
    data: { title: string; cover?: File | null; is_private?: boolean }
  ): Promise<PlaylistResult> => {
    try {
      const formData = new FormData()
      formData.append('title', data.title)
      if (data.cover) {
        formData.append('cover_image_path', data.cover)
      }
      formData.append('is_private', data.is_private ? '1' : '0')
      const response = await apiFetch(`/playlists/${id}`, {
        method: 'POST',
        body: formData,
      })
      return { success: true, data: response, message: 'Playlist updated' }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not update playlist' }
    }
  }

  const deletePlaylist = async (id: number | string): Promise<PlaylistResult> => {
    try {
      await apiFetch(`/playlists/${id}`, { method: 'DELETE' })
      return { success: true, message: 'Playlist deleted' }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not delete playlist' }
    }
  }

  const addSongToPlaylist = async (playlistId: number | string, songId: number): Promise<PlaylistResult> => {
    try {
      const response = await apiFetch(`/playlists/${playlistId}/songs`, {
        method: 'POST',
        body: { song_id: songId },
      })
      return { success: true, data: response }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not add track' }
    }
  }

  const removeSongFromPlaylist = async (playlistId: number | string, songId: number): Promise<PlaylistResult> => {
    try {
      const response = await apiFetch(`/playlists/${playlistId}/songs/${songId}`, {
        method: 'DELETE',
      })
      return { success: true, data: response }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not remove track' }
    }
  }

  const getAllSongs = async (): Promise<PlaylistSong[]> => {
    return await apiFetch<PlaylistSong[]>('/songs')
  }

  return {
    getMyPlaylists,
    getPlaylists,
    getPlaylist,
    createPlaylist,
    updatePlaylist,
    deletePlaylist,
    addSongToPlaylist,
    removeSongFromPlaylist,
    getAllSongs,
  }
}
