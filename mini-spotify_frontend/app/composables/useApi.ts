interface ApiOptions {
  redirectOn401?: boolean
  method?: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH' | 'HEAD' | 'OPTIONS'
  body?: any
  headers?: Record<string, string>
}

interface ApiResponse<T = any> {
  success: boolean
  data?: T
  message?: string
  error?: any
}

export const useApi = () => {
  const config = useRuntimeConfig()
  const apiBase = config.public.apiBase

  const apiFetch = async <T = any>(
    url: string, 
    options: ApiOptions = {}
  ): Promise<T> => {
    const { redirectOn401 = true, ...restOptions } = options
    const token = useCookie('auth-token')
    
    const fullUrl = url.startsWith('http') ? url : `${apiBase}${url.startsWith('/') ? '' : '/'}${url}`
    
    console.log('API Request:', {
      originalUrl: url,
      apiBase: apiBase,
      fullUrl: fullUrl
    })
    
    const isFormData = restOptions.body instanceof FormData
    
    const defaultOptions = {
      headers: {
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
        'Accept': 'application/json',
        ...(token.value && { 'Authorization': `Bearer ${token.value}` }),
        ...restOptions.headers
      }
    }

    try {
      const response = await $fetch<T>(fullUrl, { ...defaultOptions, ...restOptions })
      return response
    } catch (error: any) {
      if (error.status === 401 && redirectOn401) {
        navigateTo('/login')
      }
      throw error
    }
  }

  return {
    apiFetch
  }
}
