import { ref, computed } from 'vue'
import { useAuth } from './useAuth'

export const useRole = () => {
  const { getCurrentUser } = useAuth()
  const currentUser = ref<any>(null)
  const isLoading = ref(false)

  const loadCurrentUser = async () => {
    isLoading.value = true
    try {
      currentUser.value = await getCurrentUser()
    } catch (error) {
      console.error('Error loading user:', error)
      currentUser.value = null
    } finally {
      isLoading.value = false
    }
  }

  const isAdmin = computed(() => currentUser.value?.role === 'admin')
  const isArtist = computed(() => currentUser.value?.role === 'artist')
  const isUser = computed(() => currentUser.value?.role === 'user')
  const isGuest = computed(() => !currentUser.value)

  const hasRole = (role: string) => {
    return currentUser.value?.role === role
  }

  const hasAnyRole = (roles: string[]) => {
    return roles.includes(currentUser.value?.role || '')
  }

  const hasAllRoles = (roles: string[]) => {
    return roles.length === 1 && roles.includes(currentUser.value?.role || '')
  }

  const can = (permission: string) => {
    const permissions: Record<string, string[]> = {
      'create-artist': ['admin', 'user'],
      'manage-users': ['admin'],
      'upload-music': ['artist', 'admin'],
      'delete-content': ['admin'],
      'view-admin-panel': ['admin'],
      'create-playlist': ['user', 'artist', 'admin'],
      'edit-profile': ['user', 'artist', 'admin']
    }

    const allowedRoles = permissions[permission] || []
    return hasAnyRole(allowedRoles)
  }

  return {
    currentUser,
    isLoading,
    loadCurrentUser,
    isAdmin,
    isArtist,
    isUser,
    isGuest,
    hasRole,
    hasAnyRole,
    hasAllRoles,
    can
  }
}

