import { useApi } from '~/composables/useApi'
import { ref } from 'vue'

export const FAVORITES_PLAYLIST_TITLE = 'Liked Songs'

const likedSongIds = ref<number[]>([])
const favoritesPlaylistId = ref<number | null>(null)
const isLoadingState = ref(false)

export const useLikedSongs = () => {
  const { apiFetch } = useApi()

  const reset = () => {
    likedSongIds.value = []
    favoritesPlaylistId.value = null
  }

  const refresh = async () => {
    isLoadingState.value = true
    try {
      const res = await apiFetch<{
        playlist: { id: number; title: string } | null
        song_ids: number[]
      }>('/playlists/liked-songs')
      favoritesPlaylistId.value = res.playlist?.id ?? null
      likedSongIds.value = [...(res.song_ids || [])]
    } catch {
      reset()
    } finally {
      isLoadingState.value = false
    }
  }

  const isLiked = (songId: number) => likedSongIds.value.includes(songId)

  const like = async (songId: number): Promise<{ ok: boolean; wasAlreadyLiked: boolean; message?: string }> => {
    try {
      const res = await apiFetch<{
        playlist: { id: number; title: string }
        was_already_liked: boolean
      }>('/playlists/liked-songs/songs', {
        method: 'POST',
        body: { song_id: songId },
      })
      favoritesPlaylistId.value = res.playlist?.id ?? null
      if (!res.was_already_liked && !likedSongIds.value.includes(songId)) {
        likedSongIds.value = [...likedSongIds.value, songId]
      }
      return { ok: true, wasAlreadyLiked: res.was_already_liked }
    } catch (error: any) {
      return { ok: false, wasAlreadyLiked: false, message: error?.data?.message }
    }
  }

  const unlike = async (songId: number): Promise<{ ok: boolean; wasNotLiked: boolean; message?: string }> => {
    try {
      const res = await apiFetch<{
        playlist: { id: number; title: string } | null
        was_not_liked: boolean
      }>(`/playlists/liked-songs/songs/${songId}`, {
        method: 'DELETE',
      })

      favoritesPlaylistId.value = res.playlist?.id ?? favoritesPlaylistId.value
      likedSongIds.value = likedSongIds.value.filter((id) => id !== songId)

      return { ok: true, wasNotLiked: res.was_not_liked }
    } catch (error: any) {
      return { ok: false, wasNotLiked: false, message: error?.data?.message }
    }
  }

  const toggle = async (songId: number): Promise<{ ok: boolean; liked: boolean; message?: string }> => {
    if (isLiked(songId)) {
      const res = await unlike(songId)
      return { ok: res.ok, liked: false, message: res.message }
    }

    const res = await like(songId)
    return { ok: res.ok, liked: true, message: res.message }
  }

  return {
    likedSongIds,
    favoritesPlaylistId,
    isLoadingState,
    reset,
    refresh,
    isLiked,
    like,
    unlike,
    toggle,
  }
}
