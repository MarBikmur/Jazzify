<template>
  <CollectionDetailTemplate
    :loading="loading"
    :error-message="errorMessage"
    :loading-message="tl('Loading playlist...')"
    back-to="/playlists"
    :back-label="tl('Your playlists')"
    :type-label="tl('Playlist')"
    :title="displayTitle"
    :subtitle="playlistSubtitle"
    :image-src="playlist ? (playlistCover(playlist) || '') : ''"
    :fallback-icon="isFavoritesPlaylist ? 'material-symbols:favorite' : 'solar:music-note-2-bold'"
    :fallback-variant="isFavoritesPlaylist ? 'liked' : 'playlist'"
    hero-class="playlist-hero"
  >
    <template #hero-subtitle>
      <p v-if="playlist">
          <button
            v-if="playlist.user?.uid"
            type="button"
            class="hero-link"
            @click="openPlaylistAuthor"
          >
            {{ playlist.user?.name || tl('User') }}
          </button>
          <template v-else>{{ playlist.user?.name || tl('User') }}</template>
          <span>• {{ formatTracksCount((playlist.songs || []).length) }}</span>
          <span v-if="playlist.is_private">• {{ tl('Private') }}</span>
      </p>
    </template>

    <template #back-actions>
      <div v-if="canManagePlaylist || canDeletePlaylist || isAdmin" class="owner-actions">
        <NuxtLink
          :to="canManagePlaylist ? `/playlists/${idParam}/edit` : `/admin/playlists/${idParam}`"
          class="owner-action-button"
        >
          {{ tl('Edit') }}
        </NuxtLink>
        <button
          v-if="canDeletePlaylist"
          type="button"
          class="owner-action-button owner-action-button--danger"
          :disabled="saving"
          @click="onDeletePlaylist"
        >
          {{ tl('Delete') }}
        </button>
      </div>
    </template>

    <template #actions-main>
      <ListenPillButton
        :label="isThisPlaylistPlaying ? tl('Pause') : tl('Listen')"
        :disabled="isPreparing || isPlayerLoading"
        @click="playFirst"
      />
    </template>

    <template #actions-side>
      <div class="hero-side-actions">
        <FollowToggleButton
          v-if="canFollowPlaylist"
          :active="!!playlist?.is_in_library"
          :loading="followBusy"
          loading-label="Saving..."
          @click="togglePlaylistLibrary"
        />
        <button
          v-if="canSharePlaylist"
          type="button"
          class="share-icon-button"
          :aria-label="tl('Share playlist')"
          @click="sharePlaylist"
        >
          <Icon :icon="getIcon('solar:share-linear')" />
        </button>
      </div>
    </template>

    <template #notices>
      <FormNotice v-if="formError" variant="error" :message="formError" />
      <FormNotice v-if="playbackError" variant="error" :message="playbackError" />
    </template>

    <template #extra-bottom>
      <PlaylistAddTracksPanel
        v-if="canManagePlaylist"
        v-model:query="songQuery"
        :loading="loadingSongs"
        :songs="addableSongs"
        :adding-id="addingId"
        @add-song="addSong"
      />
    </template>

    <template #table-section>
      <CollectionTracksTable :has-rows="(playlist?.songs || []).length > 0">
        <template #header>
          <h2>{{ tl('Tracks') }}</h2>
          <span class="tracks-count">{{ (playlist?.songs || []).length }}</span>
        </template>

        <template #head>
          <th>#</th>
          <th>{{ tl('Title') }}</th>
          <th>{{ tl('Artist') }}</th>
          <th>{{ tl('Genre') }}</th>
          <th class="tracks-table__th-duration">{{ tl('Duration') }}</th>
          <th v-if="canManagePlaylist" class="tracks-table__th-actions" />
        </template>

        <template #body>
          <tr
            v-for="(track, index) in playlist?.songs || []"
            :key="track.id"
            class="track-row"
            :class="{ active: playerTrack?.id === track.id }"
            @click="() => playTrackRow(track)"
            @contextmenu.prevent="openTrackContextMenu($event, track)"
          >
            <td>
              <span class="track-marker">
                <span class="track-index">{{ index + 1 }}</span>
                <Icon
                  :icon="getIcon('material-symbols:play-arrow-rounded')"
                  class="track-play-icon"
                />
              </span>
            </td>
            <td>
              <strong>{{ track.title }}</strong>
              <span class="sub">{{ track.album?.title || tl('Track') }}</span>
            </td>
            <td>{{ track.artist?.name || '—' }}</td>
            <td>{{ track.genre?.name || '-' }}</td>
            <td class="tracks-table__td-duration">{{ formatTrackDuration(track) }}</td>
            <td v-if="canManagePlaylist" class="actions">
              <button
                type="button"
                class="btn text-danger small"
                :disabled="removingId === track.id"
                @click.stop="() => removeTrack(track.id)"
              >
                {{ tl('Remove') }}
              </button>
            </td>
          </tr>
        </template>

        <template #empty>
          <PageState :message="tl('No tracks yet.')" min-height="120px" />
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
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useAuth } from '~/composables/useAuth'
import { useAlbum } from '~/composables/useAlbum'
import { useAppIcons } from '~/composables/useAppIcons'
import { FAVORITES_PLAYLIST_TITLE } from '~/composables/useLikedSongs'
import { usePlaylist, type Playlist, type PlaylistSong } from '~/composables/usePlaylist'
import { useMediaUrl } from '~/composables/useMediaUrl'
import { useLikedSongs } from '~/composables/useLikedSongs'
import { formatDuration } from '~/utils/audioDuration'

const route = useRoute()
const router = useRouter()
const { tl } = useLocalizedText()
const { getIcon } = useAppIcons()
const { getCurrentUser } = useAuth()
const { getTrackStreamUrl } = useAlbum()
const { getPlaylist, getMyPlaylists, deletePlaylist, addSongToPlaylist, removeSongFromPlaylist } = usePlaylist()
const { addPlaylistToLibrary, removePlaylistFromLibrary } = useLibrary()
const { mediaUrl } = useMediaUrl()
const { favoritesPlaylistId, refresh: refreshLiked, likedSongIds, isLiked, toggle: toggleLikedTrack } = useLikedSongs()
const { prepareShare } = useMessenger()

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

const playlist = ref<Playlist | null>(null)
const myPlaylists = ref<Playlist[]>([])
const favoritesSongs = ref<PlaylistSong[]>([])
const currentUser = ref<{ uid: string; name?: string; role?: string } | null>(null)
const loading = ref(true)
const loadingSongs = ref(false)
const errorMessage = ref('')
const formError = ref('')
const saving = ref(false)
const isPreparing = ref(false)
const streamingError = ref('')
const songQuery = ref('')
const addingId = ref<number | null>(null)
const removingId = ref<number | null>(null)
const selectedTrack = ref<PlaylistSong | null>(null)
const trackMenuPosition = ref({ x: 0, y: 0 })
const followBusy = ref(false)

const formatTracksCount = (count: number) => count === 1 ? `1 ${tl('track')}` : `${count} ${tl('tracks')}`

const idParam = computed(() => route.params.id as string)
const isOwner = computed(
  () => !!currentUser.value?.uid && !!playlist.value?.user_uid && currentUser.value.uid === playlist.value.user_uid
)
const isAdmin = computed(() => currentUser.value?.role === 'admin')
const isFavoritesPlaylist = computed(
  () => !!playlist.value?.id && !!favoritesPlaylistId.value && playlist.value.id === favoritesPlaylistId.value
)
const canManagePlaylist = computed(() => isOwner.value && !isFavoritesPlaylist.value)
const canDeletePlaylist = computed(() => (isOwner.value || isAdmin.value) && !isFavoritesPlaylist.value)
const canFollowPlaylist = computed(() => !!currentUser.value?.uid && !isOwner.value && !isFavoritesPlaylist.value)
const canSharePlaylist = computed(() => !!playlist.value && !playlist.value.is_private)

const displayTitle = computed(() => tl(playlist.value?.title || ''))
const playlistIds = computed(() => new Set((playlist.value?.songs || []).map((s) => s.id)))

const addableSongs = computed(() => {
  const q = songQuery.value.trim().toLowerCase()
  return (favoritesSongs.value || [])
    .filter((s) => !playlistIds.value.has(s.id))
    .filter((s) => {
      if (!q) {
        return true
      }
      const title = (s.title || '').toLowerCase()
      const art = (s.artist?.name || '').toLowerCase()
      return title.includes(q) || art.includes(q)
    })
    .slice(0, 5)
})

const trackCover = (t: PlaylistSong) => (t as any).album?.cover_image_url || mediaUrl((t as any).album?.cover_image_path)
const defaultPlaylistCover = (pl: Playlist) => (pl as any).cover_image_url || mediaUrl(pl.cover_image_path)
const playlistCover = (pl: Playlist) => defaultPlaylistCover(pl)

const isThisTrack = computed(() => {
  const tid = playerTrack.value?.id
  const currentPlaylistId = playlist.value?.id

  if (!tid || !currentPlaylistId || !playlist.value?.songs?.length) {
    return false
  }

  if (playerTrack.value?.collectionType && playerTrack.value.collectionType !== 'playlist') {
    return false
  }

  if (playerTrack.value?.collectionType === 'playlist' && String(playerTrack.value.collectionId) !== String(currentPlaylistId)) {
    return false
  }

  return playlist.value.songs.some((t) => t.id === tid)
})

const isThisPlaylistPlaying = computed(() => isThisTrack.value && isPlaying.value)
const playbackError = computed(() => streamingError.value || playerError.value)
const isTrackMenuOpen = computed(() => !!selectedTrack.value)
const playlistSubtitle = computed(() => '')
const availablePlaylists = computed(() =>
  myPlaylists.value.filter((playlistItem) =>
    playlistItem.id !== favoritesPlaylistId.value && playlistItem.id !== playlist.value?.id
  )
)

const load = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    currentUser.value = await getCurrentUser()
    if (!currentUser.value) {
      await router.replace('/login')
      return
    }

    const [playlistData, playlistsData] = await Promise.all([
      getPlaylist(idParam.value),
      getMyPlaylists(),
    ])

    playlist.value = playlistData
    myPlaylists.value = playlistsData.filter((playlistItem) => playlistItem.title !== FAVORITES_PLAYLIST_TITLE)
  } catch (e: any) {
    errorMessage.value = e?.data?.message || tl('Could not load playlist')
  } finally {
    loading.value = false
    void autoplayFromQueryIfNeeded()
  }
}

const loadSongs = async () => {
  if (!canManagePlaylist.value) {
    favoritesSongs.value = []
    return
  }

  loadingSongs.value = true
  try {
    await refreshLiked()

    if (!favoritesPlaylistId.value || playlist.value?.id === favoritesPlaylistId.value) {
      favoritesSongs.value = []
      return
    }

    const favoritesPlaylist = await getPlaylist(favoritesPlaylistId.value)
    favoritesSongs.value = favoritesPlaylist.songs || []
  } catch (e) {
    console.error(e)
  } finally {
    loadingSongs.value = false
  }
}

onMounted(() => {
  void load()
})

watch(isOwner, (v) => {
  if (v && canManagePlaylist.value) {
    void loadSongs()
  }
})

watch(
  () => playlist.value?.id,
  () => {
    if (canManagePlaylist.value) {
      void loadSongs()
    }

    void autoplayFromQueryIfNeeded()
  }
)

watch(
  () => route.query.autoplay,
  () => {
    void autoplayFromQueryIfNeeded()
  }
)

const onDeletePlaylist = async () => {
  if (!playlist.value || !canDeletePlaylist.value) {
    return
  }

  if (!window.confirm(tl('Delete this playlist?'))) {
    return
  }

  saving.value = true
  const res = await deletePlaylist(playlist.value.id)
  saving.value = false

  if (res.success) {
    await router.push('/playlists')
  } else {
    formError.value = res.message || tl('Could not delete')
  }
}

const addSong = async (s: PlaylistSong) => {
  if (!playlist.value) {
    return
  }

  addingId.value = s.id
  formError.value = ''
  const res = await addSongToPlaylist(playlist.value.id, s.id)

  if (res.success && res.data) {
    playlist.value = res.data
    songQuery.value = ''
  } else {
    formError.value = res.message || tl('Could not add')
  }

  addingId.value = null
}

const removeTrack = async (songId: number) => {
  if (!playlist.value) {
    return
  }

  removingId.value = songId
  formError.value = ''
  const res = await removeSongFromPlaylist(playlist.value.id, songId)

  if (res.success && res.data) {
    playlist.value = res.data
    if (isFavoritesPlaylist.value) {
      await refreshLiked()
    }
  } else {
    formError.value = res.message || tl('Could not remove')
  }

  removingId.value = null
}

const closeTrackContextMenu = () => {
  selectedTrack.value = null
}

const openTrackContextMenu = (event: MouseEvent, track: PlaylistSong) => {
  trackMenuPosition.value = { x: event.clientX, y: event.clientY }
  selectedTrack.value = track
}

const togglePlaylistLibrary = async () => {
  if (!playlist.value || followBusy.value) {
    return
  }

  followBusy.value = true
  formError.value = ''

  try {
    const response = playlist.value.is_in_library
      ? await removePlaylistFromLibrary(playlist.value.id)
      : await addPlaylistToLibrary(playlist.value.id)

    if (!response.success) {
      formError.value = response.message || tl('Could not update library')
      return
    }

    playlist.value = {
      ...playlist.value,
      ...(response.data || {}),
      songs: playlist.value.songs,
      is_in_library: response.data?.is_in_library ?? !playlist.value.is_in_library,
    }
  } finally {
    followBusy.value = false
  }
}

const openPlaylistAuthor = async () => {
  if (!playlist.value?.user?.uid) {
    return
  }

  await router.push(`/users/${playlist.value.user.uid}`)
}

watch(
  likedSongIds,
  async () => {
    if (!isFavoritesPlaylist.value || !playlist.value?.id) {
      return
    }

    try {
      playlist.value = await getPlaylist(playlist.value.id)
    } catch (error) {
      console.error(error)
    }
  },
  { deep: true }
)

const buildQueueEntry = (t: PlaylistSong) => {
  if (!playlist.value) {
  return {
    id: t.id,
    title: t.title,
    duration: t.duration ?? null,
    artistName: t.artist?.name || tl('Artist'),
    artistId: t.artist?.id,
    albumTitle: t.album?.title || tl('Track'),
    albumId: t.album?.id,
    coverUrl: trackCover(t) || '',
    collectionType: 'playlist' as const,
    collectionId: idParam.value,
      resolveStreamUrl: () => getTrackStreamUrl(t.id),
    }
  }

  return {
    id: t.id,
    title: t.title,
    duration: t.duration ?? null,
    artistName: t.artist?.name || tl('Artist'),
    artistId: t.artist?.id,
    albumTitle: t.album?.title || tl('Track'),
    albumId: t.album?.id,
    coverUrl: trackCover(t) || defaultPlaylistCover(playlist.value) || '',
    collectionType: 'playlist' as const,
    collectionId: playlist.value.id,
    resolveStreamUrl: () => getTrackStreamUrl(t.id),
  }
}

const playFirst = async () => {
  const isSamePlaylistTrack =
    isThisTrack.value
    && playerTrack.value?.collectionType === 'playlist'
    && String(playerTrack.value?.collectionId) === String(playlist.value?.id)

  if (isSamePlaylistTrack && playerTrack.value) {
    await togglePlay()
    return
  }

  const first = playlist.value?.songs?.[0]
  if (first) {
    await playTrackRow(first)
  }
}

const formatTrackDuration = (track: PlaylistSong) => formatDuration(track.duration)

const autoplayFromQueryIfNeeded = async () => {
  if (route.query.autoplay !== '1' || loading.value || !playlist.value?.songs?.length || isPreparing.value) {
    return
  }

  await playFirst()

  const nextQuery = { ...route.query }
  delete nextQuery.autoplay

  await router.replace({
    path: route.path,
    query: nextQuery,
  })
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
    ...(canManagePlaylist.value ? [{
      key: 'remove-from-playlist',
      label: tl('Remove from this playlist'),
      icon: 'material-symbols:delete-outline-rounded',
      danger: true,
      action: async () => {
        await removeTrack(track.id)
      },
    }] : []),
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
      action: () => addToQueue(buildQueueEntry(track)),
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
          subtitle: track.artist?.name || tl('Artist'),
          image_url: trackCover(track) || (playlist.value ? defaultPlaylistCover(playlist.value) : '') || '',
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
      disabled: !track.artist?.id,
      action: async () => {
        if (track.artist?.id) {
          await router.push(`/albums/${track.artist.id}`)
        }
      },
    },
    {
      key: 'go-to-album',
      label: tl('Go to album'),
      icon: 'solar:album-bold',
      disabled: !track.artist?.id || !track.album?.id,
      action: async () => {
        if (track.artist?.id && track.album?.id) {
          await router.push(`/albums/${track.artist.id}/${track.album.id}`)
        }
      },
    },
  ]
})

const playTrackRow = async (track: PlaylistSong) => {
  if (isPreparing.value || !playlist.value?.songs?.length) {
    return
  }

  isPreparing.value = true
  streamingError.value = ''
  playerError.value = ''

  const qTracks = (playlist.value.songs || []).map((t) => buildQueueEntry(t))
  setQueue(qTracks, track.id)

  try {
    const isSamePlaylistTrack =
      playerTrack.value?.id === track.id
      && playerTrack.value?.collectionType === 'playlist'
      && String(playerTrack.value?.collectionId) === String(playlist.value.id)

    if (isSamePlaylistTrack) {
      await togglePlay()
      return
    }

      await playInLayout({
        id: track.id,
        title: track.title,
        duration: track.duration ?? null,
        artistName: track.artist?.name || tl('Artist'),
      artistId: track.artist?.id,
      albumTitle: track.album?.title || tl('Playlist'),
      albumId: track.album?.id,
      coverUrl: trackCover(track) || defaultPlaylistCover(playlist.value) || '',
      collectionType: 'playlist',
      collectionId: playlist.value.id,
      resolveStreamUrl: () => getTrackStreamUrl(track.id),
    })
  } catch (e: any) {
    console.error(e)
    streamingError.value = e?.data?.message || tl('Could not start playback')
  } finally {
    isPreparing.value = false
  }
}

const sharePlaylist = async () => {
  if (!playlist.value || playlist.value.is_private) {
    return
  }

  await prepareShare({
    type: 'playlist',
    id: playlist.value.id,
    title: tl(playlist.value.title),
    subtitle: `${playlist.value.user?.name || tl('User')} • ${formatTracksCount((playlist.value.songs || []).length)}`,
    image_url: playlistCover(playlist.value) || '',
  })
}
</script>

<style scoped>
.playlist-hero {
  align-items: center;
}

.playlist-hero :deep(h1) {
  margin: 6px 0 8px;
  font-size: clamp(2.6rem, 6vw, 5rem);
  line-height: 0.96;
  color: var(--color-text-main);
}

.playlist-hero :deep(.collection-hero__meta) {
  min-width: 0;
  align-self: center;
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

.tracks-table__th-duration,
.tracks-table__td-duration {
  width: 110px;
  text-align: right;
  white-space: nowrap;
}

.btn {
  border: 0;
  font: inherit;
  cursor: pointer;
  border-radius: 999px;
  padding: 8px 16px;
  background: var(--button-primary-bg);
  color: var(--button-primary-text);
  border: 1px solid var(--button-primary-border);
  box-shadow: var(--shadow-primary);
  font-weight: 700;
  font-size: 0.85rem;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn.small {
  padding: 4px 10px;
  font-size: 0.8rem;
}

.btn.text-danger {
  background: var(--button-danger-bg);
  color: var(--button-danger-text);
  border: 1px solid var(--button-danger-border);
  padding: 4px 8px;
  font-weight: 600;
  box-shadow: 0 8px 18px rgba(40, 7, 13, 0.18);
}

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

.owner-action-button--danger > * {
  color: inherit;
}

.owner-action-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.tracks-table__th-actions {
  width: 120px;
  padding-right: 18px;
}

.tracks-count {
  color: var(--color-text-muted);
}

:deep(.tracks-table td:nth-child(3)),
:deep(.tracks-table th:nth-child(3)) {
  width: 220px;
}

.track-row {
  cursor: pointer;
}

:deep(.tracks-table tbody tr) {
  border-bottom: 1px solid var(--color-border);
}

.track-row:hover {
  background: var(--color-row-hover);
}

.track-row.active {
  background: var(--color-row-active);
  box-shadow: inset 3px 0 0 var(--color-row-active-border);
}

:deep(.tracks-table td) {
  vertical-align: middle;
}

.actions {
  cursor: default;
  width: 120px;
  text-align: right;
  padding: 8px 18px 8px 0;
}

.actions .btn.text-danger {
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s;
}

.track-row:hover .actions .btn.text-danger,
.actions .btn.text-danger:disabled {
  opacity: 1;
  pointer-events: auto;
}

.sub {
  display: block;
  color: var(--color-text-muted);
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

.track-row:hover .track-index {
  opacity: 0;
}

.track-row:hover .track-play-icon {
  opacity: 1;
}

@media (max-width: 760px) {
  .hero-side-actions {
    width: 100%;
    justify-content: flex-start;
  }
}

@media (max-width: 960px) {
  .owner-actions {
    width: 100%;
    grid-auto-flow: column;
    justify-content: flex-start;
  }

  :deep(.tracks-table td:nth-child(4)),
  :deep(.tracks-table th:nth-child(4)) {
    display: none;
  }
}

@media (max-width: 760px) {
  :deep(.tracks-table td:nth-child(3)),
  :deep(.tracks-table th:nth-child(3)) {
    display: none;
  }

  .actions {
    width: 92px;
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

  .tracks-table__th-actions,
  .actions {
    width: 76px;
    padding-right: 8px;
  }

  .btn.text-danger {
    padding: 4px 6px;
    font-size: 0.76rem;
  }
}
</style>
