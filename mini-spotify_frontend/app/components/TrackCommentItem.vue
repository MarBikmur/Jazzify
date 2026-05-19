<template>
  <article
    class="track-comment-item"
    :class="{ 'track-comment-item--active': isActive }"
    @contextmenu="handleContextMenu"
  >
    <button
      class="track-comment-item__avatar"
      type="button"
      :aria-label="`Open ${comment.user?.name || 'user'} profile`"
      @click="openUserProfile"
    >
      <ArtworkCover
        class="track-comment-item__avatar-cover"
        :src="comment.user?.avatar_url || mediaUrl(comment.user?.avatar_path)"
        :alt="comment.user?.name || 'User avatar'"
        :fallback="(comment.user?.name || 'U').slice(0, 1).toUpperCase()"
        shape="circle"
      />
    </button>

    <div class="track-comment-item__body">
      <div class="track-comment-item__meta">
        <button class="track-comment-item__name" type="button" @click="openUserProfile">
          {{ comment.user?.name || 'User' }}
        </button>
        <button class="track-comment-item__timestamp" type="button" @click="$emit('seek', comment.timestamp)">
          {{ formatTime(comment.timestamp) }}
        </button>
      </div>
      <p>{{ comment.text }}</p>
    </div>
  </article>
</template>

<script setup lang="ts">
import type { TrackComment } from '~/composables/useTrackCommentsApi'

const props = defineProps<{
  comment: TrackComment
  isActive?: boolean
  canManage?: boolean
}>()

const emit = defineEmits<{
  seek: [timestamp: number]
  'open-context-menu': [event: MouseEvent]
}>()

const router = useRouter()
const { mediaUrl } = useMediaUrl()
const { formatTime } = useAudioPlayer()

const openUserProfile = async () => {
  if (!props.comment.user?.uid) {
    return
  }

  await router.push(`/users/${props.comment.user.uid}`)
}

const handleContextMenu = (event: MouseEvent) => {
  if (!props.canManage) {
    return
  }

  event.preventDefault()
  emit('open-context-menu', event)
}
</script>

<style scoped>
.track-comment-item {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr);
  gap: 10px;
  align-items: center;
  padding: 10px 12px;
  border: 1px solid rgba(24, 78, 140, 0.16);
  border-radius: 14px;
  background: var(--color-surface);
  transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}

.track-comment-item--active {
  background: rgba(0, 163, 255, 0.12);
  border-color: rgba(35, 118, 214, 0.55);
  box-shadow:
    inset 0 0 0 1px rgba(255, 255, 255, 0.22),
    0 0 0 1px rgba(35, 118, 214, 0.18);
}

.track-comment-item__avatar,
.track-comment-item__name,
.track-comment-item__timestamp {
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.track-comment-item__avatar {
  width: 42px;
  height: 42px;
  display: inline-flex;
  border-radius: 50%;
}

.track-comment-item__avatar-cover {
  width: 100%;
  height: 100%;
}

.track-comment-item__body {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.track-comment-item__meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.track-comment-item__name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--color-text-main);
  font-weight: 800;
  line-height: 1.1;
}

.track-comment-item__timestamp {
  color: var(--color-primary);
  font-size: 0.82rem;
  font-weight: 800;
  white-space: nowrap;
  line-height: 1;
}

.track-comment-item__name:hover,
.track-comment-item__timestamp:hover {
  text-decoration: underline;
}

.track-comment-item p {
  margin: 0;
  color: var(--color-text-main);
  line-height: 1.25;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
