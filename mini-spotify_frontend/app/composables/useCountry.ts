import { useApi } from '~/composables/useApi'

interface Country {
  id: number
  name: string
}

interface CreateCountryData {
  name: string
}

interface CountryResponse {
  success: boolean
  data?: any
  message?: string
}

export const useCountry = () => {
  const { apiFetch } = useApi()

  const createCountry = async (countryData: CreateCountryData): Promise<CountryResponse> => {
    try {
      const response = await apiFetch('/countries', {
        method: 'POST',
        body: countryData
      })
      return { success: true, data: response }
    } catch (error: any) {
      const message = error?.data?.message || 'Error while creating a country'
      return { success: false, message }
    }
  }

  const getCountries = async (): Promise<Country[]> => {
    return await apiFetch<Country[]>('/countries')
  }

  return {
    getCountries,
    createCountry
  }
}
