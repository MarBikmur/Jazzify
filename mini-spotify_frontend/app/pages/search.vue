<template>
  <section class="search-page">
    <PageSectionHeader :title="pageTitle" />

    <PageState
      v-if="!normalizedQuery"
      :title="tl('Search')"
      :message="tl('Enter a query in the top search bar to find music.')"
      min-height="180px"
    />
    <PageState v-else-if="loading" message="Searching..." min-height="180px" />
    <FormNotice v-else-if="errorMessage" variant="error" :message="errorMessage" />
    <PageState
      v-else-if="!hasResults"
      :title="tl('Nothing found')"
      :message="tl('No tracks, albums, playlists, artists, users or genres found for “{query}”.', { query: normalizedQuery })"
      min-height="180px"
    />

    <template v-else>
      <section v-if="results.tracks.length" class="search-section">
        <PageSectionHeader :title="tl('Tracks')" :count="results.tracks.length" />
        <div class="tracks-list">
          <div
            v-for="track in results.tracks"
            :key="`track-${track.id}`"
            class="track-result"
            role="button"
            tabindex="0"
            @click="openTrackResult(track)"
            @keydown.enter.prevent="openTrackResult(track)"
            @keydown.space.prevent="openTrackResult(track)"
          >
            <span class="track-result__art-shell">
              <ArtworkCover
                class="track-result__cover"
                :src="trackCover(track)"
                :alt="track.title"
                fallback-icon="solar:music-notes-bold"
                fallback-variant="playlist"
              />
              <button
                class="track-result__play"
                type="button"
                :aria-label="tl('Play {title}', { title: track.title })"
                @click.stop="playTrackFromSearch(track)"
              >
                <Icon icon="material-symbols:play-arrow-rounded" />
              </button>
            </span>
            <span class="track-result__copy">
              <strong>{{ track.title }}</strong>
              <span>{{ track.artist?.name || tl('Artist') }}</span>
            </span>
            <button
              class="track-result__like"
              :class="{ 'track-result__like--liked': isLiked(track.id) }"
              type="button"
              :disabled="likeBusyId === track.id"
              :aria-pressed="isLiked(track.id)"
              :aria-label="tl('Toggle liked song')"
              @click.stop="toggleTrackLike(track)"
            >
              <Icon icon="material-symbols:favorite" class="track-result__like-icon" />
            </button>
            <span class="track-result__time">{{ formatTrackDuration(track) }}</span>
          </div>
        </div>
      </section>

      <section v-if="results.albums.length" class="search-section">
        <PageSectionHeader :title="tl('Albums')" :count="results.albums.length" />
        <div class="search-grid">
          <MediaTile
            v-for="album in results.albums"
            :key="`album-${album.id}`"
            :to="album.artist?.id ? `/albums/${album.artist.id}/${album.id}` : ''"
            :title="album.title"
            :subtitle="album.artist?.name || tl('Artist')"
            :image-src="albumCover(album)"
            fallback="A"
          />
        </div>
      </section>

      <section v-if="visiblePlaylists.length" class="search-section">
        <PageSectionHeader :title="tl('Playlists')" :count="visiblePlaylists.length" />
        <div class="search-grid">
          <MediaTile
            v-for="playlist in visiblePlaylists"
            :key="`playlist-${playlist.id}`"
            :to="`/playlists/${playlist.id}`"
            :title="tl(playlist.title)"
            :subtitle="playlistTrackSubtitle(playlist)"
            :image-src="playlistCover(playlist)"
            fallback-icon="solar:music-note-2-bold"
            fallback-variant="playlist"
          />
        </div>
      </section>

      <section v-if="results.artists.length" class="search-section">
        <PageSectionHeader :title="tl('Artists')" :count="results.artists.length" />
        <div class="search-grid">
          <MediaTile
            v-for="artist in results.artists"
            :key="`artist-${artist.id}`"
            :to="`/albums/${artist.id}`"
            :title="artist.name"
            :subtitle="tl('Artist')"
            :image-src="artistAvatar(artist)"
            :fallback="artist.name.slice(0, 1).toUpperCase()"
            shape="circle"
          />
        </div>
      </section>

      <section v-if="results.users.length" class="search-section">
        <PageSectionHeader :title="tl('Users')" :count="results.users.length" />
        <div class="search-grid">
          <MediaTile
            v-for="resultUser in results.users"
            :key="`user-${resultUser.uid}`"
            :to="`/users/${resultUser.uid}`"
            :title="resultUser.name"
            :subtitle="userSubtitle(resultUser)"
            :image-src="resultUser.avatar_url || mediaUrl(resultUser.avatar_path)"
            :fallback="resultUser.name.slice(0, 1).toUpperCase()"
            shape="circle"
          >
            <FollowToggleButton
              v-if="canFollowUser(resultUser)"
              class="search-user__action"
              :active="!!resultUser.is_following"
              :loading="userFollowBusyUid === resultUser.uid"
              loading-label="Saving..."
              @click.prevent.stop="toggleUserFollow(resultUser.uid)"
            />
          </MediaTile>
        </div>
      </section>

      <section v-if="results.genres.length" class="search-section">
        <PageSectionHeader :title="tl('Genres')" :count="results.genres.length" />
        <div class="genres-grid">
          <NuxtLink
            v-for="genre in results.genres"
            :key="`genre-${genre.id}`"
            class="genre-card"
            :to="`/genres/${encodeURIComponent(genre.name)}`"
          >
            <span class="genre-card__eyebrow">{{ tl('Genre') }}</span>
            <strong>{{ genre.name }}</strong>
          </NuxtLink>
        </div>
      </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { FAVORITES_PLAYLIST_TITLE } from '~/composables/useLikedSongs'
import type { Album } from '~/composables/useAlbum'
import type { Artist } from '~/composables/useArtist'
import type { Playlist, PlaylistSong } from '~/composables/usePlaylist'
import type { SearchResults } from '~/composables/useSearch'
import type { PublicProfileUser } from '~/composables/useUserProfile'
import { formatDuration } from '~/utils/audioDuration'

const route = useRoute()
const { isRussian } = useAppLocale()
const { tl } = useLocalizedText()
const { mediaUrl } = useMediaUrl()
const { search, normalizeQuery } = useSearch()
const { refresh: refreshLiked, isLiked, toggle: toggleLikedTrack } = useLikedSongs()
const { getCurrentUser } = useAuth()
const { followUser, unfollowUser } = useUserSocial()

const loading = ref(false)
const errorMessage = ref('')
const likeBusyId = ref<number | null>(null)
const userFollowBusyUid = ref<string | null>(null)
const currentUserUid = ref('')
const results = ref<SearchResults>({
  tracks: [],
  albums: [],
  playlists: [],
  artists: [],
  users: [],
  genres: [],
})

const normalizedQuery = computed(() => normalizeQuery(String(route.query.q || '')))
const pageTitle = computed(() =>
  normalizedQuery.value
    ? tl('Search results for "{query}"', { query: normalizedQuery.value })
    : tl('Search results')
)
const visiblePlaylists = computed(() =>
  results.value.playlists.filter((playlist) => playlist.title !== FAVORITES_PLAYLIST_TITLE)
)
const hasResults = computed(
  () =>
    results.value.tracks.length > 0
    || results.value.albums.length > 0
    || visiblePlaylists.value.length > 0
    || results.value.artists.length > 0
    || results.value.users.length > 0
    || results.value.genres.length > 0
)

const albumCoverMap = computed(() => {
  const map = new Map<number, string>()

  results.value.albums.forEach((album) => {
    map.set(album.id, albumCover(album))
  })

  return map
})

const albumCoverById = (albumId: number) => albumCoverMap.value.get(albumId) || ''
const trackCover = (track: PlaylistSong) => {
  if (track.album?.cover_image_url || track.album?.cover_image_path) {
    return track.album.cover_image_url || mediaUrl(track.album.cover_image_path)
  }

  if (track.album?.id) {
    return albumCoverById(track.album.id)
  }

  return ''
}
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

const formatFollowersCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? '1 follower' : `${count} followers`
  }

  return pluralizeRu(count, 'подписчик', 'подписчика', 'подписчиков')
}

const openTrackResult = async (track: PlaylistSong) => {
  if (track.artist?.id && track.album?.id) {
    await navigateTo(`/albums/${track.artist.id}/${track.album.id}`)
    return
  }

  if (track.artist?.id) {
    await navigateTo(`/albums/${track.artist.id}`)
  }
}

const playTrackFromSearch = async (track: PlaylistSong) => {
  if (track.artist?.id && track.album?.id) {
    await navigateTo({
      path: `/albums/${track.artist.id}/${track.album.id}`,
      query: {
        autoplay: '1',
        track: String(track.id),
      },
    })
    return
  }

  await openTrackResult(track)
}

const toggleTrackLike = async (track: PlaylistSong) => {
  if (!track.id || likeBusyId.value === track.id) {
    return
  }

  likeBusyId.value = track.id

  try {
    await toggleLikedTrack(track.id)
  } finally {
    likeBusyId.value = null
  }
}

const formatTrackDuration = (track: PlaylistSong) => formatDuration(track.duration)

const artistAvatar = (artist: Artist) => artist.image_url || mediaUrl(artist.image_path)
const albumCover = (album: Album) => album.cover_image_url || mediaUrl(album.cover_image_path)
const playlistCover = (playlist: Playlist) => playlist.cover_image_url || mediaUrl(playlist.cover_image_path)
const playlistTrackSubtitle = (playlist: Playlist) => formatTracksCount(playlist.songs_count ?? playlist.songs?.length ?? 0)
const userSubtitle = (user: PublicProfileUser) => {
  const followersCount = user.followers_count ?? 0
  return formatFollowersCount(followersCount)
}
const canFollowUser = (user: PublicProfileUser) => !!user.uid && user.uid !== currentUserUid.value

const toggleUserFollow = async (uid: string) => {
  const targetUser = results.value.users.find((user) => user.uid === uid)
  if (!targetUser || userFollowBusyUid.value === uid) {
    return
  }

  userFollowBusyUid.value = uid

  try {
    const response = targetUser.is_following
      ? await unfollowUser(uid)
      : await followUser(uid)

    if (!response.success) {
      errorMessage.value = response.message || tl('Could not update follow state')
      return
    }

    results.value = {
      ...results.value,
      users: results.value.users.map((user) => user.uid === uid ? { ...user, ...(response.data || {}) } : user),
    }
  } finally {
    userFollowBusyUid.value = null
  }
}

const runSearch = async () => {
  const query = normalizedQuery.value
  errorMessage.value = ''

  if (!query) {
    results.value = {
      tracks: [],
      albums: [],
      playlists: [],
      artists: [],
      users: [],
      genres: [],
    }
    return
  }

  loading.value = true

  try {
    results.value = await search(query)
  } catch (error: any) {
    console.error('Search page error:', error)
    errorMessage.value = error?.data?.message || tl('Could not load search results')
  } finally {
    loading.value = false
  }
}

watch(
  () => route.query.q,
  () => {
    void runSearch()
  },
  { immediate: true }
)

onMounted(() => {
  void refreshLiked()
  void getCurrentUser().then((user) => {
    currentUserUid.value = user?.uid || ''
  })
})
</script>

<style scoped>
.search-page {
  min-height: 100%;
  display: grid;
  gap: 22px;
}

.search-section {
  display: grid;
  gap: 14px;
}

.search-grid,
.genres-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
}

.tracks-list {
  display: grid;
  gap: 2px;
}

.track-result {
  width: 100%;
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr) 32px auto;
  gap: 14px;
  align-items: center;
  padding: 10px 8px;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: var(--color-text-main);
  text-align: left;
  font: inherit;
  cursor: pointer;
  transition: background-color 0.2s ease, transform 0.2s ease;
}

.track-result:hover,
.track-result:focus-visible {
  background: var(--color-library-item-hover);
  outline: none;
}

.track-result__cover {
  width: 54px;
  height: 54px;
  border-radius: 8px;
}

.track-result__art-shell {
  position: relative;
  width: 54px;
  height: 54px;
  display: block;
}

.track-result:hover .track-result__cover,
.track-result:focus-visible .track-result__cover {
  filter: brightness(0.6);
}

.track-result__play {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: 1;
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--button-primary-border);
  border-radius: 50%;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  box-shadow: var(--shadow-primary);
  opacity: 0;
  pointer-events: none;
  transform: translate(-50%, -50%) scale(0.92);
  transition: opacity 0.15s ease, transform 0.15s ease;
  cursor: pointer;
}

.track-result:hover .track-result__play,
.track-result:focus-within .track-result__play,
.track-result__play:focus-visible {
  opacity: 1;
  pointer-events: auto;
  transform: translate(-50%, -50%) scale(1);
}

.track-result__copy {
  min-width: 0;
  display: grid;
  gap: 4px;
}

.track-result__copy strong,
.track-result__copy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.track-result__copy span {
  color: var(--color-text-muted);
}

.track-result__like {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 1px solid var(--button-control-border);
  border-radius: 50%;
  background: var(--button-control-bg);
  color: var(--color-text-soft);
  line-height: 0;
  cursor: pointer;
  opacity: 0;
  pointer-events: none;
  transition: color 0.15s ease, background 0.15s ease, border-color 0.15s ease, opacity 0.15s ease;
}

.track-result:hover .track-result__like,
.track-result:focus-within .track-result__like {
  opacity: 1;
  pointer-events: auto;
}

.track-result__like--liked,
.track-result__like--liked:hover {
  opacity: 1;
  pointer-events: auto;
  color: var(--color-accent);
  background: var(--button-control-hover);
  border-color: var(--color-border-strong);
}

.track-result__like:hover:not(:disabled) {
  color: var(--color-accent);
  background: var(--button-control-hover);
  border-color: var(--color-border-strong);
}

.track-result__like:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.track-result__like-icon {
  font-size: 1.2rem;
  line-height: 1;
  transform: translateY(0.5px);
}

.track-result__time {
  min-width: 34px;
  color: var(--color-text-soft);
  font-size: 0.95rem;
  font-variant-numeric: tabular-nums;
  text-align: right;
}

.genre-card {
  display: grid;
  gap: 6px;
  min-height: 128px;
  padding: 18px;
  border-radius: var(--radius-card);
  border: 1px solid var(--color-card-border);
  background: var(--color-card-surface);
  color: var(--color-text-main);
  box-shadow: var(--color-card-shadow);
  text-decoration: none;
  transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.genre-card:hover {
  background: var(--color-card-surface-hover);
  border-color: var(--color-border-strong);
  transform: translateY(-2px);
}

.genre-card__eyebrow {
  color: var(--color-accent);
  font-size: 0.76rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.genre-card strong {
  font-size: 1.18rem;
  line-height: 1.2;
}

.search-user__action {
  margin-top: 4px;
  width: fit-content;
  justify-self: center;
  align-self: center;
}

@media (max-width: 760px) {
  .search-grid,
  .genres-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .track-result__like,
  .track-result:hover .track-result__like {
    opacity: 0.85;
    pointer-events: auto;
  }
}
</style>
