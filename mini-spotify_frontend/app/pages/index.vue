<template>
  <div class="home-page">
    <PageState v-if="loading" :message="tl('Loading home...')" />
    <PageState v-else-if="errorMessage" variant="error" :message="errorMessage" />

    <template v-else>
      <section class="content-section">
        <PageSectionHeader :title="tl('Artists')" />

        <div v-if="artists.length" class="carousel-row carousel-row--artists" @mouseleave="clearRowFocus">
          <button class="carousel-row__button carousel-row__button--left" type="button" :aria-label="tl('Back')" @click="scrollRow(artistsRowRef, 'left')">
            <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="carousel-row__icon" />
          </button>
          <div ref="artistsRowRef" class="media-grid media-grid--artists">
            <MediaTile
              v-for="artist in artists"
              :key="artist.id"
              class="artist-tile"
              :to="`/albums/${artist.id}`"
              :title="artist.name"
              :subtitle="tl('Artist')"
              :image-src="artistAvatar(artist)"
              :fallback="artist.name.slice(0, 1).toUpperCase()"
              shape="circle"
            />
          </div>
          <button class="carousel-row__button carousel-row__button--right" type="button" :aria-label="tl('Forward')" @click="scrollRow(artistsRowRef, 'right')">
            <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="carousel-row__icon" />
          </button>
        </div>
        <PageState v-else variant="empty" :message="tl('No artists yet.')" min-height="180px" />
      </section>

      <section class="content-section">
        <PageSectionHeader :title="tl('Latest albums')" />

        <div v-if="latestAlbums.length" class="carousel-row" @mouseleave="clearRowFocus">
          <button class="carousel-row__button carousel-row__button--left" type="button" :aria-label="tl('Back')" @click="scrollRow(albumsRowRef, 'left')">
            <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="carousel-row__icon" />
          </button>
          <div ref="albumsRowRef" class="media-grid">
            <MediaTile
              v-for="album in latestAlbums"
              :key="album.id"
              :to="album.artist?.id ? `/albums/${album.artist.id}/${album.id}` : ''"
              :title="album.title"
              :subtitle="album.artist?.name || tl('Artist')"
              :image-src="albumCover(album)"
              fallback="A"
            />
          </div>
          <button class="carousel-row__button carousel-row__button--right" type="button" :aria-label="tl('Forward')" @click="scrollRow(albumsRowRef, 'right')">
            <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="carousel-row__icon" />
          </button>
        </div>
        <PageState v-else variant="empty" :message="tl('No albums yet.')" min-height="180px" />
      </section>

      <section class="content-section">
        <PageSectionHeader :title="tl(`Users's playlists`)" />

        <div v-if="visiblePlaylists.length" class="carousel-row" @mouseleave="clearRowFocus">
          <button class="carousel-row__button carousel-row__button--left" type="button" :aria-label="tl('Back')" @click="scrollRow(playlistsRowRef, 'left')">
            <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="carousel-row__icon" />
          </button>
          <div ref="playlistsRowRef" class="media-grid">
            <MediaTile
              v-for="playlist in visiblePlaylists"
              :key="playlist.id"
              :to="`/playlists/${playlist.id}`"
              :title="tl(playlist.title)"
              :subtitle="playlistTrackSubtitle(playlist)"
              :image-src="playlistCover(playlist)"
              fallback-icon="solar:music-note-2-bold"
              fallback-variant="playlist"
            />
          </div>
          <button class="carousel-row__button carousel-row__button--right" type="button" :aria-label="tl('Forward')" @click="scrollRow(playlistsRowRef, 'right')">
            <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="carousel-row__icon" />
          </button>
        </div>
        <PageState v-else variant="empty" :message="tl('No playlists yet.')" min-height="180px" />
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import { FAVORITES_PLAYLIST_TITLE } from '~/composables/useLikedSongs'
import type { Playlist } from '~/composables/usePlaylist'

interface User {
  uid: string
  name: string
  email: string
  role?: string
}

interface Artist {
  id: number
  name: string
  image_path?: string
  image_url?: string
}

interface Album {
  id: number
  title: string
  cover_image_path?: string
  cover_image_url?: string
  artist?: {
    id: number
    name: string
  }
}

const { isRussian } = useAppLocale()
const { tl } = useLocalizedText()
const { getIcon } = useAppIcons()
const { getCurrentUser } = useAuth()
const { getArtists } = useArtist()
const { getLatestAlbums } = useAlbum()
const { getPlaylists } = usePlaylist()
const { mediaUrl } = useMediaUrl()

const user = ref<User | null>(null)
const artists = ref<Artist[]>([])
const latestAlbums = ref<Album[]>([])
const playlists = ref<Playlist[]>([])
const loading = ref(false)
const errorMessage = ref('')
const artistsRowRef = ref<HTMLElement | null>(null)
const albumsRowRef = ref<HTMLElement | null>(null)
const playlistsRowRef = ref<HTMLElement | null>(null)

const visiblePlaylists = computed(() =>
  playlists.value.filter((playlist) => playlist.title !== FAVORITES_PLAYLIST_TITLE)
)

const artistAvatar = (artist: Artist) => artist.image_url || mediaUrl(artist.image_path)
const albumCover = (album: Album) => album.cover_image_url || mediaUrl(album.cover_image_path)
const playlistCover = (playlist: Playlist) => playlist.cover_image_url || mediaUrl(playlist.cover_image_path)
const pluralizeRu = (count: number, one: string, few: string, many: string) => {
  if (count % 10 === 1 && count % 100 !== 11) {
    return `${count} ${one}`
  }

  if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) {
    return `${count} ${few}`
  }

  return `${count} ${many}`
}

const formatTracksCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? '1 track' : `${count} tracks`
  }

  return pluralizeRu(count, 'трек', 'трека', 'треков')
}

const playlistTrackSubtitle = (playlist: Playlist) => formatTracksCount(playlist.songs_count ?? playlist.songs?.length ?? 0)

const scrollRow = (container: HTMLElement | null, direction: 'left' | 'right') => {
  if (!container) {
    return
  }

  const distance = 392
  container.scrollBy({
    left: direction === 'left' ? -distance : distance,
    behavior: 'smooth',
  })
}

const clearRowFocus = (event: MouseEvent) => {
  const row = event.currentTarget as HTMLElement | null
  const activeElement = document.activeElement

  if (!(row && activeElement instanceof HTMLElement)) {
    return
  }

  if (row.contains(activeElement) && activeElement.classList.contains('carousel-row__button')) {
    activeElement.blur()
  }
}

onMounted(async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const [userData, artistsData, albumsData, playlistsData] = await Promise.all([
      getCurrentUser(),
      getArtists(),
      getLatestAlbums(),
      getPlaylists(),
    ])

    user.value = userData
    artists.value = artistsData
    latestAlbums.value = albumsData
    playlists.value = playlistsData
  } catch (error: any) {
    console.error('Home loading error:', error)
    errorMessage.value = error?.data?.message || tl('Failed to load home page')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.home-page {
  min-height: 100%;
  display: grid;
  gap: 26px;
}

.content-section {
  display: grid;
  gap: 14px;
}

.carousel-row {
  position: relative;
  width: 100%;
  overflow: hidden;
  padding: 0 28px;
  background: transparent;
}

.media-grid {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(180px, 180px);
  gap: 16px;
  overflow-x: auto;
  overflow-y: hidden;
  width: 100%;
  align-items: start;
  padding: 2px 0;
  scroll-behavior: smooth;
  scrollbar-width: none;
  background: transparent;
}

.media-grid::-webkit-scrollbar {
  display: none;
}

.media-grid > * {
  width: 180px;
  min-width: 180px;
  max-width: 180px;
}

.carousel-row--artists,
.carousel-row--artists .media-grid,
.carousel-row--artists .media-grid--artists {
  background: transparent !important;
}

.media-grid--artists {
  grid-auto-columns: minmax(180px, 180px);
}

.media-grid--artists > * {
  width: 180px;
  min-width: 180px;
  max-width: 180px;
}

.carousel-row__button {
  position: absolute;
  top: 50%;
  z-index: 2;
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--button-control-border);
  border-radius: 50%;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  box-shadow: var(--shadow-soft);
  cursor: pointer;
  transform: translateY(-50%);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s ease, background-color 0.2s ease;
}

.carousel-row:hover .carousel-row__button,
.carousel-row:focus-within .carousel-row__button {
  opacity: 1;
  pointer-events: auto;
}

.carousel-row__button:hover {
  background: var(--button-control-hover);
}

.carousel-row__button--left {
  left: 0;
}

.carousel-row__button--right {
  right: 0;
}

.carousel-row__icon {
  display: block;
  font-size: 1.2rem;
}

.home-page :deep(.section-header) {
  padding-left: 28px;
}
</style>
