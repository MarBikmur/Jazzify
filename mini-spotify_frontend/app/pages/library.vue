<template>
  <section class="library-page">
    <PageState v-if="loading" :message="tl('Loading library...')" min-height="240px" />
    <PageState v-else-if="errorMessage" variant="error" :message="errorMessage" min-height="240px" />

    <template v-else>
      <PageSectionHeader :title="tl('Your library')" :subtitle="librarySubtitle" />

      <section class="library-section">
        <PageSectionHeader :title="tl('Your playlists')" :count="ownPlaylists.length" />
        <div v-if="ownPlaylists.length" class="carousel-row" @mouseleave="clearRowFocus">
          <button class="carousel-row__button carousel-row__button--left" type="button" :aria-label="tl('Back')" @click="scrollRow(ownPlaylistsRowRef, 'left')">
            <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="carousel-row__icon" />
          </button>
          <div ref="ownPlaylistsRowRef" class="library-grid">
            <MediaTile
              v-for="playlist in ownPlaylists"
              :key="`own-playlist-${playlist.id}`"
              :to="`/playlists/${playlist.id}`"
              :title="playlist.title"
              :subtitle="playlistSubtitle(playlist)"
              :image-src="playlist.cover_image_url || mediaUrl(playlist.cover_image_path)"
              fallback-icon="solar:music-note-2-bold"
              fallback-variant="playlist"
            />
          </div>
          <button class="carousel-row__button carousel-row__button--right" type="button" :aria-label="tl('Forward')" @click="scrollRow(ownPlaylistsRowRef, 'right')">
            <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="carousel-row__icon" />
          </button>
        </div>
        <PageState
          v-else
          :message="tl('No playlists yet.')"
          min-height="120px"
        />
      </section>

      <PageState
        v-if="!hasSavedResults"
        :title="tl('Nothing saved yet')"
        :message="tl('Follow users, artists, albums or playlists to keep them here.')"
        min-height="180px"
      />

      <template v-else>
        <section v-if="users.length" class="library-section">
          <PageSectionHeader :title="tl('Users')" :count="users.length" />
          <div class="carousel-row" @mouseleave="clearRowFocus">
            <button class="carousel-row__button carousel-row__button--left" type="button" :aria-label="tl('Back')" @click="scrollRow(usersRowRef, 'left')">
              <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="carousel-row__icon" />
            </button>
            <div ref="usersRowRef" class="library-grid">
              <MediaTile
                v-for="libraryUser in users"
                :key="`user-${libraryUser.uid}`"
                :to="`/users/${libraryUser.uid}`"
                :title="libraryUser.name"
                :subtitle="userSubtitle(libraryUser)"
                :image-src="libraryUser.avatar_url || mediaUrl(libraryUser.avatar_path)"
                :fallback="libraryUser.name.slice(0, 1).toUpperCase()"
                shape="circle"
              >
                <FollowToggleButton
                  class="library-tile__action"
                  active
                  idle-label="Follow"
                  active-label="Following"
                  loading-label="Removing..."
                  :loading="busyUserUid === libraryUser.uid"
                  @click.prevent.stop="removeUser(libraryUser.uid)"
                />
              </MediaTile>
            </div>
            <button class="carousel-row__button carousel-row__button--right" type="button" :aria-label="tl('Forward')" @click="scrollRow(usersRowRef, 'right')">
              <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="carousel-row__icon" />
            </button>
          </div>
        </section>

        <section v-if="artists.length" class="library-section">
          <PageSectionHeader :title="tl('Artists')" :count="artists.length" />
          <div class="carousel-row carousel-row--artists" @mouseleave="clearRowFocus">
            <button class="carousel-row__button carousel-row__button--left" type="button" :aria-label="tl('Back')" @click="scrollRow(artistsRowRef, 'left')">
              <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="carousel-row__icon" />
            </button>
            <div ref="artistsRowRef" class="library-grid library-grid--artists">
              <MediaTile
                v-for="artist in artists"
                :key="`artist-${artist.id}`"
                class="artist-tile"
                :to="`/albums/${artist.id}`"
                :title="artist.name"
                :subtitle="tl('Artist')"
                :image-src="artist.image_url || mediaUrl(artist.image_path)"
                :fallback="artist.name.slice(0, 1).toUpperCase()"
                shape="circle"
              >
                <FollowToggleButton
                  class="library-tile__action"
                  active
                  idle-label="Follow"
                  active-label="Following"
                  loading-label="Removing..."
                  :loading="busyArtistId === artist.id"
                  @click.prevent.stop="removeArtist(artist.id)"
                />
              </MediaTile>
            </div>
            <button class="carousel-row__button carousel-row__button--right" type="button" :aria-label="tl('Forward')" @click="scrollRow(artistsRowRef, 'right')">
              <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="carousel-row__icon" />
            </button>
          </div>
        </section>

        <section v-if="albums.length" class="library-section">
          <PageSectionHeader :title="tl('Albums')" :count="albums.length" />
          <div class="carousel-row" @mouseleave="clearRowFocus">
            <button class="carousel-row__button carousel-row__button--left" type="button" :aria-label="tl('Back')" @click="scrollRow(albumsRowRef, 'left')">
              <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="carousel-row__icon" />
            </button>
            <div ref="albumsRowRef" class="library-grid">
              <MediaTile
                v-for="album in albums"
                :key="`album-${album.id}`"
                :to="album.artist?.id ? `/albums/${album.artist.id}/${album.id}` : ''"
                :title="album.title"
                :subtitle="album.artist?.name || tl('Artist')"
                :image-src="album.cover_image_url || mediaUrl(album.cover_image_path)"
                fallback="A"
              >
                <FollowToggleButton
                  class="library-tile__action"
                  active
                  idle-label="Follow"
                  active-label="Following"
                  loading-label="Removing..."
                  :loading="busyAlbumId === album.id"
                  @click.prevent.stop="removeAlbum(album.id)"
                />
              </MediaTile>
            </div>
            <button class="carousel-row__button carousel-row__button--right" type="button" :aria-label="tl('Forward')" @click="scrollRow(albumsRowRef, 'right')">
              <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="carousel-row__icon" />
            </button>
          </div>
        </section>

        <section v-if="playlists.length" class="library-section">
          <PageSectionHeader :title="tl('Playlists')" :count="playlists.length" />
          <div class="carousel-row" @mouseleave="clearRowFocus">
            <button class="carousel-row__button carousel-row__button--left" type="button" :aria-label="tl('Back')" @click="scrollRow(playlistsRowRef, 'left')">
              <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="carousel-row__icon" />
            </button>
            <div ref="playlistsRowRef" class="library-grid">
              <MediaTile
                v-for="playlist in playlists"
                :key="`playlist-${playlist.id}`"
                :to="`/playlists/${playlist.id}`"
                :title="playlist.title"
                :subtitle="playlistSubtitle(playlist)"
                :image-src="playlist.cover_image_url || mediaUrl(playlist.cover_image_path)"
                fallback-icon="solar:music-note-2-bold"
                fallback-variant="playlist"
              >
                <FollowToggleButton
                  class="library-tile__action"
                  active
                  idle-label="Follow"
                  active-label="Following"
                  loading-label="Removing..."
                  :loading="busyPlaylistId === playlist.id"
                  @click.prevent.stop="removePlaylist(playlist.id)"
                />
              </MediaTile>
            </div>
            <button class="carousel-row__button carousel-row__button--right" type="button" :aria-label="tl('Forward')" @click="scrollRow(playlistsRowRef, 'right')">
              <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="carousel-row__icon" />
            </button>
          </div>
        </section>
      </template>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import type { Album } from '~/composables/useAlbum'
import type { Artist } from '~/composables/useArtist'
import type { Playlist } from '~/composables/usePlaylist'
import type { PublicProfileUser } from '~/composables/useUserProfile'

const { isRussian } = useAppLocale()
const { tl } = useLocalizedText()
const { getIcon } = useAppIcons()
const { getCurrentUser } = useAuth()
const { getLibraryAlbums, removeAlbumFromLibrary, getLibraryPlaylists, removePlaylistFromLibrary, getLibraryArtists, unfollowArtist, getLibraryUsers, unfollowLibraryUser } =
  useLibrary()
const { getMyPlaylists } = usePlaylist()
const { mediaUrl } = useMediaUrl()

const loading = ref(true)
const errorMessage = ref('')
const ownPlaylists = ref<Playlist[]>([])
const users = ref<PublicProfileUser[]>([])
const artists = ref<Artist[]>([])
const albums = ref<Album[]>([])
const playlists = ref<Playlist[]>([])
const busyUserUid = ref<string | null>(null)
const busyArtistId = ref<number | null>(null)
const busyAlbumId = ref<number | null>(null)
const busyPlaylistId = ref<number | null>(null)
const ownPlaylistsRowRef = ref<HTMLElement | null>(null)
const usersRowRef = ref<HTMLElement | null>(null)
const artistsRowRef = ref<HTMLElement | null>(null)
const albumsRowRef = ref<HTMLElement | null>(null)
const playlistsRowRef = ref<HTMLElement | null>(null)

const pluralizeRu = (count: number, one: string, few: string, many: string) => {
  if (count % 10 === 1 && count % 100 !== 11) {
    return `${count} ${one}`
  }

  if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) {
    return `${count} ${few}`
  }

  return `${count} ${many}`
}

const formatItemsCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? '1 item' : `${count} items`
  }

  return pluralizeRu(count, 'элемент', 'элемента', 'элементов')
}

const formatFollowersCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? '1 follower' : `${count} followers`
  }

  return pluralizeRu(count, 'подписчик', 'подписчика', 'подписчиков')
}

const formatTracksCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? '1 track' : `${count} tracks`
  }

  return pluralizeRu(count, 'трек', 'трека', 'треков')
}

const hasSavedResults = computed(() => users.value.length > 0 || artists.value.length > 0 || albums.value.length > 0 || playlists.value.length > 0)
const librarySubtitle = computed(() => {
  const total = ownPlaylists.value.length + users.value.length + artists.value.length + albums.value.length + playlists.value.length
  return formatItemsCount(total)
})

const artistFollowersLabel = (artist: Artist) => {
  const count = artist.followers_count ?? 0
  return formatFollowersCount(count)
}

const playlistSubtitle = (playlist: Playlist) => {
  const count = playlist.songs_count ?? playlist.songs?.length ?? 0
  return formatTracksCount(count)
}

const userSubtitle = (user: PublicProfileUser) => {
  const followersCount = user.followers_count ?? 0
  return formatFollowersCount(followersCount)
}

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

const removeUser = async (uid: string) => {
  if (busyUserUid.value === uid) {
    return
  }

  busyUserUid.value = uid

  try {
    const response = await unfollowLibraryUser(uid)
    if (!response.success) {
      errorMessage.value = response.message || tl('Could not remove user')
      return
    }

    users.value = users.value.filter((user) => user.uid !== uid)
  } finally {
    busyUserUid.value = null
  }
}

const removeArtist = async (artistId: number) => {
  if (busyArtistId.value === artistId) {
    return
  }

  busyArtistId.value = artistId

  try {
    const response = await unfollowArtist(artistId)
    if (!response.success) {
      errorMessage.value = response.message || tl('Could not remove artist')
      return
    }

    artists.value = artists.value.filter((artist) => artist.id !== artistId)
  } finally {
    busyArtistId.value = null
  }
}

const removeAlbum = async (albumId: number) => {
  if (busyAlbumId.value === albumId) {
    return
  }

  busyAlbumId.value = albumId

  try {
    const response = await removeAlbumFromLibrary(albumId)
    if (!response.success) {
      errorMessage.value = response.message || tl('Could not remove album')
      return
    }

    albums.value = albums.value.filter((album) => album.id !== albumId)
  } finally {
    busyAlbumId.value = null
  }
}

const removePlaylist = async (playlistId: number) => {
  if (busyPlaylistId.value === playlistId) {
    return
  }

  busyPlaylistId.value = playlistId

  try {
    const response = await removePlaylistFromLibrary(playlistId)
    if (!response.success) {
      errorMessage.value = response.message || tl('Could not remove playlist')
      return
    }

    playlists.value = playlists.value.filter((playlist) => playlist.id !== playlistId)
  } finally {
    busyPlaylistId.value = null
  }
}

onMounted(async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const user = await getCurrentUser()
    if (!user) {
      await navigateTo('/login')
      return
    }

    const [myPlaylistsData, usersData, artistsData, albumsData, playlistsData] = await Promise.all([
      getMyPlaylists(),
      getLibraryUsers(),
      getLibraryArtists(),
      getLibraryAlbums(),
      getLibraryPlaylists(),
    ])

    ownPlaylists.value = myPlaylistsData
    users.value = usersData
    artists.value = artistsData
    albums.value = albumsData
    playlists.value = playlistsData
  } catch (error: any) {
    console.error('Library loading error:', error)
    errorMessage.value = error?.data?.message || tl('Failed to load library')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.library-page {
  min-height: 100%;
  display: grid;
  gap: 24px;
}

.library-section {
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

.library-grid {
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

.library-tile__action {
  margin-top: 4px;
  width: fit-content;
  justify-self: center;
  align-self: center;
}

.library-grid::-webkit-scrollbar {
  display: none;
}

.library-grid > * {
  width: 180px;
  min-width: 180px;
  max-width: 180px;
}

.carousel-row--artists,
.carousel-row--artists .library-grid,
.carousel-row--artists .library-grid--artists {
  background: transparent !important;
}

.library-grid--artists {
  grid-auto-columns: minmax(180px, 180px);
}

.library-grid--artists > * {
  width: 180px;
  min-width: 180px;
  max-width: 180px;
}

.library-page :deep(.section-header) {
  padding-left: 28px;
}
</style>
