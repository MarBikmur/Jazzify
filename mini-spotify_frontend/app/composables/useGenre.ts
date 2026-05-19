import { useApi } from '~/composables/useApi'

export interface Genre {
  id: number
  name: string
}

interface CreatingGenreData {
  name: string
}

interface GenreResponse {
  success: boolean
  data?: any
  message?: string
}
export const useGenre = () => {
  const { apiFetch } = useApi()

  const createGenre = async (genreData: CreatingGenreData): Promise<GenreResponse> => {
    try {
      const response = await apiFetch('/genres', {
        method: 'POST',
        body: genreData
      })
      return { success: true, data: response }
    } catch (error: any) {
      const message = error?.data?.message || 'Error while creating genre'
      return { success: false, message }
    }
  }

  const getGenres = async (): Promise<Genre[]> => {
    return await apiFetch<Genre[]>('/genres')
  }

  return {
    createGenre,
    getGenres
  }
}
