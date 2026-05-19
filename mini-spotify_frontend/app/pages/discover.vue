<template>
  <section class="discover-page">
    <div class="discover-page__header">
      <PageSectionHeader
        :title="pageTitle"
        :subtitle="pageSubtitle"
      />
    </div>

    <FormNotice v-if="errorMessage" variant="error" :message="errorMessage" />
    <PageState v-else-if="loading" :message="tl('Building recommendations...')" min-height="180px" />
    <PageState
      v-else-if="!selectedGenre"
      :title="tl('Choose a genre first')"
      :message="tl('Open Discover new genre from the sidebar and pick a genre to build recommendations.')"
      min-height="180px"
    />
    <PageState
      v-else-if="!recommendations.length"
      :title="tl('Nothing found')"
      :message="tl('No recommendations available for “{genre}”.', { genre: selectedGenre })"
      min-height="180px"
    />

    <CollectionTracksTable v-else :has-rows="recommendations.length > 0" class="discover-page__table">
      <template #header>
        <h2>{{ tl('Recommended tracks') }}</h2>
        <span>{{ formatTrackCount(recommendations.length) }}</span>
      </template>

      <template #head>
        <th>#</th>
        <th>{{ tl('Title') }}</th>
        <th>{{ tl('Artist') }}</th>
        <th>{{ tl('Album') }}</th>
        <th class="discover-page__th-duration">{{ tl('Duration') }}</th>
      </template>

      <template #body>
        <tr
          v-for="(track, index) in recommendations"
          :key="track.id"
          class="discover-page__row"
          :class="{ active: currentTrack?.id === track.id }"
          @click="playRecommendation(track)"
          @contextmenu.prevent="openTrackContextMenu($event, track)"
        >
          <td>
            <span class="track-marker">
              <span class="track-index">{{ index + 1 }}</span>
              <Icon :icon="getIcon('material-symbols:play-arrow-rounded')" class="track-play-icon" />
            </span>
          </td>
          <td class="discover-page__track-cell">
            <ArtworkCover
              class="discover-page__cover"
              :src="track.album?.cover_image_url || mediaUrl(track.album?.cover_image_path)"
              :alt="track.title"
              fallback-icon="solar:music-notes-bold"
              fallback-variant="playlist"
            />
            <div class="discover-page__track-copy">
              <strong>{{ track.title }}</strong>
            </div>
          </td>
          <td>
            <button
              type="button"
              class="hero-link"
              :disabled="!track.artist?.id"
              @click.stop="openArtist(track)"
            >
              {{ track.artist?.name || tl('Artist') }}
            </button>
          </td>
          <td>
            <button
              type="button"
              class="hero-link"
              :disabled="!track.artist?.id || !track.album?.id"
              @click.stop="openAlbum(track)"
            >
              {{ track.album?.title || tl('Single') }}
            </button>
          </td>
          <td class="discover-page__td-duration">{{ formatDuration(track.duration) }}</td>
        </tr>
      </template>
    </CollectionTracksTable>

    <TrackContextMenu
      :visible="isTrackMenuOpen"
      :x="trackMenuPosition.x"
      :y="trackMenuPosition.y"
      :items="trackMenuItems"
      @close="closeTrackContextMenu"
    />
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import type { Playlist, PlaylistSong } from '~/composables/usePlaylist'
import type { AudioPlayerTrack } from '~/composables/useAudioPlayer'
import { formatDuration } from '~/utils/audioDuration'
import { useAppIcons } from '~/composables/useAppIcons'
const { tl } = useLocalizedText()
const { isRussian } = useAppLocale()

const route = useRoute()
const router = useRouter()
const { getIcon } = useAppIcons()
const { mediaUrl } = useMediaUrl()
const { getRecommendations } = useRecommendations()
const { getTrackStreamUrl } = useAlbum()
const { getCurrentUser } = useAuth()
const { getMyPlaylists, addSongToPlaylist } = usePlaylist()
const { favoritesPlaylistId, refresh: refreshLiked, isLiked, toggle: toggleLikedTrack } = useLikedSongs()
const { prepareShare } = useMessenger()
const {
  currentTrack,
  playTrack,
  setQueue,
  addToQueue,
} = useAudioPlayer()

const loading = ref(false)
const errorMessage = ref('')
const currentUser = ref<{ uid?: string } | null>(null)
const myPlaylists = ref<Playlist[]>([])
const recommendations = ref<PlaylistSong[]>([])
const selectedTrack = ref<PlaylistSong | null>(null)
const trackMenuPosition = ref({ x: 0, y: 0 })

const selectedGenre = computed(() => String(route.query.genre || '').trim())
const pageTitle = computed(() => isRussian.value ? 'Откройте для себя новый жанр' : 'Discover a new genre')
const pageSubtitle = computed(() =>
  selectedGenre.value
    ? tl('5 recommendations for {genre}, based on your liked songs', { genre: selectedGenre.value })
    : tl('Pick a genre from the sidebar flow to see recommended tracks')
)
const formatTrackCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? '1 track' : `${count} tracks`
  }
  if (count % 10 === 1 && count % 100 !== 11) {
    return `${count} трек`
  }
  if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) {
    return `${count} трека`
  }
  return `${count} треков`
}
const isTrackMenuOpen = computed(() => !!selectedTrack.value)
const availablePlaylists = computed(() =>
  myPlaylists.value.filter((playlistItem) => playlistItem.id !== favoritesPlaylistId.value)
)

const mapRecommendationToPlayerTrack = (track: PlaylistSong): AudioPlayerTrack => ({
  id: track.id,
  title: track.title,
  duration: track.duration ?? null,
  playCount: track.play_count ?? null,
  artistName: track.artist?.name || 'Artist',
  artistId: track.artist?.id,
  albumTitle: track.album?.title,
  albumId: track.album?.id,
  coverUrl: track.album?.cover_image_url || mediaUrl(track.album?.cover_image_path),
  resolveStreamUrl: () => getTrackStreamUrl(track.id),
})

const loadRecommendations = async () => {
  errorMessage.value = ''

  if (!selectedGenre.value) {
    recommendations.value = []
    return
  }

  loading.value = true

  try {
    recommendations.value = await getRecommendations(selectedGenre.value, 5)
  } catch (error: any) {
    recommendations.value = []
    errorMessage.value = error?.data?.message || tl('Could not load recommendations')
  } finally {
    loading.value = false
  }
}

const loadContextState = async () => {
  try {
    currentUser.value = await getCurrentUser()
  } catch {
    currentUser.value = null
  }

  try {
    await refreshLiked()
    myPlaylists.value = await getMyPlaylists()
  } catch (error) {
    console.error(error)
    myPlaylists.value = []
  }
}

const playRecommendation = async (track: PlaylistSong) => {
  const queueTracks = recommendations.value.map(mapRecommendationToPlayerTrack)
  const nextTrack = queueTracks.find((item) => item.id === track.id)

  if (!nextTrack) {
    return
  }

  setQueue(queueTracks, nextTrack.id)
  await playTrack(nextTrack)
}

const openArtist = async (track: PlaylistSong) => {
  if (!track.artist?.id) {
    return
  }

  await router.push(`/albums/${track.artist.id}`)
}

const openAlbum = async (track: PlaylistSong) => {
  if (!track.artist?.id || !track.album?.id) {
    return
  }

  await router.push(`/albums/${track.artist.id}/${track.album.id}`)
}

const closeTrackContextMenu = () => {
  selectedTrack.value = null
}

const openTrackContextMenu = (event: MouseEvent, track: PlaylistSong) => {
  trackMenuPosition.value = { x: event.clientX, y: event.clientY }
  selectedTrack.value = track
}

const trackMenuItems = computed(() => {
  const track = selectedTrack.value

  if (!track) {
    return []
  }

  const playlistChildren = currentUser.value
    ? availablePlaylists.value.length
      ? availablePlaylists.value.map((playlistItem) => ({
          key: `playlist-${playlistItem.id}`,
          label: playlistItem.title,
          action: async () => {
            const result = await addSongToPlaylist(playlistItem.id, track.id)
            if (!result.success) {
              errorMessage.value = result.message || 'Could not add track'
            }
          },
        }))
      : [{
          key: 'playlist-empty',
          label: 'No playlists available',
          disabled: true,
        }]
    : [{
        key: 'playlist-login',
        label: 'Login required',
        disabled: true,
      }]

  return [
    {
      key: 'add-to-playlist',
      label: 'Add to playlist',
      icon: 'material-symbols:add-rounded',
      children: playlistChildren,
    },
    {
      key: 'toggle-liked',
      label: isLiked(track.id) ? 'Remove from your Liked Songs' : 'Save to your Liked Songs',
      icon: 'material-symbols:favorite',
      disabled: !currentUser.value,
      action: async () => {
        const result = await toggleLikedTrack(track.id)
        if (!result.ok) {
          errorMessage.value = result.message || 'Could not update liked songs'
        }
      },
    },
    {
      key: 'add-to-queue',
      label: 'Add to queue',
      icon: 'material-symbols:queue-music-rounded',
      action: () => addToQueue(mapRecommendationToPlayerTrack(track)),
    },
    {
      key: 'share-track',
      label: 'Share to user',
      icon: 'solar:share-linear',
      action: async () => {
        await prepareShare({
          type: 'track',
          id: track.id,
          title: track.title,
          subtitle: track.artist?.name || 'Artist',
          image_url: track.album?.cover_image_url || mediaUrl(track.album?.cover_image_path) || '',
        })
      },
    },
    {
      key: 'separator-navigation',
      separator: true,
    },
    {
      key: 'go-to-artist',
      label: 'Go to artist',
      icon: 'material-symbols:person-rounded',
      disabled: !track.artist?.id,
      action: async () => {
        await openArtist(track)
      },
    },
    {
      key: 'go-to-album',
      label: 'Go to album',
      icon: 'solar:album-bold',
      disabled: !track.artist?.id || !track.album?.id,
      action: async () => {
        await openAlbum(track)
      },
    },
  ]
})

onMounted(() => {
  void loadContextState()
})

watch(
  () => route.query.genre,
  () => {
    void loadRecommendations()
  },
  { immediate: true }
)
</script>

<style scoped>
.discover-page {
  min-height: 100%;
  display: grid;
  gap: 18px;
}

.discover-page__table :deep(.collection-tracks) {
  width: min(720px, calc(100% - 96px));
}

.discover-page__th-duration,
.discover-page__td-duration {
  width: 84px;
  text-align: right;
  white-space: nowrap;
}

:deep(.tracks-table) {
  table-layout: fixed;
}

:deep(.tracks-table th),
:deep(.tracks-table td) {
  padding: 10px 12px;
}

:deep(.tracks-table th:first-child),
:deep(.tracks-table td:first-child) {
  width: 42px;
}

:deep(.tracks-table th:nth-child(2)),
:deep(.tracks-table td:nth-child(2)) {
  width: 280px;
}

:deep(.tracks-table th:nth-child(3)),
:deep(.tracks-table td:nth-child(3)) {
  width: 170px;
}

:deep(.tracks-table th:nth-child(4)),
:deep(.tracks-table td:nth-child(4)) {
  width: 150px;
}

.discover-page__track-cell {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr);
  gap: 10px;
  align-items: center;
}

.discover-page__cover {
  width: 38px;
  height: 38px;
  border-radius: 8px;
}

.discover-page__track-copy {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.discover-page__track-copy strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.95rem;
}

.discover-page__row {
  cursor: pointer;
}

:deep(.tracks-table tbody tr) {
  border-bottom: 1px solid var(--color-border);
}

.discover-page__row:hover {
  background: var(--color-row-hover);
}

.discover-page__row.active {
  background: var(--color-row-active);
  box-shadow: inset 3px 0 0 var(--color-row-active-border);
}

.hero-link {
  padding: 0;
  border: 0;
  color: inherit;
  background: transparent;
  font: inherit;
  cursor: pointer;
}

.hero-link:hover:not(:disabled) {
  text-decoration: underline;
}

.hero-link:disabled {
  cursor: default;
}

.track-index {
  color: var(--color-text-soft);
}

.track-marker {
  position: relative;
  width: 16px;
  height: 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.track-play-icon {
  position: absolute;
  inset: 50% auto auto 50%;
  transform: translate(-50%, -50%);
  color: var(--color-primary);
  font-size: 1rem;
  line-height: 1;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.discover-page__row:hover .track-index {
  opacity: 0;
}

.discover-page__row:hover .track-play-icon {
  opacity: 1;
}

@media (max-width: 760px) {
  .discover-page__table :deep(.collection-tracks) {
    width: calc(100% - 24px);
    margin-left: 16px;
    margin-right: 16px;
  }

  :deep(.tracks-table th),
  :deep(.tracks-table td) {
    padding: 9px 10px;
  }

  .discover-page__track-cell {
    grid-template-columns: 34px minmax(0, 1fr);
    gap: 8px;
  }

  .discover-page__cover {
    width: 34px;
    height: 34px;
  }
}
</style>
