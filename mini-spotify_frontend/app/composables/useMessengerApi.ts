import { useApi } from '~/composables/useApi'
import type { PublicProfileUser } from '~/composables/useUserProfile'

export interface MessengerUser extends PublicProfileUser {
  conversation_id?: number | null
}

export type MessengerSharedItemType = 'track' | 'album' | 'playlist' | 'artist'

export interface MessengerSharedItem {
  type: MessengerSharedItemType
  id: number
  title: string
  subtitle?: string | null
  image_url?: string | null
  route?: string | null
  duration?: number | null
  artist_id?: number | null
  artist_name?: string | null
  album_id?: number | null
  album_title?: string | null
  owner_uid?: string | null
  owner_name?: string | null
}

export interface MessengerMessage {
  id: number
  conversation_id: number
  sender_uid: string
  body: string
  message_type?: 'text' | 'share'
  shared_item?: MessengerSharedItem | null
  created_at?: string | null
  updated_at?: string | null
  edited_at?: string | null
  sender?: MessengerUser | null
}

export interface MessengerConversation {
  id: number
  type: string
  created_at?: string | null
  updated_at?: string | null
  last_message_at?: string | null
  last_read_at?: string | null
  unread_count?: number
  has_unread?: boolean
  other_user?: MessengerUser | null
  last_message?: MessengerMessage | null
}

interface MessengerMessagesResponse {
  conversation: MessengerConversation
  messages: MessengerMessage[]
}

interface MessengerMutationResult<T> {
  success: boolean
  data?: T
  message?: string
}

export const useMessengerApi = () => {
  const { apiFetch } = useApi()

  const getFollowedUsersForMessenger = async (): Promise<MessengerUser[]> => {
    return await apiFetch<MessengerUser[]>('/messenger/followed-users')
  }

  const getConversations = async (): Promise<MessengerConversation[]> => {
    return await apiFetch<MessengerConversation[]>('/messenger/conversations')
  }

  const getOrCreateDirectConversation = async (uid: string): Promise<MessengerMutationResult<MessengerConversation>> => {
    try {
      const data = await apiFetch<MessengerConversation>(`/messenger/conversations/direct/${uid}`, {
        method: 'POST',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not open conversation' }
    }
  }

  const getConversationMessages = async (
    conversationId: number | string,
    limit = 50,
  ): Promise<MessengerMessagesResponse> => {
    return await apiFetch<MessengerMessagesResponse>(`/messenger/conversations/${conversationId}/messages?limit=${limit}`)
  }

  const sendMessage = async (
    conversationId: number | string,
    payload: {
      body?: string
      share?: {
        type: MessengerSharedItemType
        id: number
      } | null
    },
  ): Promise<MessengerMutationResult<MessengerMessage>> => {
    try {
      const data = await apiFetch<MessengerMessage>(`/messenger/conversations/${conversationId}/messages`, {
        method: 'POST',
        body: payload,
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not send message' }
    }
  }

  const markConversationAsRead = async (
    conversationId: number | string,
  ): Promise<MessengerMutationResult<{ conversation_id: number; last_read_at?: string | null }>> => {
    try {
      const data = await apiFetch<{ conversation_id: number; last_read_at?: string | null }>(
        `/messenger/conversations/${conversationId}/read`,
        {
          method: 'PATCH',
        },
      )

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not mark conversation as read' }
    }
  }

  const updateMessage = async (
    messageId: number | string,
    payload: {
      body: string
    },
  ): Promise<MessengerMutationResult<MessengerMessage>> => {
    try {
      const data = await apiFetch<MessengerMessage>(`/messenger/messages/${messageId}`, {
        method: 'PATCH',
        body: payload,
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not update message' }
    }
  }

  const deleteMessage = async (
    messageId: number | string,
  ): Promise<MessengerMutationResult<{ message_id: number; conversation_id: number }>> => {
    try {
      const data = await apiFetch<{ message_id: number; conversation_id: number }>(`/messenger/messages/${messageId}`, {
        method: 'DELETE',
      })

      return { success: true, data }
    } catch (error: any) {
      return { success: false, message: error?.data?.message || 'Could not delete message' }
    }
  }

  return {
    getFollowedUsersForMessenger,
    getConversations,
    getOrCreateDirectConversation,
    getConversationMessages,
    sendMessage,
    markConversationAsRead,
    updateMessage,
    deleteMessage,
  }
}
