import { useApi } from '~/composables/useApi'
import type { Album } from '~/composables/useAlbum'
import type { Artist } from '~/composables/useArtist'
import type { Playlist } from '~/composables/usePlaylist'

export interface PublicProfileUser {
  uid: string
  name: string
  role?: string
  avatar_path?: string | null
  avatar_url?: string | null
  followers_count?: number
  is_following?: boolean
  artist?: Artist | null
}

export interface PublicProfileResponse {
  user: PublicProfileUser
  playlists: Playlist[]
  followed_users: PublicProfileUser[]
  artists: Artist[]
  albums: Album[]
  liked_playlists: Playlist[]
}

export const useUserProfile = () => {
  const { apiFetch } = useApi()

  const getPublicProfile = async (uid: string): Promise<PublicProfileResponse> => {
    return await apiFetch<PublicProfileResponse>(`/profiles/${uid}`)
  }

  return {
    getPublicProfile,
  }
}
