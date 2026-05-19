import { useApi } from '~/composables/useApi'

export interface Artist {
  id: number
  name: string
  user_uid?: string | null
  country_id?: number
  image_path?: string
  image_url?: string
  followers_count?: number
  is_following?: boolean
  is_in_library?: boolean
}

interface CreateArtistData {
  name: string
  country_id?: string | number
  image?: File | null
}

interface UpdateArtistData {
  name: string
  country_id?: string | number
  image?: File | null
  remove_image?: boolean
}

interface ArtistResponse {
  success: boolean
  data?: any
  message?: string
}

export const useArtist = () => {
  const { apiFetch } = useApi()

  const createArtist = async (artistData: CreateArtistData): Promise<ArtistResponse> => {
    try {
      const formData = new FormData()
      formData.append('name', artistData.name)
      if (artistData.country_id) {
        formData.append('country_id', String(artistData.country_id))
      }
      if (artistData.image) {
        formData.append('image_path', artistData.image)
      }

      const data = await apiFetch('/artists', {
        method: 'POST',
        body: formData
      })

      return { success: true, data: data, message: 'Artist created successfully' }
    } catch (error: any) {
      const message = error?.data?.message || 'Error while creating artist'
      return { success: false, message }
    }
  }
  
  const getArtists = async (): Promise<Artist[]> => {
    return await apiFetch<Artist[]>('/artists')
  }

  const getArtist = async (id: number | string): Promise<Artist> => {
    return await apiFetch<Artist>(`/artists/${id}`)
  }

  const getCurrentArtist = async (): Promise<Artist | null> => {
    try {
      return await apiFetch<Artist>('/artists/me', { redirectOn401: false })
    } catch (error: any) {
      if (error?.status === 404 || error?.statusCode === 404) {
        return null
      }

      throw error
    }
  }

  const updateCurrentArtist = async (artistData: UpdateArtistData): Promise<ArtistResponse> => {
    try {
      const formData = new FormData()
      formData.append('name', artistData.name)

      if (artistData.country_id) {
        formData.append('country_id', String(artistData.country_id))
      }

      if (artistData.image) {
        formData.append('image_path', artistData.image)
      }

      if (artistData.remove_image) {
        formData.append('remove_image', '1')
      }

      const data = await apiFetch('/artists/me', {
        method: 'POST',
        body: formData,
      })

      return { success: true, data, message: 'Artist updated successfully' }
    } catch (error: any) {
      const message = error?.data?.message || 'Error while updating artist'
      return { success: false, message }
    }
  }

  return {
    createArtist,
    getArtists,
    getArtist,
    getCurrentArtist,
    updateCurrentArtist,
  }
}
