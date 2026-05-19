<template>
  <aside class="track-comments-sidebar">
    <header class="track-comments-sidebar__header">
      <div class="track-comments-sidebar__title">
        <div>
          <strong>{{ tl('Track comments') }}</strong>
          <span>{{ currentTrackTitle }}</span>
        </div>
      </div>

      <button class="track-comments-sidebar__close" type="button" :aria-label="tl('Close comments')" @click="closeTrackComments">
        <Icon :icon="getIcon('material-symbols:close-rounded')" class="ui-icon ui-icon--md" />
      </button>
    </header>

    <div class="track-comments-sidebar__body">
      <div v-if="commentsError && !hasComments" class="track-comments-sidebar__state track-comments-sidebar__state--error">
        {{ commentsError }}
      </div>
      <div v-else-if="isLoadingComments" class="track-comments-sidebar__state">
        <span class="loader" />
        <span>{{ tl('Loading comments...') }}</span>
      </div>
      <div v-else-if="!currentTrackId" class="track-comments-sidebar__empty">
        <strong>{{ tl('No track selected') }}</strong>
        <span>{{ tl('Start playing a track to see and leave comments.') }}</span>
      </div>
      <div v-else-if="!hasComments" class="track-comments-sidebar__empty">
        <strong>{{ tl('No comments yet') }}</strong>
        <span>{{ tl('Be the first to leave a note for this moment in the track.') }}</span>
      </div>
      <template v-else>
        <div v-if="commentsError" class="track-comments-sidebar__error-banner">
          {{ commentsError }}
        </div>
        <div class="track-comments-sidebar__list">
          <TrackCommentItem
            v-for="comment in comments"
            :key="comment.id"
            :comment="comment"
            :is-active="activeCommentIds.includes(comment.id)"
            :can-manage="comment.user_uid === currentUserUid || currentUserRole === 'admin'"
            :ref="(element) => setCommentRef(comment.id, element)"
            @seek="seekToTime"
            @open-context-menu="openCommentContextMenu($event, comment.id, comment.text)"
          />
        </div>
      </template>

      <TrackCommentComposer
        v-model="commentDraft"
        :timestamp="composerTimestamp"
        :timestamp-label="composerTimestampLabel"
        :max-timestamp="duration ?? null"
        :is-submitting="isSubmittingComment"
        :disabled="!currentTrackId"
        :submit-label="editingCommentId ? tl('Edit') : tl('Comment')"
        :busy-label="editingCommentId ? tl('Saving...') : tl('Sending...')"
        :show-cancel="Boolean(editingCommentId)"
        @refresh-timestamp="refreshComposerTimestamp"
        @update-timestamp="setComposerTimestamp"
        @cancel="cancelCommentEdit"
        @submit="handleSubmit"
      />
    </div>

    <TrackContextMenu
      :visible="commentMenu.visible"
      :x="commentMenu.x"
      :y="commentMenu.y"
      :items="commentMenuItems"
      @close="closeCommentMenu"
    />
  </aside>
</template>

<script setup lang="ts">
import { Icon } from '@iconify/vue'
import { computed, nextTick, onMounted, reactive, ref, type ComponentPublicInstance } from 'vue'
import type { TrackContextMenuItem } from '~/components/TrackContextMenu.vue'
const { tl } = useLocalizedText()

const { getIcon } = useAppIcons()
const { seekToTime, duration } = useAudioPlayer()
const { getCurrentUser } = useAuth()
const {
  loadComments,
  comments,
  currentTrackId,
  currentTrackTitle,
  hasComments,
  commentsError,
  isTrackCommentsOpen,
  isLoadingComments,
  isSubmittingComment,
  composerTimestamp,
  composerTimestampLabel,
  activeCommentIds,
  refreshComposerTimestamp,
  setComposerTimestamp,
  closeTrackComments,
  submitTrackComment,
  updateTrackComment,
  deleteTrackComment,
} = useTrackComments()

const commentRefs = new Map<number, Element | ComponentPublicInstance>()
const currentUserUid = ref('')
const currentUserRole = ref('')
const editingCommentId = ref<number | null>(null)
const commentDraft = ref('')
const commentMenu = reactive({
  visible: false,
  x: 0,
  y: 0,
  commentId: null as number | null,
})

const setCommentRef = (commentId: number, element: Element | ComponentPublicInstance | null) => {
  if (!element) {
    commentRefs.delete(commentId)
    return
  }

  commentRefs.set(commentId, element)
}

const scrollToActiveComment = async () => {
  if (!activeCommentIds.value.length) {
    return
  }

  await nextTick()
  const firstActiveId = activeCommentIds.value[0]
  const target = commentRefs.get(firstActiveId)
  const element = target instanceof Element ? target : target?.$el

  if (element instanceof HTMLElement) {
    element.scrollIntoView({
      block: 'nearest',
      behavior: 'smooth',
    })
  }
}

const handleSubmit = async (text: string, done: () => void) => {
  const result = editingCommentId.value
    ? await updateTrackComment(editingCommentId.value, text)
    : await submitTrackComment(text)

  if (result.success) {
    if (editingCommentId.value) {
      cancelCommentEdit()
    }
    done()
    await scrollToActiveComment()
  }
}

const closeCommentMenu = () => {
  commentMenu.visible = false
  commentMenu.commentId = null
}

const cancelCommentEdit = () => {
  editingCommentId.value = null
  commentDraft.value = ''
}

const openCommentContextMenu = (event: MouseEvent, commentId: number, text: string) => {
  commentMenu.visible = true
  commentMenu.x = event.clientX
  commentMenu.y = event.clientY
  commentMenu.commentId = commentId

  if (editingCommentId.value !== commentId) {
    commentDraft.value = text
  }
}

const commentMenuItems = computed<TrackContextMenuItem[]>(() => {
  const targetComment = commentMenu.commentId
    ? comments.value.find((comment) => comment.id === commentMenu.commentId)
    : null

  if (!targetComment) {
    return []
  }

  const targetId = targetComment.id

  return [
    {
      key: 'edit',
      label: tl('Edit comment'),
      icon: 'solar:pen-2-linear',
      action: async () => {
        editingCommentId.value = targetId
        commentDraft.value = targetComment.text
      },
    },
    {
      key: 'delete',
      label: tl('Delete comment'),
      icon: 'solar:trash-bin-trash-linear',
      danger: true,
      action: async () => {
        const result = await deleteTrackComment(targetId)

        if (result.success && editingCommentId.value === targetId) {
          cancelCommentEdit()
        }
      },
    },
  ]
})

watch(
  () => activeCommentIds.value.join(','),
  async (nextIds, previousIds) => {
    if (!isTrackCommentsOpen.value || !nextIds || nextIds === previousIds) {
      return
    }

    await scrollToActiveComment()
  }
)

watch(
  () => currentTrackId.value,
  async (nextTrackId) => {
    closeCommentMenu()
    cancelCommentEdit()

    if (isTrackCommentsOpen.value && nextTrackId) {
      refreshComposerTimestamp()
      await loadComments(nextTrackId, { force: true })
    }
  }
)

onMounted(async () => {
  const user = await getCurrentUser()
  currentUserUid.value = user?.uid || ''
  currentUserRole.value = user?.role || ''
})
</script>

<style scoped>
.track-comments-sidebar {
  height: 100%;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  overflow: hidden;
  border: 1px solid var(--color-shell-border);
  border-radius: 18px;
  background: var(--color-sidebar-surface);
  box-shadow: var(--shadow-card);
}

.track-comments-sidebar__header,
.track-comments-sidebar__title {
  min-width: 0;
  flex: 1 1 auto;
  display: flex;
  align-items: center;
}

.track-comments-sidebar__header {
  justify-content: space-between;
  gap: 14px;
  padding: 16px 18px;
  border-bottom: 1px solid var(--color-border);
}

.track-comments-sidebar__title strong,
.track-comments-sidebar__title span {
  display: block;
  min-width: 0;
}

.track-comments-sidebar__title > div {
  min-width: 0;
  width: 100%;
}

.track-comments-sidebar__title strong {
  color: var(--color-text-main);
  font-size: 1rem;
  font-weight: 900;
}

.track-comments-sidebar__title span {
  margin-top: 3px;
  color: var(--color-text-muted);
  font-size: 0.88rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.track-comments-sidebar__close {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--button-control-border);
  border-radius: 50%;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  cursor: pointer;
}

.track-comments-sidebar__close:hover {
  background: var(--button-control-hover);
}

.track-comments-sidebar__body {
  min-height: 0;
  display: grid;
  grid-template-rows: minmax(0, 1fr) auto;
  gap: 14px;
  padding: 16px;
}

.track-comments-sidebar__list {
  min-height: 0;
  display: grid;
  align-content: start;
  gap: 10px;
  overflow: auto;
  padding-right: 4px;
}

.track-comments-sidebar__error-banner {
  padding: 10px 12px;
  border: 1px solid rgba(255, 107, 129, 0.28);
  border-radius: 12px;
  color: var(--color-error-text);
  background: rgba(255, 107, 129, 0.08);
  font-size: 0.88rem;
}

.track-comments-sidebar__state,
.track-comments-sidebar__empty {
  min-height: 120px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  text-align: center;
}

.track-comments-sidebar__state {
  color: var(--color-text-muted);
}

.track-comments-sidebar__state--error {
  color: var(--color-error-text);
}

.track-comments-sidebar__empty strong {
  color: var(--color-text-main);
}

.track-comments-sidebar__empty span {
  color: var(--color-text-muted);
  max-width: 236px;
  line-height: 1.32;
}

@media (max-width: 560px) {
  .track-comments-sidebar__header {
    padding: 14px 16px;
  }

  .track-comments-sidebar__body {
    padding: 14px;
  }

  .track-comments-sidebar__title {
    min-width: 0;
    flex: 1 1 auto;
  }

  .track-comments-sidebar__title strong {
    font-size: 0.95rem;
  }
}
</style>
