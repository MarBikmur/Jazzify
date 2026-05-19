import { useApi } from '~/composables/useApi'
import type { Album } from '~/composables/useAlbum'
import type { Artist } from '~/composables/useArtist'
import type { Playlist } from '~/composables/usePlaylist'
import type { PublicProfileUser } from '~/composables/useUserProfile'

interface LibraryMutationResult<T> {
  success: boolean
  data?: T
  message?: string
}

export const useLibrary = () => {
  const { apiFetch } = useApi()

  const getLibraryAlbums = async (): Promise<Album[]> => {
    return await apiFetch<Album[]>('/library/albums')
  }

  const addAlbumToLibrary = async (albumId: number | string): Promise<LibraryMutationResult<Album>> => {
    try {
      const data = await apiFetch<Album>(`/library/albums/${albumId}`, {
        method: 'POST',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not save album' }
    }
  }

  const removeAlbumFromLibrary = async (albumId: number | string): Promise<LibraryMutationResult<Album>> => {
    try {
      const data = await apiFetch<Album>(`/library/albums/${albumId}`, {
        method: 'DELETE',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not remove album' }
    }
  }

  const getLibraryPlaylists = async (): Promise<Playlist[]> => {
    return await apiFetch<Playlist[]>('/library/playlists')
  }

  const addPlaylistToLibrary = async (playlistId: number | string): Promise<LibraryMutationResult<Playlist>> => {
    try {
      const data = await apiFetch<Playlist>(`/library/playlists/${playlistId}`, {
        method: 'POST',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not save playlist' }
    }
  }

  const removePlaylistFromLibrary = async (playlistId: number | string): Promise<LibraryMutationResult<Playlist>> => {
    try {
      const data = await apiFetch<Playlist>(`/library/playlists/${playlistId}`, {
        method: 'DELETE',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not remove playlist' }
    }
  }

  const getLibraryArtists = async (): Promise<Artist[]> => {
    return await apiFetch<Artist[]>('/library/artists')
  }

  const getLibraryUsers = async (): Promise<PublicProfileUser[]> => {
    return await apiFetch<PublicProfileUser[]>('/library/users')
  }

  const followArtist = async (artistId: number | string): Promise<LibraryMutationResult<Artist>> => {
    try {
      const data = await apiFetch<Artist>(`/library/artists/${artistId}`, {
        method: 'POST',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not follow artist' }
    }
  }

  const unfollowArtist = async (artistId: number | string): Promise<LibraryMutationResult<Artist>> => {
    try {
      const data = await apiFetch<Artist>(`/library/artists/${artistId}`, {
        method: 'DELETE',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not unfollow artist' }
    }
  }

  const followLibraryUser = async (uid: string): Promise<LibraryMutationResult<PublicProfileUser>> => {
    try {
      const data = await apiFetch<PublicProfileUser>(`/library/users/${uid}`, {
        method: 'POST',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not follow user' }
    }
  }

  const unfollowLibraryUser = async (uid: string): Promise<LibraryMutationResult<PublicProfileUser>> => {
    try {
      const data = await apiFetch<PublicProfileUser>(`/library/users/${uid}`, {
        method: 'DELETE',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not unfollow user' }
    }
  }

  return {
    getLibraryAlbums,
    addAlbumToLibrary,
    removeAlbumFromLibrary,
    getLibraryPlaylists,
    addPlaylistToLibrary,
    removePlaylistFromLibrary,
    getLibraryArtists,
    getLibraryUsers,
    followArtist,
    unfollowArtist,
    followLibraryUser,
    unfollowLibraryUser,
  }
}
