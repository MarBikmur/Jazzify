import type { PlaylistSong } from '~/composables/usePlaylist'
import { useApi } from '~/composables/useApi'

export const useRecommendations = () => {
  const { apiFetch } = useApi()

  const getRecommendationGenres = async (): Promise<string[]> => {
    return await apiFetch<string[]>('/genres/used')
  }

  const getRecommendations = async (genre: string, limit = 5): Promise<PlaylistSong[]> => {
    const query = new URLSearchParams({
      genre,
      limit: String(limit),
    })

    return await apiFetch<PlaylistSong[]>(`/recommendations?${query.toString()}`)
  }

  return {
    getRecommendationGenres,
    getRecommendations,
  }
}
