import { useApi } from '~/composables/useApi'
import type { PublicProfileUser } from '~/composables/useUserProfile'

interface UserSocialResult {
  success: boolean
  data?: PublicProfileUser
  message?: string
}

export const useUserSocial = () => {
  const { apiFetch } = useApi()

  const getSearchableUsers = async (): Promise<PublicProfileUser[]> => {
    return await apiFetch<PublicProfileUser[]>('/social/users')
  }

  const followUser = async (uid: string): Promise<UserSocialResult> => {
    try {
      const data = await apiFetch<PublicProfileUser>(`/social/users/${uid}`, {
        method: 'POST',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not follow user' }
    }
  }

  const unfollowUser = async (uid: string): Promise<UserSocialResult> => {
    try {
      const data = await apiFetch<PublicProfileUser>(`/social/users/${uid}`, {
        method: 'DELETE',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not unfollow user' }
    }
  }

  return {
    getSearchableUsers,
    followUser,
    unfollowUser,
  }
}
