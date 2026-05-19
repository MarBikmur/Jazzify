<template>
  <aside class="messenger-sidebar">
    <header class="messenger-sidebar__header">
      <div class="messenger-sidebar__title">
        <button
          v-if="messengerView === 'conversation'"
          class="messenger-sidebar__back"
          type="button"
          @click="backToConversationList"
        >
          <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="ui-icon ui-icon--md" />
        </button>
        <div>
          <strong>{{ tl('Messenger') }}</strong>
          <span>{{ messengerSubtitle }}</span>
        </div>
      </div>

      <button class="messenger-sidebar__close" type="button" :aria-label="tl('Close messenger')" @click="closeMessenger">
        <Icon :icon="getIcon('material-symbols:close-rounded')" class="ui-icon ui-icon--md" />
      </button>
    </header>

    <div v-if="messengerView === 'list'" class="messenger-sidebar__body messenger-sidebar__body--list">
      <div v-if="isLoadingFollowedUsers || isLoadingConversations" class="messenger-state">
        <span class="loader" />
        <span>{{ tl('Loading messenger...') }}</span>
      </div>
      <div v-else-if="messengerError" class="messenger-state messenger-state--error">
        {{ messengerError }}
      </div>
      <template v-else>
        <div v-if="pendingShare" class="messenger-share-prompt">
          <div class="messenger-share-prompt__copy">
            <strong>{{ tl('Share with someone') }}</strong>
            <span>{{ tl('Choose a conversation or a user to send this card.') }}</span>
          </div>
          <button class="messenger-share-prompt__dismiss" type="button" @click="clearPendingShare">
            {{ tl('Cancel') }}
          </button>
          <button type="button" class="messenger-shared-card messenger-shared-card--draft" @click="clearPendingShare">
            <ArtworkCover
              class="messenger-shared-card__image"
              :src="pendingShare.image_url || ''"
              :alt="pendingShare.title"
              :fallback="pendingShare.title.slice(0, 1).toUpperCase()"
              :fallback-icon="shareIcon(pendingShare.type)"
              :fallback-variant="pendingShare.type === 'playlist' ? 'playlist' : 'default'"
            />
            <span class="messenger-shared-card__copy">
              <small>{{ shareTypeLabel(pendingShare.type) }}</small>
              <strong>{{ pendingShare.title }}</strong>
              <span>{{ pendingShare.subtitle || tl('Ready to share') }}</span>
            </span>
          </button>
        </div>

        <section v-if="conversations.length" class="messenger-section">
          <div class="messenger-section__header">
            <div class="messenger-section__heading">
              <strong>{{ tl('Recent conversations') }}</strong>
              <span>{{ conversations.length }}</span>
            </div>
          </div>
          <button
            v-for="conversation in conversations"
            :key="`conversation-${conversation.id}`"
            class="messenger-list-item"
            type="button"
            @click="openConversationFromSummary(conversation)"
          >
            <ArtworkCover
              class="messenger-list-item__avatar"
              :src="conversation.other_user?.avatar_url || mediaUrl(conversation.other_user?.avatar_path)"
              :alt="conversation.other_user?.name || tl('User')"
              :fallback="conversation.other_user?.name?.slice(0, 1).toUpperCase() || 'U'"
              shape="circle"
            />
            <span class="messenger-list-item__copy">
              <strong>{{ conversation.other_user?.name || tl('User') }}</strong>
              <span>{{ conversationPreview(conversation) }}</span>
            </span>
            <span class="messenger-list-item__meta">
              <small>{{ formatTimestamp(conversation.last_message_at || conversation.updated_at) }}</small>
              <span v-if="conversation.unread_count" class="messenger-list-item__badge">{{ conversation.unread_count }}</span>
            </span>
          </button>
        </section>

        <section class="messenger-section">
          <div class="messenger-section__header">
            <div class="messenger-section__heading">
              <strong>{{ tl('Following') }}</strong>
              <span>{{ followedUsers.length }}</span>
            </div>
          </div>

          <div v-if="followedUsers.length" class="messenger-list">
            <button
              v-for="followedUser in followedUsers"
              :key="`followed-user-${followedUser.uid}`"
              class="messenger-list-item"
              type="button"
              @click="openConversationWithUser(followedUser.uid)"
            >
              <ArtworkCover
                class="messenger-list-item__avatar"
                :src="followedUser.avatar_url || mediaUrl(followedUser.avatar_path)"
                :alt="followedUser.name"
                :fallback="followedUser.name.slice(0, 1).toUpperCase()"
                shape="circle"
              />
              <span class="messenger-list-item__copy">
                <strong>{{ followedUser.name }}</strong>
                <span>{{ userSubtitle(followedUser) }}</span>
              </span>
              <span class="messenger-list-item__meta">
                <small>{{ followedUser.conversation_id ? tl('Open chat') : tl('New chat') }}</small>
              </span>
            </button>
          </div>

          <div v-else class="messenger-empty">
            <strong>{{ tl('You are not following anyone yet') }}</strong>
            <p>{{ tl('You can still open any user page and start a conversation from there.') }}</p>
          </div>
        </section>
      </template>
    </div>

    <div v-else class="messenger-sidebar__body messenger-sidebar__body--conversation">
      <div v-if="isLoadingMessages" class="messenger-state">
        <span class="loader" />
        <span>{{ tl('Loading conversation...') }}</span>
      </div>
      <div v-else-if="messengerError" class="messenger-state messenger-state--error">
        {{ messengerError }}
      </div>
      <template v-else-if="selectedConversation">
        <div class="messenger-conversation__header">
          <button
            class="messenger-conversation__avatar-button"
            type="button"
            :aria-label="`${tl('Open')} ${selectedConversation.other_user?.name || tl('user')} ${tl('profile')}`"
            @click="openConversationUserProfile"
          >
            <ArtworkCover
              class="messenger-conversation__avatar"
              :src="selectedConversation.other_user?.avatar_url || mediaUrl(selectedConversation.other_user?.avatar_path)"
              :alt="selectedConversation.other_user?.name || tl('User')"
              :fallback="selectedConversation.other_user?.name?.slice(0, 1).toUpperCase() || 'U'"
              shape="circle"
            />
          </button>
          <div class="messenger-conversation__copy">
            <button class="messenger-conversation__name" type="button" @click="openConversationUserProfile">
              {{ selectedConversation.other_user?.name || tl('User') }}
            </button>
          </div>
        </div>

        <div ref="messagesContainerRef" class="messenger-messages">
          <div v-if="!messages.length" class="messenger-empty messenger-empty--conversation">
            <strong>{{ tl('No messages yet') }}</strong>
            <p>{{ tl('Say hello and start the conversation.') }}</p>
          </div>
          <article
            v-for="message in messages"
            :key="`message-${message.id}`"
            class="messenger-message"
            :class="{ 'messenger-message--own': message.sender_uid === currentUserUid }"
            @contextmenu="openMessageContextMenu($event, message)"
          >
            <div class="messenger-message__bubble">
              <p v-if="message.body">{{ message.body }}</p>
              <button
                v-if="message.message_type === 'share' && message.shared_item"
                type="button"
                class="messenger-shared-card"
                @click="openSharedItem(message.shared_item)"
              >
                <span class="messenger-shared-card__image-shell">
                  <ArtworkCover
                    class="messenger-shared-card__image"
                    :src="message.shared_item.image_url || ''"
                    :alt="message.shared_item.title"
                    :fallback="message.shared_item.title.slice(0, 1).toUpperCase()"
                    :fallback-icon="shareIcon(message.shared_item.type)"
                    :fallback-variant="message.shared_item.type === 'playlist' ? 'playlist' : 'default'"
                  />
                  <button
                    v-if="hasSharedPlayAction(message.shared_item)"
                    type="button"
                    class="messenger-shared-card__play"
                    :aria-label="`${tl('Play')} ${message.shared_item.title}`"
                    @click.stop="playSharedItem(message.shared_item)"
                  >
                    <Icon :icon="getIcon('material-symbols:play-arrow-rounded')" class="ui-icon ui-icon--md" />
                  </button>
                </span>
                <span class="messenger-shared-card__copy">
                  <small>{{ shareTypeLabel(message.shared_item.type) }}</small>
                  <strong>{{ message.shared_item.title }}</strong>
                  <span>{{ formatSharedSubtitle(message.shared_item) || sharedItemActionLabel(message.shared_item.type) }}</span>
                </span>
              </button>
              <span class="messenger-message__time">
                {{ formatTimestamp(message.created_at, true) }}
                <template v-if="message.edited_at && message.message_type === 'text'"> · {{ tl('edited') }}</template>
              </span>
            </div>
          </article>
        </div>

        <form class="messenger-composer" @submit.prevent="submitMessage">
          <label class="messenger-composer__field">
            <textarea
              ref="composerTextareaRef"
              v-model="draftMessage"
              rows="1"
              maxlength="300"
              :placeholder="tl('Write a message...')"
              :disabled="isSendingMessage"
              @input="syncComposerHeight"
              @keydown="handleComposerKeydown"
            />
          </label>
          <div class="messenger-composer__actions">
            <button
              v-if="editingMessageId"
              class="messenger-composer__cancel"
              type="button"
              @click="cancelMessageEdit"
            >
              {{ tl('Cancel') }}
            </button>
            <button
              class="messenger-composer__send"
              type="submit"
              :disabled="isSendingMessage || !draftMessage.trim()"
            >
              {{ isSendingMessage ? (editingMessageId ? tl('Saving...') : tl('Sending...')) : (editingMessageId ? tl('Edit') : tl('Send')) }}
            </button>
          </div>
        </form>
      </template>
    </div>

    <TrackContextMenu
      :visible="messageMenu.visible"
      :x="messageMenu.x"
      :y="messageMenu.y"
      :items="messageMenuItems"
      @close="closeMessageMenu"
    />
  </aside>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import type { MessengerConversation, MessengerSharedItem, MessengerSharedItemType } from '~/composables/useMessengerApi'
import type { TrackContextMenuItem } from '~/components/TrackContextMenu.vue'
const { tl } = useLocalizedText()

const { getIcon } = useAppIcons()
const { mediaUrl } = useMediaUrl()
const config = useRuntimeConfig()
const authToken = useCookie<string | null>('auth-token', { default: () => null })
const { getCurrentUser } = useAuth()
const { getTrackStreamUrl } = useAlbum()
const router = useRouter()
const {
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
  closeMessenger,
  backToConversationList,
  loadSidebarData,
  openConversationFromSummary,
  openConversationWithUser,
  loadConversationMessages,
  clearPendingShare,
  sendMessage,
  updateMessage,
  deleteMessage,
} = useMessenger()
const { playTrack: playInLayout } = useAudioPlayer()

const currentUserUid = ref('')
const draftMessage = ref('')
const editingMessageId = ref<number | null>(null)
const messagesContainerRef = ref<HTMLElement | null>(null)
const composerTextareaRef = ref<HTMLTextAreaElement | null>(null)
const realtimeCursor = ref<string | null>(null)
const messageMenu = reactive({
  visible: false,
  x: 0,
  y: 0,
  messageId: null as number | null,
})
let streamAbortController: AbortController | null = null
let streamReconnectTimer: ReturnType<typeof setTimeout> | null = null
let isRealtimeClosing = false

const messengerSubtitle = computed(() => {
  if (messengerView.value === 'conversation') {
    return tl('Direct messages')
  }

  return tl('Chats and following')
})

const userSubtitle = (user?: { followers_count?: number | null } | null) => {
  const count = user?.followers_count ?? 0
  return count === 1 ? `1 ${tl('follower')}` : `${count} ${tl('followers')}`
}

const shareTypeLabel = (type?: MessengerSharedItemType | null) => {
  switch (type) {
    case 'track':
      return tl('Track')
    case 'album':
      return tl('Album')
    case 'playlist':
      return tl('Playlist')
    case 'artist':
      return tl('Artist')
    default:
      return tl('Share')
  }
}

const sharedItemActionLabel = (type?: MessengerSharedItemType | null) => {
  return type === 'track' ? tl('Tap to play') : tl('Open page')
}

const hasSharedPlayAction = (item?: MessengerSharedItem | null) => {
  return item?.type === 'track' || item?.type === 'album' || item?.type === 'playlist'
}

const formatSharedSubtitle = (item?: MessengerSharedItem | null) => {
  if (!item) {
    return ''
  }

  if (item.subtitle) {
    return item.subtitle
  }

  switch (item.type) {
    case 'playlist':
      return item.owner_name || tl('Playlist')
    case 'track':
      return [item.artist_name, item.album_title].filter(Boolean).join(' • ')
    case 'album':
      return item.artist_name || tl('Album')
    case 'artist':
      return tl('Artist')
    default:
      return ''
  }
}

const shareIcon = (type?: MessengerSharedItemType | null) => {
  switch (type) {
    case 'track':
      return 'solar:music-notes-bold'
    case 'album':
      return 'solar:album-bold'
    case 'playlist':
      return 'solar:playlist-minimalistic-bold'
    case 'artist':
      return 'material-symbols:person-rounded'
    default:
      return 'material-symbols:share-rounded'
  }
}

const conversationPreview = (conversation: MessengerConversation) => {
  const lastMessage = conversation.last_message

  if (!lastMessage) {
    return tl('No messages yet')
  }

  if (lastMessage.message_type === 'share' && lastMessage.shared_item) {
    return `${tl('Shared a')} ${shareTypeLabel(lastMessage.shared_item.type).toLowerCase()}`
  }

  return lastMessage.body || tl('No messages yet')
}

const formatTimestamp = (value?: string | null, compact = false) => {
  if (!value) {
    return compact ? '' : tl('Now')
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }

  return compact
    ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : date.toLocaleDateString([], { month: 'short', day: 'numeric' })
}

const scrollToBottom = async () => {
  await nextTick()

  if (!messagesContainerRef.value) {
    return
  }

  messagesContainerRef.value.scrollTop = messagesContainerRef.value.scrollHeight
}

const syncComposerHeight = async () => {
  await nextTick()

  if (!composerTextareaRef.value) {
    return
  }

  composerTextareaRef.value.style.height = '0px'
  const nextHeight = Math.max(54, composerTextareaRef.value.scrollHeight)
  composerTextareaRef.value.style.height = `${nextHeight}px`
}

const submitMessage = async () => {
  const body = draftMessage.value.trim()
  if (!body) {
    return
  }

  const result = editingMessageId.value
    ? await updateMessage(editingMessageId.value, body)
    : await sendMessage(body)
  if (!result.success) {
    return
  }

  draftMessage.value = ''
  editingMessageId.value = null
  await syncComposerHeight()
  await scrollToBottom()
}

const handleComposerKeydown = async (event: KeyboardEvent) => {
  if (event.key !== 'Enter' || event.shiftKey) {
    return
  }

  event.preventDefault()
  await submitMessage()
}

const closeMessageMenu = () => {
  messageMenu.visible = false
  messageMenu.messageId = null
}

const cancelMessageEdit = () => {
  editingMessageId.value = null
  draftMessage.value = ''
}

const openMessageContextMenu = (event: MouseEvent, message: MessengerMessage) => {
  if (message.sender_uid !== currentUserUid.value || editingMessageId.value === message.id) {
    return
  }

  event.preventDefault()
  messageMenu.visible = true
  messageMenu.x = event.clientX
  messageMenu.y = event.clientY
  messageMenu.messageId = message.id
}

const saveMessageEdit = async (messageId: number) => {
  const result = await updateMessage(messageId, editingMessageText.value)

  if (result.success) {
    cancelMessageEdit()
  }
}

const messageMenuItems = computed<TrackContextMenuItem[]>(() => {
  const targetMessage = messageMenu.messageId
    ? messages.value.find((message) => message.id === messageMenu.messageId)
    : null

  if (!targetMessage) {
    return []
  }

  const items: TrackContextMenuItem[] = []

  if (targetMessage.message_type === 'text') {
    const textMessageId = targetMessage.id
    const textMessageBody = targetMessage.body || ''

    items.push({
      key: 'edit',
      label: tl('Edit message'),
      icon: 'solar:pen-2-linear',
      action: async () => {
        editingMessageId.value = textMessageId
        draftMessage.value = textMessageBody
        await nextTick()
        composerTextareaRef.value?.focus()
        await syncComposerHeight()
      },
    })
  }

  const deleteTargetId = targetMessage.id

  items.push({
    key: 'delete',
    label: tl('Delete message'),
    icon: 'solar:trash-bin-trash-linear',
    danger: true,
    action: async () => {
      const result = await deleteMessage(deleteTargetId)

      if (result.success && editingMessageId.value === deleteTargetId) {
        cancelMessageEdit()
      }
    },
  })

  return items
})

const openSharedItem = async (item: MessengerSharedItem) => {
  if (item.type === 'track') {
    if (!item.id) {
      return
    }

    await playInLayout({
      id: item.id,
      title: item.title,
      duration: item.duration ?? null,
      artistName: item.artist_name || item.subtitle || tl('Artist'),
      artistId: item.artist_id || undefined,
      albumTitle: item.album_title || undefined,
      albumId: item.album_id || undefined,
      coverUrl: item.image_url || '',
      collectionType: item.album_id ? 'album' : undefined,
      collectionId: item.album_id || undefined,
      resolveStreamUrl: () => getTrackStreamUrl(item.id),
    })
    return
  }

  if (item.route) {
    await router.push(item.route)
  }
}

const playSharedItem = async (item: MessengerSharedItem) => {
  if (item.type === 'track') {
    await openSharedItem(item)
    return
  }

  if (item.type === 'album' && item.artist_id && item.id) {
    await router.push(`/albums/${item.artist_id}/${item.id}?autoplay=1`)
    return
  }

  if (item.type === 'playlist' && item.id) {
    await router.push(`/playlists/${item.id}?autoplay=1`)
  }
}

const openConversationUserProfile = async () => {
  const userUid = selectedConversation.value?.other_user?.uid

  if (!userUid) {
    return
  }

  await router.push(`/users/${userUid}`)
}

const syncRealtimeCursorFromState = () => {
  const selectedConversationTime =
    selectedConversation.value?.last_message_at ||
    selectedConversation.value?.updated_at ||
    null

  const latestConversationTime = conversations.value.reduce<string | null>((latest, conversation) => {
    const candidate = conversation.last_message_at || conversation.updated_at || conversation.created_at || null

    if (!candidate) {
      return latest
    }

    if (!latest || candidate > latest) {
      return candidate
    }

    return latest
  }, selectedConversationTime)

  realtimeCursor.value = latestConversationTime
}

const stopRealtime = () => {
  isRealtimeClosing = true

  if (streamReconnectTimer) {
    clearTimeout(streamReconnectTimer)
    streamReconnectTimer = null
  }

  if (streamAbortController) {
    streamAbortController.abort()
    streamAbortController = null
  }
}

const scheduleRealtimeReconnect = () => {
  if (isRealtimeClosing || streamReconnectTimer) {
    return
  }

  streamReconnectTimer = setTimeout(() => {
    streamReconnectTimer = null
    void startRealtime()
  }, 1200)
}

const applyRealtimeEvent = async (payload: { cursor?: string | null; conversation_ids?: number[] | null }) => {
  if (payload.cursor) {
    realtimeCursor.value = payload.cursor
  }

  await loadSidebarData(true, true)

  if (
    selectedConversation.value?.id &&
    payload.conversation_ids?.includes(selectedConversation.value.id)
  ) {
    await loadConversationMessages(selectedConversation.value.id, true)
  }
}

const handleRealtimeChunk = async (
  block: string,
  eventTypeRef: { value: string },
) => {
  const lines = block.split('\n').map((line) => line.trimEnd())
  let payloadText = ''

  for (const line of lines) {
    if (!line) {
      continue
    }

    if (line.startsWith('event:')) {
      eventTypeRef.value = line.slice(6).trim() || 'message'
      continue
    }

    if (line.startsWith('data:')) {
      payloadText += `${line.slice(5).trim()}`
    }
  }

  if (!payloadText) {
    return
  }

  const payload = JSON.parse(payloadText) as { cursor?: string | null; conversation_ids?: number[] | null }
  if (payload.cursor) {
    realtimeCursor.value = payload.cursor
  }

  if (eventTypeRef.value === 'message.created') {
    await applyRealtimeEvent(payload)
  }
}

const startRealtime = async () => {
  if (process.server || streamAbortController || !authToken.value) {
    return
  }

  isRealtimeClosing = false
  syncRealtimeCursorFromState()

  const baseUrl = config.public.apiBase as string
  const streamUrl = new URL(`${baseUrl.replace(/\/$/, '')}/messenger/stream`)
  if (realtimeCursor.value) {
    streamUrl.searchParams.set('cursor', realtimeCursor.value)
  }

  const controller = new AbortController()
  streamAbortController = controller

  try {
    const response = await fetch(streamUrl.toString(), {
      method: 'GET',
      headers: {
        Accept: 'text/event-stream',
        Authorization: `Bearer ${authToken.value}`,
      },
      signal: controller.signal,
      cache: 'no-store',
    })

    if (!response.ok || !response.body) {
      throw new Error(`Messenger stream failed with status ${response.status}`)
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''
    const eventTypeRef = { value: 'message' }

    while (true) {
      const { done, value } = await reader.read()
      if (done) {
        break
      }

      buffer += decoder.decode(value, { stream: true }).replace(/\r\n/g, '\n')

      let separatorIndex = buffer.indexOf('\n\n')
      while (separatorIndex !== -1) {
        const block = buffer.slice(0, separatorIndex)
        buffer = buffer.slice(separatorIndex + 2)
        await handleRealtimeChunk(block, eventTypeRef)
        eventTypeRef.value = 'message'
        separatorIndex = buffer.indexOf('\n\n')
      }
    }
  } catch (error: any) {
    if (error?.name !== 'AbortError') {
      console.warn('Messenger realtime stream disconnected:', error)
      scheduleRealtimeReconnect()
    }
  } finally {
    if (streamAbortController === controller) {
      streamAbortController = null
    }

    if (!isRealtimeClosing) {
      scheduleRealtimeReconnect()
    }
  }
}

onMounted(async () => {
  const user = await getCurrentUser()
  currentUserUid.value = user?.uid || ''
  await loadSidebarData()
  await syncComposerHeight()
  syncRealtimeCursorFromState()
  void startRealtime()
})

watch(
  () => selectedConversation.value?.id,
  async () => {
    closeMessageMenu()
    cancelMessageEdit()
    syncRealtimeCursorFromState()
    await scrollToBottom()
  }
)

watch(
  () => messages.value.length,
  async () => {
    syncRealtimeCursorFromState()
    await scrollToBottom()
  }
)

watch(draftMessage, async () => {
  await syncComposerHeight()
})

onBeforeUnmount(() => {
  stopRealtime()
})
</script>

<style scoped>
.messenger-sidebar {
  height: 100%;
  min-height: 0;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 14px;
  padding: 18px;
  border: 1px solid var(--color-shell-border);
  border-radius: 18px;
  background: var(--color-sidebar-surface);
  box-shadow: var(--shadow-card);
}

.ui-icon {
  display: block;
}

.ui-icon--md {
  font-size: 1.2rem;
}

.messenger-sidebar__header,
.messenger-sidebar__title,
.messenger-conversation__header {
  display: flex;
  align-items: center;
}

.messenger-conversation__avatar-button,
.messenger-conversation__name {
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.messenger-sidebar__header {
  justify-content: space-between;
  gap: 12px;
}

.messenger-sidebar__title {
  min-width: 0;
  gap: 10px;
}

.messenger-sidebar__title > div {
  min-width: 0;
}

.messenger-sidebar__title strong,
.messenger-sidebar__title span,
.messenger-conversation__copy strong,
.messenger-conversation__copy span {
  display: block;
}

.messenger-sidebar__title strong,
.messenger-section__header strong,
.messenger-list-item__copy strong,
.messenger-conversation__copy strong {
  color: var(--color-text-main);
}

.messenger-sidebar__title span,
.messenger-section__header span,
.messenger-list-item__copy span,
.messenger-list-item__meta small,
.messenger-conversation__copy span,
.messenger-message__bubble span,
.messenger-empty p {
  color: var(--color-text-muted);
}

.messenger-sidebar__back,
.messenger-sidebar__close {
  appearance: none;
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  min-width: 34px;
  min-height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 1px solid var(--button-control-border);
  border-radius: 50%;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  outline: none;
  line-height: 1;
  cursor: pointer;
  box-shadow: var(--shadow-soft);
}

.messenger-sidebar__back:hover,
.messenger-sidebar__close:hover {
  background: var(--button-control-hover);
}

.messenger-sidebar__body {
  min-height: 0;
}

.messenger-sidebar__body--list {
  display: grid;
  gap: 18px;
  align-content: start;
  overflow: auto;
  padding-right: 4px;
}

.messenger-sidebar__body--conversation {
  min-height: 0;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  gap: 14px;
}

.messenger-section {
  display: grid;
  gap: 10px;
}

.messenger-section__header {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.9rem;
}

.messenger-section__heading {
  display: inline-flex;
  align-items: baseline;
  gap: 6px;
}

.messenger-section__heading span {
  font-size: 0.88rem;
  font-weight: 700;
}

.messenger-list {
  display: grid;
  gap: 8px;
}

.messenger-list-item {
  width: 100%;
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  padding: 10px;
  border: 1px solid transparent;
  border-radius: 14px;
  color: var(--color-text-main);
  background: var(--color-surface);
  text-align: left;
  font: inherit;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.messenger-list-item:hover {
  background: var(--color-library-item-hover);
  border-color: var(--color-library-item-active-border);
}

.messenger-list-item__avatar,
.messenger-conversation__avatar {
  width: 44px;
  height: 44px;
}

.messenger-list-item__copy,
.messenger-conversation__copy {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.messenger-conversation__copy {
  margin-left: 4px;
}

.messenger-conversation__name {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-main);
  font-weight: 800;
  text-align: left;
}

.messenger-conversation__name:hover {
  text-decoration: underline;
}

.messenger-list-item__copy strong,
.messenger-list-item__copy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.messenger-list-item__meta {
  display: grid;
  justify-items: end;
  gap: 6px;
}

.messenger-list-item__badge {
  min-width: 20px;
  min-height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 6px;
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border: 1px solid var(--button-primary-border);
  font-size: 0.76rem;
  font-weight: 900;
}

.messenger-share-prompt {
  display: grid;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--color-card-border);
  border-radius: 16px;
  background: var(--color-surface);
}

.messenger-share-prompt--compact {
  padding: 10px 12px;
}

.messenger-share-prompt__copy {
  display: grid;
  gap: 4px;
}

.messenger-share-prompt__copy strong {
  color: var(--color-text-main);
}

.messenger-share-prompt__copy span {
  color: var(--color-text-muted);
  font-size: 0.86rem;
}

.messenger-share-prompt__dismiss {
  justify-self: end;
  padding: 0;
  border: 0;
  color: var(--color-primary);
  background: transparent;
  font: inherit;
  font-weight: 700;
  cursor: pointer;
}

.messenger-shared-card {
  width: 100%;
  display: grid;
  grid-template-columns: 52px minmax(0, 1fr);
  align-items: center;
  gap: 10px;
  padding: 8px;
  border: 1px solid var(--color-card-border);
  border-radius: 14px;
  color: inherit;
  background: var(--color-card-surface);
  text-align: left;
  cursor: pointer;
}

.messenger-shared-card--draft {
  background: var(--color-surface);
}

.messenger-shared-card__image {
  width: 52px;
  height: 52px;
  border-radius: 12px;
}

.messenger-shared-card__image-shell {
  position: relative;
  width: 52px;
  height: 52px;
  display: block;
}

.messenger-shared-card__play {
  position: absolute;
  inset: 50% auto auto 50%;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 50%;
  color: #fff;
  background: rgba(12, 24, 42, 0.78);
  opacity: 0;
  transform: translate(-50%, calc(-50% + 2px));
  cursor: pointer;
  transition: opacity 0.15s ease, transform 0.15s ease, background 0.15s ease;
}

.messenger-shared-card:hover .messenger-shared-card__play,
.messenger-shared-card:focus-visible .messenger-shared-card__play {
  opacity: 1;
  transform: translate(-50%, -50%);
}

.messenger-shared-card__play:hover {
  background: rgba(9, 18, 32, 0.92);
}

.messenger-shared-card__copy {
  min-width: 0;
  display: grid;
  gap: 1px;
}

.messenger-shared-card__copy strong {
  color: var(--color-text-main);
  font-weight: 800;
  font-size: 0.98rem;
  line-height: 1.15;
}

.messenger-shared-card__copy small,
.messenger-shared-card__copy span {
  color: var(--color-text-muted);
  font-size: 0.8rem;
  line-height: 1.15;
}

.messenger-shared-card__copy strong,
.messenger-shared-card__copy small,
.messenger-shared-card__copy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:global([data-theme='dark']) .messenger-shared-card__copy strong {
  color: #10243f;
}

:global([data-theme='dark']) .messenger-shared-card__copy small,
:global([data-theme='dark']) .messenger-shared-card__copy span {
  color: rgba(14, 34, 62, 0.96);
}

.messenger-state,
.messenger-empty {
  min-height: 160px;
  display: grid;
  place-items: center;
  gap: 8px;
  padding: 18px;
  border: 1px dashed var(--color-border);
  border-radius: 16px;
  text-align: center;
}

.messenger-state {
  color: var(--color-text-muted);
}

.messenger-state--error {
  color: var(--color-error-text);
}

.messenger-empty strong {
  color: var(--color-text-main);
}

.messenger-empty--conversation {
  min-height: 100%;
}

.messenger-messages {
  min-height: 0;
  display: grid;
  align-content: start;
  gap: 10px;
  overflow: auto;
  padding-right: 4px;
}

.messenger-message {
  display: flex;
  justify-content: flex-start;
}

.messenger-message--own {
  justify-content: flex-end;
}

.messenger-message__bubble {
  max-width: 88%;
  display: grid;
  gap: 6px;
  padding: 10px 12px;
  border-radius: 16px 16px 16px 6px;
  color: var(--color-text-main);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
}

.messenger-message--own .messenger-message__bubble {
  border-radius: 16px 16px 6px 16px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border-color: var(--button-primary-border);
}

.messenger-message__bubble p {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
}

.messenger-message__bubble .messenger-shared-card {
  margin-top: 2px;
}

.messenger-message__time {
  font-size: 0.76rem;
}

.messenger-message--own .messenger-message__time {
  color: var(--color-surface);
}

.messenger-message--own .messenger-shared-card {
  border-color: rgba(255, 255, 255, 0.22);
  background: rgba(10, 33, 62, 0.22);
}

.messenger-message--own .messenger-shared-card__copy strong {
  color: var(--button-primary-text);
}

.messenger-message--own .messenger-shared-card__copy small,
.messenger-message--own .messenger-shared-card__copy span {
  color: rgba(255, 255, 255, 0.82);
}

.messenger-message--own .messenger-shared-card__play {
  background: rgba(255, 255, 255, 0.18);
}

.messenger-message--own .messenger-shared-card__play:hover {
  background: rgba(255, 255, 255, 0.28);
}

.messenger-composer {
  display: grid;
  gap: 10px;
}

.messenger-composer__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.messenger-composer__field textarea {
  width: 100%;
  min-height: 54px;
  resize: none;
  overflow: hidden;
  padding: 15px 18px;
  border-radius: 24px;
  border: 1px solid var(--color-input-border);
  color: var(--color-input-text);
  background: var(--color-input-bg);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.04),
    0 10px 24px rgba(7, 17, 34, 0.14);
  font: inherit;
  line-height: 1.45;
}

.messenger-composer__field textarea:focus {
  outline: none;
  border-color: var(--color-input-focus-border);
  box-shadow: 0 0 0 3px var(--color-input-focus-ring);
}

.messenger-composer__send {
  min-height: 40px;
  padding: 0 18px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.messenger-composer__cancel {
  min-height: 40px;
  padding: 0 16px;
  border: 1px solid var(--button-control-border);
  border-radius: 999px;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.messenger-composer__send:hover:not(:disabled) {
  background: var(--button-primary-hover);
}

.messenger-composer__cancel:hover {
  background: var(--button-control-hover);
}

.messenger-composer__send:disabled {
  opacity: 0.56;
  cursor: not-allowed;
}

@media (max-width: 760px) {
  .messenger-sidebar {
    border-radius: 18px 18px 0 0;
  }
}
</style>
