import { computed, nextTick, ref } from 'vue'

export interface AudioPlayerTrack {
  id: number
  title: string
  duration?: number | null
  playCount?: number | null
  artistName?: string
  artistId?: number | string
  albumTitle?: string
  albumId?: number | string
  coverUrl?: string
  streamUrl?: string
  resolveStreamUrl?: () => Promise<string>
  collectionType?: 'album' | 'playlist'
  collectionId?: number | string
}

type RepeatMode = 'off' | 'all' | 'one'

const audioElement = ref<HTMLAudioElement | null>(null)
const queue = ref<AudioPlayerTrack[]>([])
const currentIndex = ref(-1)
const currentTrack = ref<AudioPlayerTrack | null>(null)
const currentCollectionType = ref<'album' | 'playlist' | null>(null)
const currentCollectionId = ref<number | string | null>(null)
const streamUrl = ref('')
const isPlaying = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')
const duration = ref(0)
const currentTime = ref(0)
const bufferedEnd = ref(0)
const isShuffleEnabled = ref(false)
const repeatMode = ref<RepeatMode>('off')
const isQueueVisible = ref(false)
const volume = ref(0.68)
const isMuted = ref(false)
const playRequestId = ref(0)

const progressPercent = computed(() => {
  if (!duration.value) {
    return 0
  }

  return Math.min(100, Math.max(0, (currentTime.value / duration.value) * 100))
})

const bufferedPercent = computed(() => {
  if (!duration.value) {
    return 0
  }

  return Math.min(100, Math.max(0, (bufferedEnd.value / duration.value) * 100))
})

const volumePercent = computed(() => Math.round(volume.value * 100))
const hasPrevious = computed(() => queue.value.length > 1)
const hasNext = computed(() => queue.value.length > 1)
const repeatLabel = computed(() => {
  if (repeatMode.value === 'one') {
    return 'Repeat one'
  }

  if (repeatMode.value === 'all') {
    return 'Repeat all'
  }

  return 'Repeat off'
})

const applyVolumeState = () => {
  if (!audioElement.value) {
    return
  }

  audioElement.value.volume = volume.value
  audioElement.value.muted = isMuted.value
}

const setAudioElement = (element: HTMLAudioElement | null) => {
  audioElement.value = element
  applyVolumeState()
}

const clearStream = () => {
  streamUrl.value = ''

  if (!audioElement.value) {
    return
  }

  audioElement.value.removeAttribute('src')
  audioElement.value.load()
}

const setQueue = (tracks: AudioPlayerTrack[], startTrackId?: number) => {
  queue.value = tracks

  if (!tracks.length) {
    currentIndex.value = -1
    currentTrack.value = null
    currentCollectionType.value = null
    currentCollectionId.value = null
    isPlaying.value = false
    isLoading.value = false
    clearStream()
    return
  }

  if (typeof startTrackId === 'number') {
    const foundIndex = tracks.findIndex((track) => track.id === startTrackId)
    currentIndex.value = foundIndex >= 0 ? foundIndex : 0
    return
  }

  currentIndex.value = 0
}

const ensureStreamUrl = async (track: AudioPlayerTrack) => {
  if (!track.resolveStreamUrl) {
    if (track.streamUrl) {
      return track.streamUrl
    }

    throw new Error('Track stream is not available')
  }

  track.streamUrl = await track.resolveStreamUrl()
  return track.streamUrl
}

const playTrack = async (track: AudioPlayerTrack) => {
  const requestId = ++playRequestId.value
  const queueIndex = queue.value.findIndex((item) => item.id === track.id)

  if (queueIndex >= 0) {
    currentIndex.value = queueIndex
  } else {
    queue.value = [track]
    currentIndex.value = 0
  }

  currentTrack.value = track
  currentCollectionType.value = track.collectionType || null
  currentCollectionId.value = track.collectionId ?? null
  isLoading.value = true
  errorMessage.value = ''
  duration.value = 0
  currentTime.value = 0
  bufferedEnd.value = 0

  try {
    const nextStreamUrl = await ensureStreamUrl(track)

    if (requestId !== playRequestId.value) {
      return
    }

    streamUrl.value = nextStreamUrl
  } catch (error) {
    if (requestId !== playRequestId.value) {
      return
    }

    console.error('Track stream resolving error:', error)
    errorMessage.value = 'Could not resolve track stream'
    isLoading.value = false
    isPlaying.value = false
    return
  }

  await nextTick()

  const audio = audioElement.value

  if (!audio) {
    errorMessage.value = 'Player is not ready'
    isLoading.value = false
    isPlaying.value = false
    return
  }

  audio.pause()
  applyVolumeState()
  audio.load()

  try {
    await audio.play()

    if (requestId !== playRequestId.value) {
      return
    }

    isPlaying.value = true
    errorMessage.value = ''
  } catch (error) {
    if (requestId !== playRequestId.value) {
      return
    }

    if (error instanceof DOMException && error.name === 'AbortError') {
      isPlaying.value = false
      return
    }

    console.error('Audio playback start error:', error)
    errorMessage.value = 'Could not start playback'
    isPlaying.value = false
  } finally {
    if (requestId === playRequestId.value) {
      isLoading.value = false
    }
  }
}

const playByIndex = async (index: number) => {
  if (index < 0 || index >= queue.value.length) {
    return
  }

  const track = queue.value[index]

  if (!track) {
    return
  }

  currentIndex.value = index
  await playTrack(track)
}

const togglePlay = async () => {
  const audio = audioElement.value

  if (!audio || !streamUrl.value) {
    return
  }

  if (audio.paused) {
    try {
      await audio.play()
      isPlaying.value = true
      errorMessage.value = ''
    } catch (error) {
      console.error('Audio playback toggle error:', error)
      errorMessage.value = 'Could not resume playback'
      isPlaying.value = false
    }
    return
  }

  audio.pause()
  isPlaying.value = false
}

const seekToPercent = (percent: number) => {
  const audio = audioElement.value

  if (!audio || !duration.value) {
    return
  }

  const safePercent = Math.min(100, Math.max(0, percent))
  audio.currentTime = (safePercent / 100) * duration.value
  currentTime.value = audio.currentTime
}

const seekToTime = (seconds: number) => {
  const audio = audioElement.value

  if (!audio) {
    return
  }

  const nextTime = Math.min(Math.max(0, seconds), duration.value || Math.max(0, seconds))
  audio.currentTime = nextTime
  currentTime.value = audio.currentTime
}

const setVolume = (nextVolume: number) => {
  volume.value = Math.min(1, Math.max(0, nextVolume))

  if (volume.value > 0) {
    isMuted.value = false
  }

  applyVolumeState()
}

const toggleMute = () => {
  isMuted.value = !isMuted.value
  applyVolumeState()
}

const toggleShuffle = () => {
  isShuffleEnabled.value = !isShuffleEnabled.value
}

const cycleRepeatMode = () => {
  if (repeatMode.value === 'off') {
    repeatMode.value = 'all'
    return
  }

  if (repeatMode.value === 'all') {
    repeatMode.value = 'one'
    return
  }

  repeatMode.value = 'off'
}

const toggleQueue = () => {
  isQueueVisible.value = !isQueueVisible.value
}

const addToQueue = (track: AudioPlayerTrack) => {
  queue.value = [...queue.value, track]

  if (currentIndex.value < 0) {
    currentIndex.value = 0
  }
}

const getNextIndex = () => {
  if (!queue.value.length || currentIndex.value < 0) {
    return -1
  }

  if (isShuffleEnabled.value && queue.value.length > 1) {
    let nextIndex = currentIndex.value

    while (nextIndex === currentIndex.value) {
      nextIndex = Math.floor(Math.random() * queue.value.length)
    }

    return nextIndex
  }

  const sequentialIndex = currentIndex.value + 1

  if (sequentialIndex < queue.value.length) {
    return sequentialIndex
  }

  if (repeatMode.value === 'all') {
    return 0
  }

  return -1
}

const getPreviousIndex = () => {
  if (!queue.value.length || currentIndex.value < 0) {
    return -1
  }

  if (isShuffleEnabled.value && queue.value.length > 1) {
    let previousIndex = currentIndex.value

    while (previousIndex === currentIndex.value) {
      previousIndex = Math.floor(Math.random() * queue.value.length)
    }

    return previousIndex
  }

  const sequentialIndex = currentIndex.value - 1

  if (sequentialIndex >= 0) {
    return sequentialIndex
  }

  if (repeatMode.value === 'all') {
    return queue.value.length - 1
  }

  return -1
}

const playNext = async () => {
  const nextIndex = getNextIndex()

  if (nextIndex < 0) {
    return
  }

  await playByIndex(nextIndex)
}

const playPrevious = async () => {
  const audio = audioElement.value

  if (audio && audio.currentTime > 5) {
    audio.currentTime = 0
    currentTime.value = 0
    return
  }

  const previousIndex = getPreviousIndex()

  if (previousIndex < 0) {
    return
  }

  await playByIndex(previousIndex)
}

const updateBuffered = () => {
  const audio = audioElement.value

  if (!audio?.buffered.length) {
    bufferedEnd.value = 0
    return
  }

  bufferedEnd.value = audio.buffered.end(audio.buffered.length - 1)
}

const onLoadedMetadata = () => {
  const audio = audioElement.value
  duration.value = Number.isFinite(audio?.duration) ? audio?.duration || 0 : 0
  updateBuffered()
  errorMessage.value = ''
}

const onTimeUpdate = () => {
  const audio = audioElement.value
  currentTime.value = audio?.currentTime || 0
}

const onProgress = () => {
  updateBuffered()
}

const onEnded = async () => {
  if (repeatMode.value === 'one' && currentTrack.value) {
    await playTrack(currentTrack.value)
    return
  }

  const nextIndex = getNextIndex()

  if (nextIndex >= 0) {
    await playByIndex(nextIndex)
    return
  }

  isPlaying.value = false
  currentTime.value = duration.value
}

const onError = () => {
  const mediaError = audioElement.value?.error

  if (mediaError?.code === MediaError.MEDIA_ERR_ABORTED) {
    isLoading.value = false
    return
  }

  errorMessage.value = mediaError?.message || 'Audio playback failed'
  isPlaying.value = false
  isLoading.value = false
}

const formatTime = (seconds: number) => {
  if (!Number.isFinite(seconds) || seconds <= 0) {
    return '0:00'
  }

  const minutes = Math.floor(seconds / 60)
  const rest = Math.floor(seconds % 60).toString().padStart(2, '0')

  return `${minutes}:${rest}`
}

export const useAudioPlayer = () => ({
  queue,
  currentIndex,
  currentTrack,
  currentCollectionType,
  currentCollectionId,
  streamUrl,
  isPlaying,
  isLoading,
  errorMessage,
  duration,
  currentTime,
  bufferedEnd,
  progressPercent,
  bufferedPercent,
  isShuffleEnabled,
  repeatMode,
  repeatLabel,
  isQueueVisible,
  volume,
  volumePercent,
  isMuted,
  hasPrevious,
  hasNext,
  setAudioElement,
  clearStream,
  setQueue,
  addToQueue,
  playTrack,
  playByIndex,
  playNext,
  playPrevious,
  togglePlay,
  seekToPercent,
  seekToTime,
  setVolume,
  toggleMute,
  toggleShuffle,
  cycleRepeatMode,
  toggleQueue,
  onLoadedMetadata,
  onTimeUpdate,
  onProgress,
  onEnded,
  onError,
  formatTime,
})
