export const useMediaUrl = () => {
  const config = useRuntimeConfig()

  const mediaBase = computed(() => {
    const apiBase = String(config.public.apiBase || '')

    return `${apiBase.replace(/\/$/, '')}/media`
  })

  const mediaUrl = (path?: string | null) => {
    if (!path) {
      return ''
    }

    if (/^https?:\/\//i.test(path)) {
      return path
    }

    return `${mediaBase.value}/${path.replace(/^\/+/, '')}`
  }

  return {
    mediaUrl,
  }
}
