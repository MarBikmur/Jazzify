import { useApi } from '~/composables/useApi'
interface ApiResponse<T = any> {
  success: boolean
  message?: string
  data?: T
}

interface User {
  uid: string
  name: string
  email: string
  role?: string
  avatar_path?: string | null
  avatar_url?: string | null
}

interface AuthResponse {
  success: boolean
  message?: string
  user?: User
  token?: string
}

interface LoginCredentials {
  email: string
  password: string
}

interface RegisterData {
  name: string
  email: string
  password: string
}

interface UpdateUserData {
  name?: string
  email?: string
  password?: string
}

interface UpdateCurrentUserData {
  name?: string
  email?: string
  avatar?: File | null
  remove_avatar?: boolean
}

interface ChangePasswordData {
  current_password: string
  new_password: string
}

export const useAuth = () => {
  const { apiFetch } = useApi()

  const getUsers = async (): Promise<User[]> => {
    return await apiFetch<User[]>('/users')
  }

  const getUser = async (id: number): Promise<User> => {
    return await apiFetch<User>(`/users/${id}`)
  }

  const register = async (userData: RegisterData): Promise<AuthResponse> => {
    try {
      const response: any = await apiFetch('/auth/register', {
        method: 'POST',
        body: userData
      })

      return { success: true, user: response.user, message: 'Registration successful' }
    } catch (error: any) {
      const message = error?.data?.message || 'Error during registration'
      return { success: false, message }
    }
  }

  const updateUser = async (id: number, userData: UpdateUserData): Promise<User> => {
    return await apiFetch<User>(`/users/${id}`, {
      method: 'PUT',
      body: userData
    })
  }

  const updateCurrentProfile = async (userData: UpdateCurrentUserData): Promise<User> => {
    const formData = new FormData()

    if (userData.name !== undefined) {
      formData.append('name', userData.name)
    }

    if (userData.email !== undefined) {
      formData.append('email', userData.email)
    }

    if (userData.avatar) {
      formData.append('avatar', userData.avatar)
    }

    if (userData.remove_avatar) {
      formData.append('remove_avatar', '1')
    }

    return await apiFetch<User>('/auth/profile', {
      method: 'POST',
      body: formData,
    })
  }

  const changePassword = async (payload: ChangePasswordData): Promise<ApiResponse> => {
    try {
      const response: any = await apiFetch('/auth/change-password', {
        method: 'POST',
        body: payload,
      })

      return {
        success: true,
        message: response?.message || 'Password changed successfully',
      }
    } catch (error: any) {
      return {
        success: false,
        message: error?.data?.message || 'Could not change password',
      }
    }
  }

  const deleteUser = async (id: number): Promise<void> => {
    return await apiFetch(`/users/${id}`, {
      method: 'DELETE'
    })
  }

  const login = async (credentials: LoginCredentials): Promise<AuthResponse> => {
    try {
      const response: any = await apiFetch('/auth/login', {
        method: 'POST',
        body: credentials
      })

      if (!response.token) {
        return { success: false, message: 'No token received from server' }
      }

      const authTokenCookie = useCookie<string | null>('auth-token', { default: () => null })
      authTokenCookie.value = response.token

      return { success: true, user: response.user }
    } catch (error: any) {
      const message = error?.data?.message || 'Error while logging in'
      return { success: false, message }
    }
  }

  const logoutCurrent = async (): Promise<void> => {
    const authTokenCookie = useCookie('auth-token')
    console.log('Starting logout, token:', authTokenCookie.value)
    try {
      await apiFetch('/auth/session', {
        method: 'DELETE',
      })
      console.log('Logout API response received')
    } catch (error) {
      console.warn('Logout API call failed:', error)
    } finally {
      console.log('Clearing token and redirecting...')
      authTokenCookie.value = null
      navigateTo('/login')
    }
  }
  const logoutAll = async (): Promise<void> => {
    const authTokenCookie = useCookie('auth-token')
    console.log('Starting logout from all sessions, token:', authTokenCookie.value)
    try {
      await apiFetch('/auth/sessions', {
        method: 'DELETE',
      })
      console.log('Logout from all sessions API response received')
    } catch (error) {
      console.warn('Logout from all sessions API call failed:', error)
    } finally {
      console.log('Clearing token and redirecting...')
      authTokenCookie.value = null
      navigateTo('/login')
    }
  }

  const getCurrentUser = async (): Promise<User | null> => {
    try {
      const user = await apiFetch<User>('/users/me')
      return user
    } catch (error: any) {
      if (error?.status === 401) {
        return null
      }
      throw error
    }
  }

  const isAuthenticated = (): boolean => {
    const authTokenCookie = useCookie<string | null>('auth-token', { default: () => null })
    return !!authTokenCookie.value
  }

  const checkUserRole = async (role: string): Promise<boolean> => {
    try {
      const user = await getCurrentUser()
      return user?.role === role
    } catch (error) {
      return false
    }
  }

  const isAdmin = async (): Promise<boolean> => {
    return await checkUserRole('admin')
  }

  const isArtist = async (): Promise<boolean> => {
    return await checkUserRole('artist')
  }

  const isUser = async (): Promise<boolean> => {
    return await checkUserRole('user')
  }

  const hasRole = (user: User | null, role: string): boolean => {
    return user?.role === role
  }

  return {
    getUsers,
    getUser,
    register,
    updateUser,
    updateCurrentProfile,
    changePassword,
    deleteUser,
    login,
    logoutCurrent,
    logoutAll,
    getCurrentUser,
    isAuthenticated,
    checkUserRole,
    isAdmin,
    isArtist,
    isUser,
    hasRole
  }
}
