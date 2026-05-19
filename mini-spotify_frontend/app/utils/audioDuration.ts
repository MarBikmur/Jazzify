export const getAudioDuration = (file: File): Promise<number> =>
  new Promise((resolve, reject) => {
    const audio = document.createElement('audio')
    const objectUrl = URL.createObjectURL(file)

    const cleanup = () => {
      audio.src = ''
      URL.revokeObjectURL(objectUrl)
    }

    audio.preload = 'metadata'
    audio.src = objectUrl

    audio.onloadedmetadata = () => {
      const duration = audio.duration
      cleanup()

      if (!Number.isFinite(duration) || duration < 0) {
        reject(new Error('Failed to read audio duration'))
        return
      }

      resolve(Math.round(duration))
    }

    audio.onerror = () => {
      cleanup()
      reject(new Error('Failed to load audio metadata'))
    }
  })

export const formatDuration = (seconds?: number | null): string => {
  if (!Number.isFinite(Number(seconds)) || Number(seconds) < 0) {
    return '—'
  }

  const totalSeconds = Math.floor(Number(seconds))
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const remainingSeconds = totalSeconds % 60

  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`
  }

  return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`
}
