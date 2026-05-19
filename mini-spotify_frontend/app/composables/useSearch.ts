import type { Album } from '~/composables/useAlbum'
import { useAlbum } from '~/composables/useAlbum'
import type { Genre } from '~/composables/useGenre'
import { useGenre } from '~/composables/useGenre'
import type { Artist } from '~/composables/useArtist'
import type { Playlist, PlaylistSong } from '~/composables/usePlaylist'
import type { PublicProfileUser } from '~/composables/useUserProfile'
import { useApi } from '~/composables/useApi'

export interface SearchResults {
  tracks: PlaylistSong[]
  albums: Album[]
  playlists: Playlist[]
  artists: Artist[]
  users: PublicProfileUser[]
  genres: Genre[]
}

interface SearchIndex {
  albums: Album[]
  genres: Genre[]
}

interface SearchOptions {
  limit?: number
}

const normalizeSearchText = (value?: string | null) =>
  String(value || '')
    .trim()
    .toLocaleLowerCase()

const uniqueById = <T extends { id: number }>(items: T[]) => {
  const seen = new Set<number>()
  return items.filter((item) => {
    if (seen.has(item.id)) {
      return false
    }

    seen.add(item.id)
    return true
  })
}

export const getAlbumGenreNames = (album: Partial<Album> | null | undefined): string[] => {
  if (!album) {
    return []
  }

  const genreNames = new Set<string>()

  const sourceValues = [
    (album as any).genre,
    (album as any).genres,
    (album as any).category,
    (album as any).tags,
  ]

  sourceValues.forEach((value) => {
    if (!value) {
      return
    }

    if (Array.isArray(value)) {
      value.forEach((entry) => {
        if (typeof entry === 'string') {
          genreNames.add(entry)
          return
        }

        if (entry && typeof entry.name === 'string') {
          genreNames.add(entry.name)
        }
      })

      return
    }

    if (typeof value === 'string') {
      genreNames.add(value)
      return
    }

    if (typeof value.name === 'string') {
      genreNames.add(value.name)
    }
  })

  ;(album.songs || []).forEach((song) => {
    if (song.genre?.name) {
      genreNames.add(song.genre.name)
    }
  })

  return Array.from(genreNames).filter(Boolean)
}

export const albumMatchesGenre = (album: Partial<Album> | null | undefined, genreName: string) => {
  const normalizedGenre = normalizeSearchText(genreName)

  if (!normalizedGenre) {
    return false
  }

  return getAlbumGenreNames(album).some((name) => normalizeSearchText(name) === normalizedGenre)
}

export const useSearch = () => {
  const { apiFetch } = useApi()
  const { getAlbums } = useAlbum()
  const { getGenres } = useGenre()

  const indexState = useState<SearchIndex | null>('search-index', () => null)
  const pendingState = useState<Promise<SearchIndex> | null>('search-index-pending', () => null)

  const normalizeQuery = (query?: string | null) => query?.trim() || ''

  const buildGenreIndex = async (albums: Album[]) => {
    const genreMap = new Map<string, Genre>()

    try {
      const apiGenres = await getGenres()
      apiGenres.forEach((genre) => {
        const key = normalizeSearchText(genre.name)
        if (key) {
          genreMap.set(key, genre)
        }
      })
    } catch (error) {
      console.error('Genres loading error:', error)
    }

    albums.forEach((album) => {
      getAlbumGenreNames(album).forEach((genreName) => {
        const key = normalizeSearchText(genreName)
        if (!key || genreMap.has(key)) {
          return
        }

        genreMap.set(key, {
          id: Number.MAX_SAFE_INTEGER - genreMap.size,
          name: genreName,
        })
      })
    })

    return Array.from(genreMap.values()).sort((left, right) => left.name.localeCompare(right.name))
  }

  const loadIndex = async (force = false) => {
    if (!force && indexState.value) {
      return indexState.value
    }

    if (!force && pendingState.value) {
      return pendingState.value
    }

    const request = (async () => {
      const [albums] = await Promise.all([
        getAlbums(),
      ])

      const genres = await buildGenreIndex(albums)

      const index = {
        albums: uniqueById(albums),
        genres: uniqueById(genres),
      }

      indexState.value = index
      pendingState.value = null
      return index
    })()

    pendingState.value = request

    try {
      return await request
    } catch (error) {
      pendingState.value = null
      throw error
    }
  }

  const emptyResults = (): SearchResults => ({
    tracks: [],
    albums: [],
    playlists: [],
    artists: [],
    users: [],
    genres: [],
  })

  const search = async (rawQuery?: string | null, options: SearchOptions = {}): Promise<SearchResults> => {
    const query = normalizeQuery(rawQuery)

    if (!query) {
      return emptyResults()
    }

    const params = new URLSearchParams({
      q: query,
    })

    if (typeof options.limit === 'number' && Number.isFinite(options.limit)) {
      const limit = Math.min(25, Math.max(1, Math.round(options.limit)))
      params.set('limit', String(limit))
    }

    return await apiFetch<SearchResults>(`/search?${params.toString()}`)
  }

  const getAlbumsByGenre = async (rawGenreName?: string | null) => {
    const genreName = normalizeQuery(rawGenreName)

    if (!genreName) {
      return []
    }

    const index = await loadIndex()
    return index.albums.filter((album) => albumMatchesGenre(album, genreName))
  }

  return {
    normalizeQuery,
    loadIndex,
    search,
    getAlbumsByGenre,
  }
}
