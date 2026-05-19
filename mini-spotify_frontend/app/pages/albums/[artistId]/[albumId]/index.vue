<template>
  <CollectionDetailTemplate
    :loading="loading"
    :error-message="errorMessage"
    :loading-message="tl('Loading album...')"
    :back-to="`/albums/${route.params.artistId}`"
    :back-label="tl('Back to albums')"
    :type-label="tl('Album')"
    :title="album?.title || ''"
    :subtitle="albumSubtitle"
    :image-src="album ? albumCover(album) : ''"
    fallback="A"
  >
    <template #hero-subtitle>
      <p v-if="album">
          <button
            v-if="album.artist?.id"
            type="button"
            class="hero-link"
            @click="openAlbumArtist"
          >
            {{ album.artist?.name || tl('Artist') }}
          </button>
          <template v-else>{{ album.artist?.name || tl('Artist') }}</template>
          <span>• {{ formatTracksCount(album?.songs?.length || 0) }}</span>
      </p>
    </template>

    <template #back-actions>
      <div v-if="isOwner || isAdmin" class="owner-actions">
        <NuxtLink
          class="owner-action-button"
          :to="isOwner ? `/albums/${route.params.artistId}/${route.params.albumId}/edit` : `/admin/albums/${album?.id}`"
        >
          {{ tl('Edit') }}
        </NuxtLink>
        <button
          v-if="isOwner"
          type="button"
          class="owner-action-button owner-action-button--danger"
          :disabled="isDeletingAlbum"
          @click="deleteCurrentAlbum"
        >
          {{ tl('Delete') }}
        </button>
      </div>
    </template>

    <template #hero-title>
      <h1
        v-if="!isOwner || !editingTitle"
        class="editable-title"
        :class="{ 'editable-title--interactive': isOwner }"
        @dblclick="startTitleEdit"
      >
        {{ album?.title }}
      </h1>
      <input
        v-else
        ref="titleInput"
        v-model="titleDraft"
        class="title-input"
        type="text"
        maxlength="255"
        :aria-label="tl('Album title')"
        @blur="saveTitleEdit"
        @keydown.enter.prevent="saveTitleEdit"
        @keydown.esc.prevent="cancelTitleEdit"
      />
    </template>

    <template #actions-main>
      <ListenPillButton
        :label="isThisAlbumPlaying ? tl('Pause') : tl('Listen')"
        :disabled="isPreparingTrack || isPlayerLoading"
        @click="playFirstTrack"
      />
    </template>

    <template #actions-side>
      <div class="hero-side-actions">
        <FollowToggleButton
          v-if="canFollowAlbum"
          :active="!!album?.is_in_library"
          :loading="isFollowBusy"
          loading-label="Saving..."
          @click="toggleAlbumLibrary"
        />
        <button
          v-if="album"
          type="button"
          class="share-icon-button"
          :aria-label="tl('Share album')"
          @click="shareAlbum"
        >
          <Icon :icon="getIcon('solar:share-linear')" />
        </button>
      </div>
    </template>

    <template #notices>
      <FormNotice v-if="playbackError" variant="error" :message="playbackError" />
      <FormNotice v-if="formError" variant="error" :message="formError" />
    </template>

    <template #table-section>
      <CollectionTracksTable :has-rows="(album?.songs || []).length > 0">
        <template #header>
          <h2>{{ tl('Tracks') }}</h2>
        </template>

        <template #head>
          <th>#</th>
          <th>{{ tl('Title') }}</th>
          <th>{{ tl('Genre') }}</th>
          <th class="tracks-table__th-duration">{{ tl('Duration') }}</th>
          <th class="tracks-table__th-like" />
        </template>

        <template #body>
          <tr
            v-for="(track, index) in album?.songs || []"
            :key="track.id"
            class="track-row"
            :class="{ active: playerTrack?.id === track.id }"
            @click="playTrack(track)"
            @contextmenu.prevent="openTrackContextMenu($event, track)"
          >
            <td>
              <span class="track-marker">
                <span class="track-index">{{ index + 1 }}</span>
                <Icon :icon="getIcon('material-symbols:play-arrow-rounded')" class="track-play-icon" />
              </span>
            </td>
            <td>
              <strong>{{ track.title }}</strong>
              <span>{{ album?.artist?.name || tl('Artist') }}</span>
            </td>
            <td>{{ track.genre?.name || '-' }}</td>
            <td class="tracks-table__td-duration">{{ formatDuration(track.duration) }}</td>
            <td class="track-like-cell" @click.stop>
              <button
                v-if="currentUser"
                class="track-like-btn"
                :class="{ 'track-like-btn--liked': isLiked(track.id) }"
                type="button"
                :disabled="likeBusyId === track.id"
                :aria-pressed="isLiked(track.id)"
                :aria-label="'В любимые: ' + track.title"
                @click="likeAlbumTrack(track)"
              >
                <Icon :icon="getIcon('material-symbols:favorite')" class="track-like-btn__icon" />
              </button>
            </td>
          </tr>
        </template>
      </CollectionTracksTable>
    </template>
  </CollectionDetailTemplate>
  <TrackContextMenu
    :visible="isTrackMenuOpen"
    :x="trackMenuPosition.x"
    :y="trackMenuPosition.y"
    :items="trackMenuItems"
    @close="closeTrackContextMenu"
  />
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { useAppIcons } from '~/composables/useAppIcons'
import { useLikedSongs } from '~/composables/useLikedSongs'
import { FAVORITES_PLAYLIST_TITLE } from '~/composables/useLikedSongs'
import type { Artist as ArtistEntity } from '~/composables/useArtist'
import type { Playlist } from '~/composables/usePlaylist'
import { formatDuration } from '~/utils/audioDuration'
import type { Album as AlbumEntity } from '~/composables/useAlbum'

interface Album extends AlbumEntity {
  id: number
  title: string
  cover_image_path?: string
  cover_image_url?: string
  artist?: {
    id: number
    name: string
    user_uid?: string
  }
  songs?: Track[]
}

interface Track {
  id: number
  title: string
  genre_id?: number
  audio_path?: string
  audio_url?: string
  duration?: number | null
  genre?: {
    id: number
    name: string
  }
}

const route = useRoute()
const { getCurrentUser } = useAuth()
const router = useRouter()
const { tl } = useLocalizedText()
const { getIcon } = useAppIcons()
const { getArtistAlbum, getTrackStreamUrl, updateAlbum, deleteAlbum } = useAlbum()
const { getCurrentArtist } = useArtist()
const { addAlbumToLibrary, removeAlbumFromLibrary } = useLibrary()
const { getMyPlaylists, addSongToPlaylist } = usePlaylist()
const { mediaUrl } = useMediaUrl()
const { favoritesPlaylistId, refresh: refreshLiked, isLiked, toggle: toggleLikedTrack } = useLikedSongs()
const { prepareShare } = useMessenger()

const album = ref<Album | null>(null)
const currentUser = ref<{ uid?: string; role?: string } | null>(null)
const currentArtist = ref<ArtistEntity | null>(null)
const titleInput = ref<HTMLInputElement | null>(null)
const loading = ref(false)
const errorMessage = ref('')
const isPreparingTrack = ref(false)
const streamingError = ref('')
const likeBusyId = ref<number | null>(null)
const myPlaylists = ref<Playlist[]>([])
const selectedTrack = ref<Track | null>(null)
const trackMenuPosition = ref({ x: 0, y: 0 })
const editingTitle = ref(false)
const titleDraft = ref('')
const formError = ref('')
const isDeletingAlbum = ref(false)
const isFollowBusy = ref(false)

const formatTracksCount = (count: number) => count === 1 ? `1 ${tl('track')}` : `${count} ${tl('tracks')}`

const {
  currentTrack: playerTrack,
  isPlaying,
  isLoading: isPlayerLoading,
  errorMessage: playerError,
  setQueue,
  addToQueue,
  playTrack: playInLayout,
  togglePlay,
} = useAudioPlayer()

const albumCover = (album: Album) => album.cover_image_url || mediaUrl(album.cover_image_path)
const albumSubtitle = computed(() => '')
const isOwner = computed(() => {
  return !!currentArtist.value?.id
    && currentArtist.value.id === album.value?.artist?.id
})
const isAdmin = computed(() => currentUser.value?.role === 'admin')
const canFollowAlbum = computed(() => !!currentUser.value?.uid && !isOwner.value)
const isThisAlbumTrack = computed(() => {
  const trackId = playerTrack.value?.id
  const currentAlbumId = album.value?.id

  if (!trackId || !currentAlbumId || !album.value?.songs?.length) {
    return false
  }

  if (playerTrack.value?.collectionType && playerTrack.value.collectionType !== 'album') {
    return false
  }

  if (playerTrack.value?.collectionType === 'album' && String(playerTrack.value.collectionId) !== String(currentAlbumId)) {
    return false
  }

  return album.value.songs.some((track) => track.id === trackId)
})
const isThisAlbumPlaying = computed(() => isThisAlbumTrack.value && isPlaying.value)
const playbackError = computed(() => streamingError.value || playerError.value)
const isTrackMenuOpen = computed(() => !!selectedTrack.value)
const availablePlaylists = computed(() =>
  myPlaylists.value.filter((playlist) => playlist.id !== favoritesPlaylistId.value)
)

const buildAlbumQueueEntry = (track: Track) => ({
  id: track.id,
  title: track.title,
  duration: track.duration ?? null,
  artistName: album.value?.artist?.name || tl('Artist'),
  artistId: album.value?.artist?.id,
  albumTitle: album.value?.title,
  albumId: album.value?.id,
  coverUrl: album.value ? albumCover(album.value) : '',
  collectionType: 'album' as const,
  collectionId: album.value?.id,
  resolveStreamUrl: () => getTrackStreamUrl(track.id),
})

const closeTrackContextMenu = () => {
  selectedTrack.value = null
}

const openTrackContextMenu = (event: MouseEvent, track: Track) => {
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
      ? availablePlaylists.value.map((playlist) => ({
          key: `playlist-${playlist.id}`,
          label: playlist.title,
          action: async () => {
            const result = await addSongToPlaylist(playlist.id, track.id)
            if (!result.success) {
              formError.value = result.message || tl('Could not add track')
            }
          },
        }))
      : [{
          key: 'playlist-empty',
          label: tl('No playlists available'),
          disabled: true,
        }]
    : [{
        key: 'playlist-login',
        label: tl('Login required'),
        disabled: true,
      }]

  return [
    {
      key: 'add-to-playlist',
      label: tl('Add to playlist'),
      icon: 'material-symbols:add-rounded',
      children: playlistChildren,
    },
    {
      key: 'toggle-liked',
      label: isLiked(track.id) ? tl('Remove from your Liked Songs') : tl('Save to your Liked Songs'),
      icon: 'material-symbols:favorite',
      disabled: !currentUser.value,
      action: async () => {
        const result = await toggleLikedTrack(track.id)
        if (!result.ok) {
          formError.value = result.message || tl('Could not update liked songs')
        }
      },
    },
    {
      key: 'add-to-queue',
      label: tl('Add to queue'),
      icon: 'material-symbols:queue-music-rounded',
      action: () => addToQueue(buildAlbumQueueEntry(track)),
    },
    {
      key: 'share-track',
      label: tl('Share to user'),
      icon: 'solar:share-linear',
      action: async () => {
        await prepareShare({
          type: 'track',
          id: track.id,
          title: track.title,
          subtitle: album.value?.artist?.name || tl('Artist'),
          image_url: album.value ? albumCover(album.value) : '',
        })
      },
    },
    {
      key: 'separator-navigation',
      separator: true,
    },
    {
      key: 'go-to-artist',
      label: tl('Go to artist'),
      icon: 'material-symbols:person-rounded',
      disabled: !album.value?.artist?.id,
      action: async () => {
        if (album.value?.artist?.id) {
          await router.push(`/albums/${album.value.artist.id}`)
        }
      },
    },
    {
      key: 'go-to-album',
      label: tl('Go to album'),
      icon: 'solar:album-bold',
      disabled: !album.value?.artist?.id || !album.value?.id,
      action: async () => {
        if (album.value?.artist?.id && album.value?.id) {
          await router.push(`/albums/${album.value.artist.id}/${album.value.id}`)
        }
      },
    },
  ]
})

const startTitleEdit = async () => {
  if (!isOwner.value || !album.value) {
    return
  }

  titleDraft.value = album.value.title
  editingTitle.value = true
  await nextTick()
  titleInput.value?.focus()
  titleInput.value?.select()
}

const cancelTitleEdit = () => {
  editingTitle.value = false
  titleDraft.value = album.value?.title || ''
}

const saveTitleEdit = async () => {
  if (!album.value || !isOwner.value || !editingTitle.value) {
    return
  }

  const nextTitle = titleDraft.value.trim()

  if (!nextTitle || nextTitle === album.value.title) {
    cancelTitleEdit()
    return
  }

  formError.value = ''

  const response = await updateAlbum(album.value.id, {
    title: nextTitle,
  })

  if (!response.success) {
    formError.value = response.message || tl('Failed to rename album')
    return
  }

  album.value = {
    ...album.value,
    ...(response.data || {}),
    title: response.data?.title || nextTitle,
  }
  editingTitle.value = false
}

const playFirstTrack = async () => {
  const isSameAlbumTrack =
    isThisAlbumTrack.value
    && playerTrack.value?.collectionType === 'album'
    && String(playerTrack.value?.collectionId) === String(album.value?.id)

  if (isSameAlbumTrack && playerTrack.value) {
    await togglePlay()
    return
  }

  const firstTrack = album.value?.songs?.[0]

  if (firstTrack) {
    await playTrack(firstTrack)
  }
}

const autoplayFromQueryIfNeeded = async () => {
  if (route.query.autoplay !== '1' || loading.value || !album.value?.songs?.length || isPreparingTrack.value) {
    return
  }

  const requestedTrackId = Number(route.query.track)
  const selectedTrack = Number.isFinite(requestedTrackId)
    ? album.value.songs.find((track) => track.id === requestedTrackId)
    : null

  if (selectedTrack) {
    await playTrack(selectedTrack)
  } else {
    await playFirstTrack()
  }

  const nextQuery = { ...route.query }
  delete nextQuery.autoplay
  delete nextQuery.track

  await router.replace({
    path: route.path,
    query: nextQuery,
  })
}

const likeAlbumTrack = async (track: Track) => {
  likeBusyId.value = track.id
  try {
    await toggleLikedTrack(track.id)
  } finally {
    likeBusyId.value = null
  }
}

const playTrack = async (track: Track) => {
  if (isPreparingTrack.value) {
    return
  }

  isPreparingTrack.value = true
  playerError.value = ''

  try {
    streamingError.value = ''

    setQueue((album.value?.songs || []).map((queueTrack) => buildAlbumQueueEntry(queueTrack)), track.id)

    const isSameAlbumTrack =
      playerTrack.value?.id === track.id
      && playerTrack.value?.collectionType === 'album'
      && String(playerTrack.value?.collectionId) === String(album.value?.id)

    if (isSameAlbumTrack) {
      await togglePlay()
      return
    }

    await playInLayout({
      id: track.id,
      title: track.title,
      duration: track.duration ?? null,
      artistName: album.value?.artist?.name || tl('Artist'),
      artistId: album.value?.artist?.id,
      albumTitle: album.value?.title,
      albumId: album.value?.id,
      coverUrl: album.value ? albumCover(album.value) : '',
      collectionType: 'album',
      collectionId: album.value?.id,
      resolveStreamUrl: () => getTrackStreamUrl(track.id),
    })
  } catch (error: any) {
    console.error('Track streaming error:', error)
    streamingError.value = error?.data?.message || tl('Could not start track streaming')
  } finally {
    isPreparingTrack.value = false
  }
}

const deleteCurrentAlbum = async () => {
  if (!album.value || isDeletingAlbum.value) {
    return
  }

  if (!window.confirm(tl('Delete this album?'))) {
    return
  }

  isDeletingAlbum.value = true
  formError.value = ''

  try {
    const response = await deleteAlbum(album.value.id)

    if (!response.success) {
      formError.value = response.message || tl('Failed to delete album')
      return
    }

    await router.push(`/albums/${route.params.artistId}`)
  } finally {
    isDeletingAlbum.value = false
  }
}

const toggleAlbumLibrary = async () => {
  if (!album.value || isFollowBusy.value) {
    return
  }

  isFollowBusy.value = true
  formError.value = ''

  try {
    const response = album.value.is_in_library
      ? await removeAlbumFromLibrary(album.value.id)
      : await addAlbumToLibrary(album.value.id)

    if (!response.success) {
      formError.value = response.message || tl('Could not update library')
      return
    }

    album.value = {
      ...album.value,
      ...(response.data || {}),
      is_in_library: response.data?.is_in_library ?? !album.value.is_in_library,
    }
  } finally {
    isFollowBusy.value = false
  }
}

const openAlbumArtist = async () => {
  if (!album.value?.artist?.id) {
    return
  }

  await router.push(`/albums/${album.value.artist.id}`)
}

const shareAlbum = async () => {
  if (!album.value) {
    return
  }

  await prepareShare({
    type: 'album',
    id: album.value.id,
    title: album.value.title,
    subtitle: `${album.value.artist?.name || tl('Artist')} • ${formatTracksCount(album.value.songs?.length || 0)}`,
    image_url: albumCover(album.value),
  })
}

onMounted(async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const artistId = String(route.params.artistId)
    const albumId = String(route.params.albumId)

    const [albumData, userData] = await Promise.all([
      getArtistAlbum(artistId, albumId),
      getCurrentUser(),
    ])

    album.value = albumData
    titleDraft.value = albumData.title
    currentUser.value = userData

    if (userData) {
      currentArtist.value = await getCurrentArtist()
    }

    if (userData) {
      const [playlistsData] = await Promise.all([
        getMyPlaylists(),
        refreshLiked(),
      ])
      myPlaylists.value = playlistsData.filter((playlist) => playlist.title !== FAVORITES_PLAYLIST_TITLE)
    }
  } catch (error: any) {
    console.error('Album loading error:', error)
    errorMessage.value = error?.data?.message || tl('Failed to load album')
  } finally {
    loading.value = false
    void autoplayFromQueryIfNeeded()
  }
})

watch(
  () => album.value?.id,
  () => {
    void autoplayFromQueryIfNeeded()
  }
)

watch(
  () => [route.query.autoplay, route.query.track],
  () => {
    void autoplayFromQueryIfNeeded()
  }
)
</script>

<style scoped>
.owner-actions {
  display: grid;
  gap: 10px;
}

.hero-side-actions {
  display: flex;
  flex-direction: row;
  gap: 10px;
  align-items: center;
  justify-content: flex-end;
}

.owner-action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 32px;
  padding: 0 14px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  text-decoration: none;
  font: inherit;
  font-weight: 800;
  font-size: 0.82rem;
  cursor: pointer;
}

.owner-action-button:hover {
  color: var(--button-primary-text);
  background: var(--button-primary-hover);
}

.owner-action-button--danger {
  background: var(--button-danger-bg);
  color: var(--button-danger-text);
  border: 1px solid var(--button-danger-border);
}

.owner-action-button--danger:hover {
  color: var(--button-danger-text);
  background: var(--button-danger-hover);
}

.owner-action-button--danger {
  box-shadow: 0 8px 18px rgba(40, 7, 13, 0.18);
}

.owner-action-button--danger > * {
  color: inherit;
}

.owner-action-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.share-icon-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  flex: 0 0 auto;
  border: 1px solid var(--button-control-border);
  border-radius: 50%;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  line-height: 0;
  cursor: pointer;
}

.share-icon-button:hover {
  background: var(--button-control-hover);
}

.share-icon-button :deep(svg) {
  font-size: 1rem;
}

.hero-link {
  padding: 0;
  border: 0;
  color: inherit;
  background: transparent;
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.hero-link:hover {
  text-decoration: underline;
}

.editable-title {
  margin: 0;
}

.editable-title--interactive {
  cursor: text;
}

.title-input {
  width: min(100%, 760px);
  padding: 8px 12px;
  border-radius: 12px;
  border: 1px solid var(--color-input-border);
  background: var(--color-input-bg);
  color: var(--color-input-text);
  font: inherit;
  font-size: clamp(2.6rem, 6vw, 5rem);
  font-weight: 800;
  line-height: 0.96;
}

.tracks-table__th-like {
  width: 48px;
  padding: 0;
}

.tracks-table__th-duration,
.tracks-table__td-duration {
  width: 92px;
  text-align: right;
  white-space: nowrap;
}

:deep(.tracks-table td:nth-child(3)),
:deep(.tracks-table th:nth-child(3)) {
  width: 180px;
}

:deep(.tracks-table td:nth-child(4)) {
  width: 92px;
  text-align: right;
}

:deep(.tracks-table td:nth-child(5)) {
  width: 48px;
  padding: 8px 12px 8px 0;
  text-align: right;
  vertical-align: middle;
}

:deep(.tracks-table tbody tr) {
  border-bottom: 1px solid var(--color-border);
  cursor: pointer;
}

:deep(.tracks-table tbody tr:hover) {
  background: var(--color-row-hover);
}

:deep(.tracks-table tbody tr.active) {
  background: var(--color-row-active);
  box-shadow: inset 3px 0 0 var(--color-row-active-border);
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

.track-marker {
  position: relative;
  width: 16px;
  height: 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.track-index {
  transition: opacity 0.15s ease;
}

:deep(.tracks-table tbody tr:hover .track-index) {
  opacity: 0;
}

:deep(.tracks-table tbody tr:hover .track-play-icon) {
  opacity: 1;
}

.track-like-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  color: var(--color-text-soft);
  line-height: 0;
  cursor: pointer;
  opacity: 0;
  pointer-events: none;
  transition: color 0.15s, background 0.15s, opacity 0.15s;
}

.track-row:hover .track-like-btn {
  opacity: 1;
  pointer-events: auto;
}

.track-like-btn--liked,
.track-like-btn--liked:hover {
  opacity: 1;
  pointer-events: auto;
  color: var(--color-accent);
  background: rgba(255, 255, 255, 0.16);
}

.track-like-btn:hover:not(:disabled) {
  color: var(--color-accent);
  background: rgba(255, 255, 255, 0.16);
}

.track-like-btn:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.track-like-btn__icon {
  font-size: 1.2rem;
  display: block;
  line-height: 1;
  transform: translateY(0.5px);
}

@media (hover: none), (max-width: 760px) {
  .hero-side-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .track-like-btn,
  .track-row:hover .track-like-btn {
    opacity: 0.85;
    pointer-events: auto;
  }
}

@media (max-width: 960px) {
  .owner-actions {
    width: 100%;
    grid-auto-flow: column;
    justify-content: flex-start;
  }

  .title-input {
    font-size: clamp(2rem, 7vw, 3.4rem);
  }

  :deep(.tracks-table td:nth-child(3)),
  :deep(.tracks-table th:nth-child(3)) {
    display: none;
  }

  :deep(.tracks-table td:nth-child(4)),
  :deep(.tracks-table th:nth-child(4)) {
    width: 72px;
  }
}

@media (max-width: 560px) {
  .owner-actions {
    grid-auto-flow: row;
  }

  .owner-action-button {
    width: 100%;
  }

  .hero-side-actions {
    flex-wrap: wrap;
  }

  .share-icon-button {
    width: 34px;
    height: 34px;
  }

  .title-input {
    padding: 6px 10px;
  }

  :deep(.tracks-table td:nth-child(4)),
  :deep(.tracks-table th:nth-child(4)) {
    width: 56px;
    padding-left: 8px;
    padding-right: 8px;
  }
}
</style>
