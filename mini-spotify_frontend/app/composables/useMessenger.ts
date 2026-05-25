import type {
  MessengerConversation,
  MessengerMessage,
  MessengerSharedItemType,
  MessengerUser,
} from '~/composables/useMessengerApi'

type MessengerView = 'list' | 'conversation'

export interface MessengerShareDraft {
  type: MessengerSharedItemType
  id: number
  title: string
  subtitle?: string | null
  image_url?: string | null
}

const sortConversations = (items: MessengerConversation[]) => {
  return [...items].sort((left, right) => {
    const leftTime = left.last_message_at || left.updated_at || left.created_at || ''
    const rightTime = right.last_message_at || right.updated_at || right.created_at || ''

    return rightTime.localeCompare(leftTime)
  })
}

const isIsoAfterOrEqual = (left?: string | null, right?: string | null) => {
  if (!left || !right) {
    return false
  }

  return left >= right
}

const mergeConversationPreservingReadState = (
  incoming: MessengerConversation,
  current?: MessengerConversation | null,
): MessengerConversation => {
  if (!current?.last_read_at) {
    return incoming
  }

  const incomingLastReadAt = incoming.last_read_at ?? null
  const localReadIsNewer = !incomingLastReadAt || current.last_read_at > incomingLastReadAt
  const latestMessageIsAlreadyRead = isIsoAfterOrEqual(current.last_read_at, incoming.last_message_at)

  if (!localReadIsNewer || !latestMessageIsAlreadyRead) {
    return incoming
  }

  return {
    ...incoming,
    last_read_at: current.last_read_at,
    unread_count: 0,
    has_unread: false,
  }
}

export const useMessenger = () => {
  const {
    getFollowedUsersForMessenger,
    getConversations,
    getOrCreateDirectConversation,
    getConversationMessages,
    sendMessage: sendMessengerMessage,
    markConversationAsRead,
    updateMessage: updateMessengerMessage,
    deleteMessage: deleteMessengerMessage,
  } = useMessengerApi()

  const { activeRightPanel, openRightPanel, closeRightPanel, toggleRightPanel } = useRightPanel()
  const isMessengerOpen = computed(() => activeRightPanel.value === 'messenger')
  const messengerView = useState<MessengerView>('messenger-view', () => 'list')
  const followedUsers = useState<MessengerUser[]>('messenger-followed-users', () => [])
  const conversations = useState<MessengerConversation[]>('messenger-conversations', () => [])
  const selectedConversation = useState<MessengerConversation | null>('messenger-selected-conversation', () => null)
  const messages = useState<MessengerMessage[]>('messenger-messages', () => [])
  const isLoadingFollowedUsers = useState<boolean>('messenger-loading-followed-users', () => false)
  const isLoadingConversations = useState<boolean>('messenger-loading-conversations', () => false)
  const isLoadingMessages = useState<boolean>('messenger-loading-messages', () => false)
  const isSendingMessage = useState<boolean>('messenger-sending-message', () => false)
  const messengerError = useState<string>('messenger-error', () => '')
  const hasBootstrappedMessenger = useState<boolean>('messenger-bootstrapped', () => false)
  const pendingShare = useState<MessengerShareDraft | null>('messenger-pending-share', () => null)
  const sidebarLoadRequestId = useState<number>('messenger-sidebar-load-request-id', () => 0)

  const totalUnreadCount = computed(() =>
    conversations.value.reduce((sum, conversation) => sum + (conversation.unread_count ?? 0), 0)
  )

  const resetMessenger = () => {
    if (activeRightPanel.value === 'messenger') {
      closeRightPanel()
    }
    messengerView.value = 'list'
    followedUsers.value = []
    conversations.value = []
    selectedConversation.value = null
    messages.value = []
    isLoadingFollowedUsers.value = false
    isLoadingConversations.value = false
    isLoadingMessages.value = false
    isSendingMessage.value = false
    messengerError.value = ''
    hasBootstrappedMessenger.value = false
    pendingShare.value = null
  }

  const upsertConversation = (conversation: MessengerConversation) => {
    const next = conversations.value.filter((item) => item.id !== conversation.id)
    next.unshift(conversation)
    conversations.value = sortConversations(next)
  }

  const setConversationReadState = (conversationId: number, lastReadAt?: string | null) => {
    conversations.value = conversations.value.map((conversation) => {
      if (conversation.id !== conversationId) {
        return conversation
      }

      return {
        ...conversation,
        last_read_at: lastReadAt ?? conversation.last_read_at ?? null,
        unread_count: 0,
        has_unread: false,
      }
    })

    if (selectedConversation.value?.id === conversationId) {
      selectedConversation.value = {
        ...selectedConversation.value,
        last_read_at: lastReadAt ?? selectedConversation.value.last_read_at ?? null,
        unread_count: 0,
        has_unread: false,
      }
    }
  }

  const attachConversationIdToUser = (userUid: string, conversationId: number) => {
    followedUsers.value = followedUsers.value.map((user) =>
      user.uid === userUid
        ? {
            ...user,
            conversation_id: conversationId,
          }
        : user
    )
  }

  const loadSidebarData = async (force = false, silent = false) => {
    if (hasBootstrappedMessenger.value && !force) {
      return
    }

    const requestId = sidebarLoadRequestId.value + 1
    sidebarLoadRequestId.value = requestId
    messengerError.value = ''

    if (!silent) {
      isLoadingFollowedUsers.value = true
      isLoadingConversations.value = true
    }

    try {
      const [followed, conversationItems] = await Promise.all([
        getFollowedUsersForMessenger(),
        getConversations(),
      ])

      if (requestId !== sidebarLoadRequestId.value) {
        return
      }

      followedUsers.value = followed

      const currentById = new Map(conversations.value.map((conversation) => [conversation.id, conversation]))
      const mergedConversations = conversationItems.map((conversation) =>
        mergeConversationPreservingReadState(conversation, currentById.get(conversation.id))
      )

      conversations.value = sortConversations(mergedConversations)

      if (selectedConversation.value) {
        const refreshedSelectedConversation = mergedConversations.find(
          (conversation) => conversation.id === selectedConversation.value?.id
        )

        if (refreshedSelectedConversation) {
          selectedConversation.value = mergeConversationPreservingReadState(
            refreshedSelectedConversation,
            selectedConversation.value
          )
        }
      }

      hasBootstrappedMessenger.value = true
    } catch (error: any) {
      console.error('Messenger bootstrap error:', error)
      messengerError.value = error?.data?.message || 'Could not load messenger'
    } finally {
      if (!silent) {
        isLoadingFollowedUsers.value = false
        isLoadingConversations.value = false
      }
    }
  }

  const openMessenger = async () => {
    openRightPanel('messenger')
    messengerView.value = selectedConversation.value ? 'conversation' : 'list'
    await loadSidebarData(true)
  }

  const closeMessenger = () => {
    if (activeRightPanel.value === 'messenger') {
      closeRightPanel()
    }
  }

  const toggleMessenger = async () => {
    if (activeRightPanel.value === 'messenger') {
      closeMessenger()
      return
    }

    toggleRightPanel('messenger')
    messengerView.value = selectedConversation.value ? 'conversation' : 'list'
    await loadSidebarData(true)
  }

  const backToConversationList = () => {
    messengerView.value = 'list'
  }

  const clearPendingShare = () => {
    pendingShare.value = null
  }

  const sendPendingShareIfNeeded = async () => {
    if (!pendingShare.value || !selectedConversation.value?.id) {
      return
    }

    const draft = pendingShare.value
    const result = await sendMessengerMessage(selectedConversation.value.id, {
      share: {
        type: draft.type,
        id: draft.id,
      },
    })

    if (!result.success || !result.data) {
      messengerError.value = result.message || 'Could not share item'
      return
    }

    messages.value = [...messages.value, result.data]

    const nowIso = result.data.created_at || new Date().toISOString()
    const updatedConversation: MessengerConversation = {
      ...selectedConversation.value,
      last_message: result.data,
      last_message_at: nowIso,
      updated_at: nowIso,
      unread_count: 0,
      has_unread: false,
    }

    selectedConversation.value = updatedConversation
    upsertConversation(updatedConversation)
    pendingShare.value = null
  }

  const loadConversationMessages = async (conversationId: number, silent = false) => {
    if (!silent) {
      isLoadingMessages.value = true
    }

    messengerError.value = ''

    try {
      const response = await getConversationMessages(conversationId)
      selectedConversation.value = response.conversation
      messages.value = response.messages
      upsertConversation(response.conversation)

      if (response.conversation.other_user?.uid) {
        attachConversationIdToUser(response.conversation.other_user.uid, response.conversation.id)
      }

      if ((response.conversation.unread_count ?? 0) > 0) {
        const markResult = await markConversationAsRead(conversationId)
        if (markResult.success) {
          setConversationReadState(conversationId, markResult.data?.last_read_at ?? null)
        }
      }
    } catch (error: any) {
      console.error('Conversation messages loading error:', error)
      messengerError.value = error?.data?.message || 'Could not load messages'
    } finally {
      isLoadingMessages.value = false
    }
  }

  const openConversationFromSummary = async (conversation: MessengerConversation) => {
    openRightPanel('messenger')
    messengerView.value = 'conversation'
    selectedConversation.value = conversation
    await loadConversationMessages(conversation.id)
    await sendPendingShareIfNeeded()
  }

  const openConversationWithUser = async (uid: string) => {
    openRightPanel('messenger')
    messengerView.value = 'conversation'
    isLoadingMessages.value = true
    messengerError.value = ''

    try {
      const result = await getOrCreateDirectConversation(uid)
      if (!result.success || !result.data) {
        messengerError.value = result.message || 'Could not open conversation'
        return
      }

      selectedConversation.value = result.data
      upsertConversation(result.data)
      attachConversationIdToUser(uid, result.data.id)
      await loadConversationMessages(result.data.id, true)
      await sendPendingShareIfNeeded()
    } finally {
      isLoadingMessages.value = false
    }
  }

  const prepareShare = async (draft: MessengerShareDraft) => {
    pendingShare.value = draft
    openRightPanel('messenger')
    messengerView.value = 'list'
    await loadSidebarData(true)
  }

  const sendMessage = async (body: string) => {
    if (!selectedConversation.value?.id || isSendingMessage.value) {
      return { success: false, message: 'Conversation is not selected' }
    }

    const trimmedBody = body.trim()
    if (!trimmedBody) {
      return { success: false, message: 'Message cannot be empty' }
    }

    isSendingMessage.value = true
    messengerError.value = ''

    try {
      const result = await sendMessengerMessage(selectedConversation.value.id, {
        body: trimmedBody,
      })
      if (!result.success || !result.data) {
        messengerError.value = result.message || 'Could not send message'
        return { success: false, message: messengerError.value }
      }

      messages.value = [...messages.value, result.data]

      const nowIso = result.data.created_at || new Date().toISOString()
      const updatedConversation: MessengerConversation = {
        ...selectedConversation.value,
        last_message: result.data,
        last_message_at: nowIso,
        updated_at: nowIso,
        unread_count: 0,
        has_unread: false,
      }

      selectedConversation.value = updatedConversation
      upsertConversation(updatedConversation)

      return { success: true }
    } finally {
      isSendingMessage.value = false
    }
  }

  const updateMessage = async (messageId: number, body: string) => {
    const trimmedBody = body.trim()

    if (!trimmedBody) {
      return { success: false, message: 'Message cannot be empty' }
    }

    messengerError.value = ''

    const result = await updateMessengerMessage(messageId, {
      body: trimmedBody,
    })

    if (!result.success || !result.data) {
      messengerError.value = result.message || 'Could not update message'
      return { success: false, message: messengerError.value }
    }

    messages.value = messages.value.map((message) => (message.id === messageId ? result.data! : message))

    if (selectedConversation.value?.last_message?.id === messageId) {
      const updatedConversation: MessengerConversation = {
        ...selectedConversation.value,
        last_message: result.data,
      }

      selectedConversation.value = updatedConversation
      upsertConversation(updatedConversation)
    } else if (selectedConversation.value) {
      upsertConversation(selectedConversation.value)
    }

    return { success: true, message: result.data }
  }

  const deleteMessage = async (messageId: number) => {
    messengerError.value = ''

    const result = await deleteMessengerMessage(messageId)

    if (!result.success) {
      messengerError.value = result.message || 'Could not delete message'
      return { success: false, message: messengerError.value }
    }

    messages.value = messages.value.filter((message) => message.id !== messageId)

    if (selectedConversation.value?.id) {
      await loadConversationMessages(selectedConversation.value.id, true)
    } else {
      await loadSidebarData(true, true)
    }

    return { success: true }
  }

  return {
    isMessengerOpen,
    messengerView,
    followedUsers,
    conversations,
    selectedConversation,
    messages,
    isLoadingFollowedUsers,
    isLoadingConversations,
    isLoadingMessages,
    isSendingMessage,
    messengerError,
    pendingShare,
    totalUnreadCount,
    resetMessenger,
    loadSidebarData,
    openMessenger,
    closeMessenger,
    toggleMessenger,
    backToConversationList,
    openConversationFromSummary,
    openConversationWithUser,
    loadConversationMessages,
    prepareShare,
    clearPendingShare,
    sendMessage,
    updateMessage,
    deleteMessage,
  }
}
