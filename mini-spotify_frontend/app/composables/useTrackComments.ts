import type { TrackComment } from '~/composables/useTrackCommentsApi'

export interface TimelineCommentMarker {
  timestamp: number
  count: number
  percent: number
  commentIds: number[]
  isActive: boolean
}

const ACTIVE_COMMENT_THRESHOLD_SECONDS = 0.75
let trackCommentsTrackingInitialized = false

const sortTrackComments = (items: TrackComment[]) => {
  return [...items].sort((left, right) => {
    if (left.timestamp !== right.timestamp) {
      return left.timestamp - right.timestamp
    }

    const leftCreatedAt = left.created_at || ''
    const rightCreatedAt = right.created_at || ''

    if (leftCreatedAt !== rightCreatedAt) {
      return leftCreatedAt.localeCompare(rightCreatedAt)
    }

    return left.id - right.id
  })
}

export const useTrackComments = () => {
  const { tl } = useLocalizedText()
  const { getTrackComments, createTrackComment, updateTrackComment: updateTrackCommentRequest, deleteTrackComment: deleteTrackCommentRequest } = useTrackCommentsApi()
  const { currentTrack, currentTime, duration, formatTime } = useAudioPlayer()
  const { activeRightPanel, openRightPanel, closeRightPanel } = useRightPanel()

  const comments = useState<TrackComment[]>('track-comments-items', () => [])
  const isLoadingComments = useState<boolean>('track-comments-loading', () => false)
  const isSubmittingComment = useState<boolean>('track-comments-submitting', () => false)
  const commentsError = useState<string>('track-comments-error', () => '')
  const composerTimestamp = useState<number>('track-comments-composer-timestamp', () => 0)
  const loadedTrackId = useState<number | null>('track-comments-loaded-track-id', () => null)

  const currentTrackId = computed(() => currentTrack.value?.id ?? null)
  const isTrackCommentsOpen = computed(() => activeRightPanel.value === 'trackComments')
  const currentTrackTitle = computed(() => currentTrack.value?.title || tl('Current track'))
  const hasComments = computed(() => comments.value.length > 0)
  const composerTimestampLabel = computed(() => formatTime(composerTimestamp.value))

  const activeTimestamp = computed<number | null>(() => {
    if (!comments.value.length) {
      return null
    }

    const playbackTime = currentTime.value + ACTIVE_COMMENT_THRESHOLD_SECONDS
    let matchedTimestamp: number | null = null

    for (const comment of comments.value) {
      if (comment.timestamp <= playbackTime) {
        matchedTimestamp = comment.timestamp
        continue
      }

      break
    }

    return matchedTimestamp
  })

  const activeCommentIds = computed(() => {
    if (activeTimestamp.value === null) {
      return []
    }

    return comments.value
      .filter((comment) => comment.timestamp === activeTimestamp.value)
      .map((comment) => comment.id)
  })

  const markers = computed<TimelineCommentMarker[]>(() => {
    if (!comments.value.length || !duration.value) {
      return []
    }

    const grouped = new Map<number, { count: number; commentIds: number[] }>()

    for (const comment of comments.value) {
      const existing = grouped.get(comment.timestamp)

      if (existing) {
        existing.count += 1
        existing.commentIds.push(comment.id)
        continue
      }

      grouped.set(comment.timestamp, {
        count: 1,
        commentIds: [comment.id],
      })
    }

    return [...grouped.entries()]
      .sort((left, right) => left[0] - right[0])
      .map(([timestamp, group]) => ({
        timestamp,
        count: group.count,
        commentIds: group.commentIds,
        percent: Math.min(100, Math.max(0, (timestamp / duration.value) * 100)),
        isActive: activeTimestamp.value === timestamp,
      }))
  })

  const resetCommentsState = () => {
    comments.value = []
    commentsError.value = ''
    loadedTrackId.value = null
  }

  const refreshComposerTimestamp = () => {
    composerTimestamp.value = Math.max(0, Math.floor(currentTime.value))
  }

  const setComposerTimestamp = (seconds: number) => {
    const normalized = Math.max(0, Math.floor(seconds))
    const bounded = duration.value ? Math.min(normalized, Math.floor(duration.value)) : normalized
    composerTimestamp.value = bounded
  }

  const loadComments = async (songId = currentTrackId.value, options: { silent?: boolean; force?: boolean } = {}) => {
    if (!songId) {
      resetCommentsState()
      return
    }

    if (!options.force && loadedTrackId.value === songId && comments.value.length) {
      return
    }

    if (!options.silent) {
      isLoadingComments.value = true
    }

    commentsError.value = ''

    try {
      const response = await getTrackComments(songId)
      comments.value = sortTrackComments(response.comments)
      loadedTrackId.value = response.song_id
    } catch (error: any) {
      comments.value = []
      loadedTrackId.value = songId
      commentsError.value = error?.data?.message || tl('Could not load track comments')
    } finally {
      isLoadingComments.value = false
    }
  }

  const openTrackComments = async () => {
    if (!currentTrackId.value) {
      return
    }

    refreshComposerTimestamp()
    openRightPanel('trackComments')
    await loadComments(currentTrackId.value, { force: loadedTrackId.value !== currentTrackId.value })
  }

  const closeTrackComments = () => {
    if (activeRightPanel.value === 'trackComments') {
      closeRightPanel()
    }
  }

  const toggleTrackComments = async () => {
    if (isTrackCommentsOpen.value) {
      closeTrackComments()
      return
    }

    await openTrackComments()
  }

  const submitTrackComment = async (text: string) => {
    if (!currentTrackId.value || isSubmittingComment.value) {
      return { success: false, message: tl('Track is not selected') }
    }

    const trimmedText = text.trim()

    if (!trimmedText) {
      return { success: false, message: tl('Comment cannot be empty') }
    }

    isSubmittingComment.value = true
    commentsError.value = ''

    try {
      const comment = await createTrackComment(currentTrackId.value, {
        text: trimmedText,
        timestamp: composerTimestamp.value,
      })

      comments.value = sortTrackComments([...comments.value, comment])
      loadedTrackId.value = currentTrackId.value

      return { success: true, comment }
    } catch (error: any) {
      commentsError.value = error?.data?.message || tl('Could not send comment')
      return { success: false, message: commentsError.value }
    } finally {
      isSubmittingComment.value = false
    }
  }

  const updateTrackComment = async (commentId: number, text: string) => {
    if (!currentTrackId.value) {
      return { success: false, message: tl('Track is not selected') }
    }

    const trimmedText = text.trim()

    if (!trimmedText) {
      return { success: false, message: tl('Comment cannot be empty') }
    }

    commentsError.value = ''

    try {
      const updatedComment = await updateTrackCommentRequest(currentTrackId.value, commentId, {
        text: trimmedText,
      })

      comments.value = sortTrackComments(
        comments.value.map((comment) => (comment.id === commentId ? updatedComment : comment))
      )

      return { success: true, comment: updatedComment }
    } catch (error: any) {
      commentsError.value = error?.data?.message || tl('Could not update comment')
      return { success: false, message: commentsError.value }
    }
  }

  const deleteTrackComment = async (commentId: number) => {
    if (!currentTrackId.value) {
      return { success: false, message: tl('Track is not selected') }
    }

    commentsError.value = ''

    try {
      await deleteTrackCommentRequest(currentTrackId.value, commentId)
      comments.value = comments.value.filter((comment) => comment.id !== commentId)
      return { success: true }
    } catch (error: any) {
      commentsError.value = error?.data?.message || tl('Could not delete comment')
      return { success: false, message: commentsError.value }
    }
  }

  if (!trackCommentsTrackingInitialized) {
    trackCommentsTrackingInitialized = true

    watch(
      () => currentTrack.value?.id ?? null,
      async (nextTrackId) => {
        comments.value = []
        commentsError.value = ''
        loadedTrackId.value = null
        setComposerTimestamp(currentTime.value)

        if (!nextTrackId) {
          return
        }

        await loadComments(nextTrackId, { force: true })
      },
      { immediate: true }
    )
  }

  return {
    comments,
    markers,
    isTrackCommentsOpen,
    isLoadingComments,
    isSubmittingComment,
    commentsError,
    composerTimestamp,
    composerTimestampLabel,
    currentTrackId,
    currentTrackTitle,
    activeTimestamp,
    activeCommentIds,
    hasComments,
    loadComments,
    refreshComposerTimestamp,
    setComposerTimestamp,
    openTrackComments,
    closeTrackComments,
    toggleTrackComments,
    submitTrackComment,
    updateTrackComment,
    deleteTrackComment,
  }
}
