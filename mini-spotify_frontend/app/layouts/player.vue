<template>
  <main
    class="player-shell"
    :class="{
      'player-shell--sidebar-collapsed': isSidebarCollapsed,
      'player-shell--right-panel-open': isRightPanelOpen,
    }"
  >
    <aside class="sidebar" :class="{ 'sidebar--collapsed': isSidebarCollapsed }">
      <div class="sidebar-header">
        <AppBrand class="brand" />
        <button
          class="icon-button sidebar-toggle"
          type="button"
          :aria-label="isSidebarCollapsed ? tl('Expand sidebar') : tl('Collapse sidebar')"
          @click="toggleSidebar"
        >
          <Icon :icon="getIcon(isSidebarCollapsed ? 'solar:alt-arrow-right-linear' : 'solar:alt-arrow-left-linear')" class="ui-icon ui-icon--md" />
        </button>
      </div>

      <section v-if="!isSidebarCollapsed" class="library-panel">
        <button type="button" class="section-title section-title--link" @click="openDiscoverGenre">
          <span class="nav-icon">
            <Icon :icon="getIcon('solar:compass-linear')" class="ui-icon ui-icon--sm" />
          </span>
          <span>{{ tl('Discover new genre') }}</span>
        </button>
        <NuxtLink to="/library" class="section-title section-title--link">
          <span class="nav-icon">
            <Icon :icon="getIcon('solar:library-bold')" class="ui-icon ui-icon--sm" />
          </span>
          <span>{{ tl('Your Library') }}</span>
        </NuxtLink>
        <div class="library-list">
          <div
            v-for="item in expandedLibraryItems"
            :key="item.id"
            class="library-row"
            :class="{ 'library-row--active': item.active }"
            @click="openLibraryItem(item.to)"
          >
            <ArtworkCover
              class="library-row__cover"
              :src="item.coverUrl"
              :alt="item.title"
              :fallback-icon="item.fallbackIcon"
              :fallback-variant="item.kind === 'liked' ? 'liked' : 'playlist'"
              icon-size="1.2rem"
            />
            <div class="library-row__meta">
              <strong :class="{ 'library-row__title--playing': item.playing }">{{ item.title }}</strong>
              <span>{{ item.subtitle }}</span>
            </div>
            <button
              class="library-row__play"
              type="button"
              :aria-label="`${tl('Play')} ${item.title}`"
              @click.stop="playSidebarPlaylist(item)"
            >
              <Icon :icon="getIcon('material-symbols:play-arrow-rounded')" class="ui-icon ui-icon--sm" />
            </button>
          </div>
        </div>
      </section>

      <section v-else class="library-panel library-panel--compact">
        <div class="compact-group">
          <div class="compact-shortcuts">
            <button type="button" class="mini-nav-button" @click="openDiscoverGenre">
              <span class="nav-icon">
                <Icon :icon="getIcon('solar:compass-linear')" class="ui-icon ui-icon--sm" />
              </span>
            </button>
            <NuxtLink
              class="mini-nav-button"
              :class="{ 'mini-nav-button--active': route.path === '/library' }"
              to="/library"
            >
              <span class="nav-icon">
                <Icon :icon="getIcon('solar:library-bold')" class="ui-icon ui-icon--sm" />
              </span>
            </NuxtLink>
          </div>
          <div class="mini-library-grid">
            <div
              v-for="item in compactLibraryItems"
              :key="item.id"
              class="mini-library-item-shell"
              :title="item.title"
            >
              <NuxtLink
                class="mini-library-item"
                :class="{ 'mini-library-item--active': item.active }"
                :to="item.to"
                :title="item.title"
              >
                <ArtworkCover
                  class="mini-library-item__cover"
                  :src="item.coverUrl"
                  :alt="item.title"
                  :fallback-icon="item.fallbackIcon"
                  :fallback-variant="item.kind === 'liked' ? 'liked' : 'playlist'"
                  icon-size="1.35rem"
                />
              </NuxtLink>
              <button
                class="mini-library-item__play"
                type="button"
                :aria-label="`${tl('Play')} ${item.title}`"
                @click.stop="playSidebarPlaylist(item)"
              >
                <Icon :icon="getIcon('material-symbols:play-arrow-rounded')" class="ui-icon ui-icon--sm" />
              </button>
            </div>
          </div>
        </div>
      </section>

      <div v-if="user" class="sidebar-utility-actions">
        <button
          class="sidebar-create-playlist"
          type="button"
          :disabled="isLoadingPlaylist"
          @click="onCreatePlaylist"
        >
          <span class="sidebar-create-playlist__icon">
            <Icon :icon="getIcon('material-symbols:add-rounded')" class="ui-icon ui-icon--md" />
          </span>
          <span class="sidebar-create-playlist__label">{{ tl('New playlist') }}</span>
        </button>
      </div>

      <section v-if="user" class="account-panel">
        <button class="account-panel__profile" type="button" @click="openOwnUserPage">
          <div class="avatar">
            <img
              v-if="avatarUrl"
              :src="avatarUrl"
              :alt="displayName"
              class="avatar-image"
            />
            <span v-else>{{ userInitials }}</span>
          </div>
        </button>
        <div class="account-panel__meta">
          <strong class="account-panel__name" @click="openProfileEditor">{{ displayName }}</strong>
          <button
            v-if="artist?.id"
            type="button"
            class="account-panel__role-link"
            @click="openOwnArtistPage"
          >
            {{ displayRole }}
          </button>
          <span v-else>{{ displayRole }}</span>
        </div>
      </section>
    </aside>

    <section class="workspace">
      <section class="content-area">
        <header class="topbar">
          <div class="topbar-controls">
            <button class="icon-button" type="button" :aria-label="tl('Back')" @click="goBack">
              <Icon :icon="getIcon('solar:alt-arrow-left-linear')" class="ui-icon ui-icon--md" />
            </button>
            <button class="icon-button" type="button" :aria-label="tl('Forward')" @click="goForward">
              <Icon :icon="getIcon('solar:alt-arrow-right-linear')" class="ui-icon ui-icon--md" />
            </button>
          </div>

          <form ref="searchFormRef" class="search-shell" @submit.prevent="submitSearch">
            <label class="search-box">
              <Icon :icon="getIcon('material-symbols:search-rounded')" class="ui-icon ui-icon--md search-box__icon" />
              <input
                v-model="searchQuery"
                type="text"
                :placeholder="tl('What do you want to play?')"
                @focus="openSearchDropdown"
              />
            </label>

            <div
              v-if="showSearchDropdown"
              class="search-dropdown"
            >
              <div v-if="searchLoading" class="search-dropdown__state">{{ tl('Searching...') }}</div>
              <div v-else-if="searchError" class="search-dropdown__state search-dropdown__state--error">{{ searchError }}</div>
              <div v-else-if="!hasQuickResults" class="search-dropdown__state">{{ tl('Nothing found') }}</div>
              <template v-else>
                <section v-if="quickResults.tracks.length" class="search-dropdown__section">
                  <strong class="search-dropdown__title">{{ tl('Tracks') }}</strong>
                  <button
                    v-for="track in quickResults.tracks"
                    :key="`search-track-${track.id}`"
                    class="search-dropdown__item"
                    type="button"
                    @click="goToTrackResult(track)"
                  >
                    <ArtworkCover
                      class="search-dropdown__cover"
                      :src="track.album?.id ? quickAlbumCoverById(track.album.id) : ''"
                      :alt="track.title"
                      fallback-icon="solar:music-notes-bold"
                      fallback-variant="playlist"
                    />
                    <span class="search-dropdown__copy">
                      <strong>{{ track.title }}</strong>
                      <span>{{ track.artist?.name || tl('Artist') }}<template v-if="track.album?.title"> • {{ track.album.title }}</template></span>
                    </span>
                  </button>
                </section>

                <section v-if="quickResults.albums.length" class="search-dropdown__section">
                  <strong class="search-dropdown__title">{{ tl('Albums') }}</strong>
                  <button
                    v-for="album in quickResults.albums"
                    :key="`search-album-${album.id}`"
                    class="search-dropdown__item"
                    type="button"
                    @click="goToAlbumResult(album)"
                  >
                    <ArtworkCover
                      class="search-dropdown__cover"
                      :src="album.cover_image_url || mediaUrl(album.cover_image_path)"
                      :alt="album.title"
                      fallback="A"
                    />
                    <span class="search-dropdown__copy">
                      <strong>{{ album.title }}</strong>
                      <span>{{ album.artist?.name || tl('Artist') }}</span>
                    </span>
                  </button>
                </section>

                <section v-if="visibleQuickPlaylists.length" class="search-dropdown__section">
                  <strong class="search-dropdown__title">{{ tl('Playlists') }}</strong>
                  <button
                    v-for="playlist in visibleQuickPlaylists"
                    :key="`search-playlist-${playlist.id}`"
                    class="search-dropdown__item"
                    type="button"
                    @click="goToPlaylistResult(playlist)"
                  >
                    <ArtworkCover
                      class="search-dropdown__cover"
                      :src="playlist.cover_image_url || mediaUrl(playlist.cover_image_path)"
                      :alt="playlist.title"
                      fallback-icon="solar:music-note-2-bold"
                      fallback-variant="playlist"
                    />
                    <span class="search-dropdown__copy">
                      <strong>{{ playlist.title }}</strong>
                      <span>{{ formatTrackCount(playlist.songs_count ?? playlist.songs?.length ?? 0) }}</span>
                    </span>
                  </button>
                </section>

                <section v-if="quickResults.artists.length" class="search-dropdown__section">
                  <strong class="search-dropdown__title">{{ tl('Artists') }}</strong>
                  <button
                    v-for="resultArtist in quickResults.artists"
                    :key="`search-artist-${resultArtist.id}`"
                    class="search-dropdown__item"
                    type="button"
                    @click="goToArtistResult(resultArtist)"
                  >
                    <ArtworkCover
                      class="search-dropdown__cover"
                      :src="resultArtist.image_url || mediaUrl(resultArtist.image_path)"
                      :alt="resultArtist.name"
                      :fallback="resultArtist.name.slice(0, 1).toUpperCase()"
                      shape="circle"
                    />
                    <span class="search-dropdown__copy">
                      <strong>{{ resultArtist.name }}</strong>
                      <span>{{ tl('Artist') }}</span>
                    </span>
                  </button>
                </section>

                <section v-if="quickResults.users.length" class="search-dropdown__section">
                  <strong class="search-dropdown__title">{{ tl('Users') }}</strong>
                  <button
                    v-for="resultUser in quickResults.users"
                    :key="`search-user-${resultUser.uid}`"
                    class="search-dropdown__item"
                    type="button"
                    @click="goToUserResult(resultUser)"
                  >
                    <ArtworkCover
                      class="search-dropdown__cover"
                      :src="resultUser.avatar_url || mediaUrl(resultUser.avatar_path)"
                      :alt="resultUser.name"
                      :fallback="resultUser.name.slice(0, 1).toUpperCase()"
                      shape="circle"
                    />
                    <span class="search-dropdown__copy">
                      <strong>{{ resultUser.name }}</strong>
                      <span>{{ tl('User') }}</span>
                    </span>
                  </button>
                </section>

                <section v-if="quickResults.genres.length" class="search-dropdown__section">
                  <strong class="search-dropdown__title">{{ tl('Genres') }}</strong>
                  <button
                    v-for="genre in quickResults.genres"
                    :key="`search-genre-${genre.id}`"
                    class="search-dropdown__item"
                    type="button"
                    @click="goToGenreResult(genre.name)"
                  >
                    <span class="search-dropdown__genre-badge">G</span>
                    <span class="search-dropdown__copy">
                      <strong>{{ genre.name }}</strong>
                      <span>{{ tl('Genre') }}</span>
                    </span>
                  </button>
                </section>

                <button class="search-dropdown__all" type="submit">
                  {{ tl('View all results for') }} "{{ normalizedSearchQuery }}"
                </button>
              </template>
            </div>
          </form>

          <div class="profile-actions">
            <button
              v-if="user?.role === 'admin'"
              class="pill-button"
              type="button"
              :disabled="isLoadingAdmin"
              @click="onAdminPanel"
            >
              {{ tl('Admin panel') }}
            </button>
            <button
              v-if="user?.role === 'user' && !artist?.id"
              class="pill-button accent"
              type="button"
              :disabled="isLoadingArtist"
              @click="onBecomeArtist"
            >
              {{ tl('Become artist') }}
            </button>
            <button
              v-if="artist?.id"
              class="pill-button"
              type="button"
              :disabled="isLoadingMyAlbums || !artist?.id"
              @click="onMyAlbums"
            >
              {{ tl('Your artist page') }}
            </button>

            <button
              class="icon-button"
              type="button"
              :aria-label="t('app.localeToggleLabel')"
              @click="toggleLocale"
            >
              <span class="locale-toggle">{{ t('app.localeToggleShort') }}</span>
            </button>

            <button
              class="icon-button icon-button--messenger"
              type="button"
              :aria-label="isMessengerOpen ? tl('Close messenger') : tl('Open messenger')"
              @click="toggleMessenger"
            >
              <Icon
                :icon="getIcon('material-symbols:chat-rounded')"
                class="ui-icon ui-icon--md"
              />
              <span v-if="totalUnreadCount" class="icon-button__badge">{{ totalUnreadCount }}</span>
            </button>

            <button
              class="icon-button"
              type="button"
              :aria-label="isDarkTheme ? tl('Switch to light mode') : tl('Switch to dark mode')"
              @click="toggleTheme"
            >
              <Icon
                :icon="getIcon(isDarkTheme ? 'material-symbols:light-mode-rounded' : 'material-symbols:dark-mode-rounded')"
                class="ui-icon ui-icon--md"
              />
            </button>

            <button
              class="icon-button"
              type="button"
              :aria-label="tl('Logout')"
              :disabled="isLoggingOut"
              @mousedown.stop
              @click="openLogoutMenu"
            >
              <Icon :icon="getIcon('solar:logout-2-bold')" class="ui-icon ui-icon--md" />
            </button>
          </div>
        </header>

        <div v-if="isLoadingUser" class="state-panel">
          <span class="loader" />
          <span>{{ tl('Loading profile...') }}</span>
        </div>
        <div v-else class="page-slot">
          <slot />
        </div>
      </section>
    </section>

    <button
      v-if="isRightPanelOpen"
      class="messenger-backdrop"
      type="button"
      :aria-label="tl('Close sidebar')"
      @click="closeRightPanel"
    />
    <MessengerSidebar v-if="isMessengerOpen" class="player-shell__right-panel" />
    <TrackCommentsSidebar v-else-if="isTrackCommentsOpen" class="player-shell__right-panel" />
    <DiscoverGenreModal
      v-if="isDiscoverGenreOpen"
      @close="closeDiscoverGenre"
      @submit="openDiscoverGenrePage"
    />

    <footer class="player-bar">
      <div class="current-track">
        <button
          class="current-art current-art--button"
          type="button"
          :disabled="!canOpenCurrentAlbum"
          :aria-label="canOpenCurrentAlbum ? tl('Open album') : tl('Album is unavailable')"
          @click="openCurrentAlbum"
        >
          <ArtworkCover
            class="current-art__cover"
            :src="currentTrack?.coverUrl || ''"
            :alt="currentTrack?.albumTitle || currentTrack?.title || tl('Current track')"
            fallback-icon="solar:music-notes-bold"
            icon-size="1.5rem"
          />
        </button>
        <div class="current-track__meta">
          <button
            class="current-track__link current-track__link--title"
            type="button"
            :disabled="!canOpenCurrentAlbum"
            @click="openCurrentAlbum"
          >
            {{ currentTrack?.title || tl('Placeholder song') }}
          </button>
          <button
            class="current-track__link current-track__link--artist"
            type="button"
            :disabled="!canOpenCurrentArtist"
            @click="openCurrentArtist"
          >
            {{ currentTrack?.artistName || tl('Current Session') }}
          </button>
        </div>
        <button
          v-if="user && currentTrack"
          class="current-like"
          type="button"
          :class="{ 'current-like--active': isLiked(currentTrack.id) }"
          :disabled="likePending"
          :aria-pressed="isLiked(currentTrack.id)"
          :aria-label="tl('Add to liked songs')"
          @click="onLikeCurrent"
        >
          <Icon :icon="getIcon('material-symbols:favorite')" class="ui-icon ui-icon--md current-like__icon" />
        </button>
      </div>

      <div class="player-controls">
        <div class="control-row">
          <button
            class="icon-button small"
            :class="{ active: isShuffleEnabled }"
            type="button"
            :aria-label="tl('Shuffle')"
            @click="toggleShuffle"
          >
            <Icon :icon="getIcon('solar:shuffle-bold')" class="ui-icon ui-icon--sm" />
          </button>
          <button class="icon-button small" type="button" :aria-label="tl('Previous')" :disabled="!hasPrevious" @click="playPrevious">
            <Icon :icon="getIcon('material-symbols:skip-previous-rounded')" class="ui-icon ui-icon--md" />
          </button>
          <button
            class="main-play"
            type="button"
            :aria-label="isPlaying ? tl('Pause') : tl('Play')"
            :disabled="!streamUrl || isLoading"
            @click="togglePlay"
          >
            <Icon v-if="isPlaying" :icon="getIcon('material-symbols:pause-rounded')" class="ui-icon ui-icon--lg" />
            <Icon v-else :icon="getIcon('material-symbols:play-arrow-rounded')" class="ui-icon ui-icon--lg main-play__play-icon" />
          </button>
          <button class="icon-button small" type="button" :aria-label="tl('Next')" :disabled="!hasNext" @click="playNext">
            <Icon :icon="getIcon('material-symbols:skip-next-rounded')" class="ui-icon ui-icon--md" />
          </button>
          <button
            class="icon-button small"
            :class="{ active: repeatMode !== 'off' }"
            type="button"
            :aria-label="repeatLabel"
            @click="cycleRepeatMode"
          >
            <Icon
              :icon="getIcon(repeatMode === 'one' ? 'material-symbols:repeat-one-rounded' : 'material-symbols:repeat-rounded')"
              class="ui-icon ui-icon--sm"
            />
          </button>
        </div>
        <div class="progress-row">
          <span>{{ formatTime(currentTime) }}</span>
          <div class="progress-track" @click="seekFromClick">
            <TimelineCommentMarkers :markers="commentMarkers" />
            <div class="progress-buffered" :style="{ width: `${bufferedPercent}%` }" />
            <div class="progress-fill" :style="{ width: `${progressPercent}%` }" />
          </div>
          <span>{{ formatTime(duration) }}</span>
        </div>
      </div>

      <div class="volume-controls">
        <PlayerCommentsButton
          :is-open="isTrackCommentsOpen"
          :disabled="!currentTrack"
          @click="toggleTrackComments"
        />
        <button
          class="icon-button small"
          :class="{ active: isQueueVisible }"
          type="button"
          :aria-label="tl('Queue')"
          @click="toggleQueue"
        >
          <Icon :icon="getIcon('material-symbols:queue-music-rounded')" class="ui-icon ui-icon--sm" />
        </button>
        <button
          class="icon-button small"
          type="button"
          :aria-label="isMuted ? tl('Unmute') : tl('Mute')"
          @click="toggleMute"
          @wheel.prevent="setVolumeFromWheel"
        >
          <Icon
            :icon="getIcon(isMuted || volumePercent === 0 ? 'material-symbols:volume-off-rounded' : 'material-symbols:volume-up-rounded')"
            class="ui-icon ui-icon--sm"
            :class="{ 'ui-icon--muted': isMuted || volumePercent === 0 }"
          />
        </button>
        <div class="volume-track" @click="setVolumeFromClick">
          <div class="volume-fill" :style="{ width: `${isMuted ? 0 : volumePercent}%` }" />
        </div>
      </div>

      <aside v-if="isQueueVisible && queue.length" class="queue-panel">
        <strong>{{ tl('Queue') }}</strong>
        <button
          v-for="(track, index) in queue"
          :key="track.id"
          class="queue-item"
          :class="{ active: currentIndex === index }"
          type="button"
          @click="playByIndex(index)"
        >
          <span>{{ track.title }}</span>
          <small>{{ track.artistName || tl('Artist') }}<template v-if="track.duration != null"> • {{ formatDuration(track.duration) }}</template></small>
        </button>
      </aside>

      <audio
        ref="layoutAudio"
        class="layout-audio"
        :src="streamUrl || undefined"
        preload="metadata"
        @loadedmetadata="onLoadedMetadata"
        @timeupdate="onTimeUpdate"
        @progress="onProgress"
        @ended="onEnded"
        @error="onError"
      />
    </footer>
    <TrackContextMenu
      :visible="isLogoutMenuOpen"
      :x="logoutMenuPosition.x"
      :y="logoutMenuPosition.y"
      :items="logoutMenuItems"
      @close="closeLogoutMenu"
    />
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Icon } from '@iconify/vue'
import { useAuth } from '~/composables/useAuth'
import { useAppIcons } from '~/composables/useAppIcons'
import { formatDuration } from '~/utils/audioDuration'
import { useArtist } from '~/composables/useArtist'
import type { Genre } from '~/composables/useGenre'
import { usePlaylist, type Playlist, type PlaylistSong } from '~/composables/usePlaylist'
import type { Album } from '~/composables/useAlbum'
import { useEditorLayout } from '~/composables/useEditorLayout'
import { useLikedSongs } from '~/composables/useLikedSongs'
import type { SearchResults } from '~/composables/useSearch'

const { tl } = useLocalizedText()
const { t, toggleLocale, isRussian } = useAppLocale()

interface User {
  uid: string
  name: string
  email: string
  role?: string
  avatar_path?: string | null
  avatar_url?: string | null
  followers_count?: number
  is_following?: boolean
  artist?: {
    id: number
    name: string
  } | null
}

interface Artist {
  id: number
  name: string
  image_path?: string
  image_url?: string
}

const router = useRouter()
const route = useRoute()
const { getIcon } = useAppIcons()
const { logoutCurrent, logoutAll, getCurrentUser } = useAuth()
const { getCurrentArtist } = useArtist()
const { getMyPlaylists } = usePlaylist()
const { mediaUrl } = useMediaUrl()
const { isSidebarCollapsed, toggleSidebar } = useEditorLayout()
const { search, normalizeQuery } = useSearch()
const { likedSongIds, favoritesPlaylistId, refresh: refreshLiked, reset: resetLiked, isLiked, toggle: toggleLikedTrack } =
  useLikedSongs()
const { isMessengerOpen, toggleMessenger, totalUnreadCount, loadSidebarData, resetMessenger } = useMessenger()
const { isRightPanelOpen, closeRightPanel } = useRightPanel()
const { isTrackCommentsOpen, toggleTrackComments, markers: commentMarkers } = useTrackComments()

const user = ref<User | null>(null)
const artist = ref<Artist | null>(null)
const sidebarPlaylists = ref<Playlist[]>([])
const isLoadingUser = ref(true)
const isLoggingOut = ref(false)
const isLoadingAdmin = ref(false)
const isLoadingArtist = ref(false)
const isLoadingMyAlbums = ref(false)
const isLoadingPlaylist = ref(false)
const likePending = ref(false)
const layoutAudio = ref<HTMLAudioElement | null>(null)
const isLogoutMenuOpen = ref(false)
const logoutMenuPosition = ref({ x: 0, y: 0 })
const isDarkTheme = ref(false)
const isDiscoverGenreOpen = ref(false)
const searchFormRef = ref<HTMLElement | null>(null)
const searchQuery = ref('')
const searchLoading = ref(false)
const searchError = ref('')
const isSearchDropdownOpen = ref(false)
const quickResults = ref<SearchResults>({
  tracks: [],
  albums: [],
  playlists: [],
  artists: [],
  users: [],
  genres: [],
})
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null

const {
  currentTrack,
  currentCollectionType,
  currentCollectionId,
  streamUrl,
  isPlaying,
  isLoading,
  duration,
  currentTime,
  bufferedPercent,
  progressPercent,
  isShuffleEnabled,
  repeatMode,
  repeatLabel,
  isQueueVisible,
  queue,
  currentIndex,
  volumePercent,
  isMuted,
  hasPrevious,
  hasNext,
  setAudioElement,
  playByIndex,
  playNext,
  playPrevious,
  togglePlay,
  seekToPercent,
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
} = useAudioPlayer()

const displayName = computed(() => user.value?.name || tl('Listener'))
const normalizedSearchQuery = computed(() => normalizeQuery(searchQuery.value))
const quickAlbumCoverMap = computed(() => {
  const map = new Map<number, string>()

  quickResults.value.albums.forEach((album) => {
    map.set(album.id, album.cover_image_url || mediaUrl(album.cover_image_path))
  })

  return map
})
const quickAlbumCoverById = (albumId: number) => quickAlbumCoverMap.value.get(albumId) || ''
const formatTrackCount = (count: number) => {
  if (!isRussian.value) {
    return `${count} ${count === 1 ? 'track' : 'tracks'}`
  }

  if (count % 10 === 1 && count % 100 !== 11) {
    return `${count} трек`
  }

  if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) {
    return `${count} трека`
  }

  return `${count} треков`
}
const visibleQuickPlaylists = computed(() =>
  quickResults.value.playlists.filter((playlist) => playlist.title !== FAVORITES_PLAYLIST_TITLE)
)
const hasQuickResults = computed(
  () =>
    quickResults.value.tracks.length > 0
    || quickResults.value.albums.length > 0
    || visibleQuickPlaylists.value.length > 0
    || quickResults.value.artists.length > 0
    || quickResults.value.users.length > 0
    || quickResults.value.genres.length > 0
)
const showSearchDropdown = computed(
  () => isSearchDropdownOpen.value && !!normalizedSearchQuery.value
)
const displayRole = computed(() => {
  if (artist.value?.name) {
    return `${artist.value.name}`
  }

  return user.value?.role || tl('user')
})
const favoritesLibrarySubtitle = computed(() => {
  return likedSongIds.value.length
    ? formatTrackCount(likedSongIds.value.length)
    : tl('empty')
})
const avatarUrl = computed(() => user.value?.avatar_url || mediaUrl(user.value?.avatar_path))
const canOpenCurrentAlbum = computed(() => !!currentTrack.value?.albumId && !!currentTrack.value?.artistId)
const canOpenCurrentArtist = computed(() => !!currentTrack.value?.artistId)
const playingPlaylistId = computed(() => {
  if (currentCollectionType.value !== 'playlist' || !currentCollectionId.value) {
    return null
  }

  return String(currentCollectionId.value)
})
const playlistCover = (playlist?: Playlist | null) => {
  if (!playlist) {
    return ''
  }

  return (playlist as any).cover_image_url || mediaUrl(playlist.cover_image_path)
}
const compactLibraryItems = computed(() => {
  const items: Array<{
    id: string | number
    title: string
    to: string
    coverUrl: string
    fallbackIcon: string
    kind: 'liked' | 'playlist'
    active: boolean
  }> = []

  if (favoritesPlaylistId.value) {
    const favoritesPath = `/playlists/${favoritesPlaylistId.value}`
    items.push({
      id: `favorites-${favoritesPlaylistId.value}`,
      title: FAVORITES_PLAYLIST_TITLE,
      to: favoritesPath,
      coverUrl: '',
      fallbackIcon: 'material-symbols:favorite',
      kind: 'liked',
      active: route.path === favoritesPath,
    })
  }

  sidebarPlaylists.value
    .filter((playlist) => playlist.id !== favoritesPlaylistId.value)
    .slice(0, 3)
    .forEach((playlist, index) => {
      items.push({
        id: playlist.id,
        title: playlist.title,
        to: `/playlists/${playlist.id}`,
        coverUrl: playlistCover(playlist),
        fallbackIcon: 'solar:music-note-2-bold',
        kind: 'playlist',
        active: route.path === `/playlists/${playlist.id}`,
      })
    })

  return items
})
const expandedLibraryItems = computed(() => {
  const items: Array<{
    id: string | number
    title: string
    subtitle: string
    to: string
    coverUrl: string
    fallbackIcon: string
    kind: 'liked' | 'playlist'
    active: boolean
    playing: boolean
  }> = []

  if (favoritesPlaylistId.value) {
    const favoritesPath = `/playlists/${favoritesPlaylistId.value}`
    items.push({
      id: favoritesPlaylistId.value,
      title: tl('Liked Songs'),
      subtitle: `${tl('Playlist')} • ${favoritesLibrarySubtitle.value}`,
      to: favoritesPath,
      coverUrl: '',
      fallbackIcon: 'material-symbols:favorite',
      kind: 'liked',
      active: route.path === favoritesPath,
      playing: playingPlaylistId.value === String(favoritesPlaylistId.value),
    })
  }

  sidebarPlaylists.value
    .filter((playlist) => playlist.id !== favoritesPlaylistId.value)
    .forEach((playlist) => {
      const playlistPath = `/playlists/${playlist.id}`

      items.push({
        id: playlist.id,
        title: playlist.title,
        subtitle: tl('Playlist'),
        to: playlistPath,
        coverUrl: playlistCover(playlist),
        fallbackIcon: 'solar:music-note-2-bold',
        kind: 'playlist',
        active: route.path === playlistPath,
        playing: playingPlaylistId.value === String(playlist.id),
      })
    })

  return items
})
const userInitials = computed(() => {
  const name = displayName.value.trim()

  if (!name) {
    return 'U'
  }

  return name
    .split(' ')
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
})

const goBack = () => router.back()
const goForward = () => window.history.forward()
const closeLogoutMenu = () => {
  isLogoutMenuOpen.value = false
}

const clearQuickResults = () => {
  quickResults.value = {
    tracks: [],
    albums: [],
    playlists: [],
    artists: [],
    users: [],
    genres: [],
  }
}

const closeSearchDropdown = () => {
  isSearchDropdownOpen.value = false
}

const openSearchDropdown = () => {
  if (normalizedSearchQuery.value) {
    isSearchDropdownOpen.value = true
  }
}

const goToSearchResults = async (query = normalizedSearchQuery.value) => {
  const trimmed = normalizeQuery(query)

  if (!trimmed) {
    clearQuickResults()
    closeSearchDropdown()
    return
  }

  await router.push({
    path: '/search',
    query: {
      q: trimmed,
    },
  })

  closeSearchDropdown()
}

const submitSearch = async () => {
  await goToSearchResults()
}

const goToAlbumResult = async (album: Album) => {
  if (!album.artist?.id) {
    return
  }

  await router.push(`/albums/${album.artist.id}/${album.id}`)
  closeSearchDropdown()
}

const goToTrackResult = async (track: PlaylistSong) => {
  if (track.artist?.id && track.album?.id) {
    await router.push(`/albums/${track.artist.id}/${track.album.id}`)
    closeSearchDropdown()
    return
  }

  if (track.artist?.id) {
    await router.push(`/albums/${track.artist.id}`)
    closeSearchDropdown()
  }
}

const goToPlaylistResult = async (playlist: Playlist) => {
  await router.push(`/playlists/${playlist.id}`)
  closeSearchDropdown()
}

const goToArtistResult = async (resultArtist: Artist) => {
  await router.push(`/albums/${resultArtist.id}`)
  closeSearchDropdown()
}

const goToUserResult = async (resultUser: User) => {
  await router.push(`/users/${resultUser.uid}`)
  closeSearchDropdown()
}

const goToGenreResult = async (genreName: string) => {
  await router.push(`/genres/${encodeURIComponent(genreName)}`)
  closeSearchDropdown()
}

const runQuickSearch = async () => {
  const query = normalizedSearchQuery.value

  if (!query) {
    searchError.value = ''
    clearQuickResults()
    closeSearchDropdown()
    return
  }

  searchLoading.value = true
  searchError.value = ''

  try {
    const results = await search(query, { limit: 3 })
    quickResults.value = {
      tracks: results.tracks,
      albums: results.albums,
      playlists: results.playlists,
      artists: results.artists,
      users: results.users,
      genres: results.genres,
    }
    isSearchDropdownOpen.value = true
  } catch (error: any) {
    console.error('Quick search error:', error)
    searchError.value = error?.data?.message || tl('Could not search right now')
    clearQuickResults()
    isSearchDropdownOpen.value = true
  } finally {
    searchLoading.value = false
  }
}

const onWindowPointerDown = (event: MouseEvent) => {
  if (!searchFormRef.value?.contains(event.target as Node)) {
    closeSearchDropdown()
  }
}

const openLogoutMenu = (event: MouseEvent) => {
  if (isLogoutMenuOpen.value) {
    closeLogoutMenu()
    return
  }

  const target = event.currentTarget as HTMLElement | null

  if (!target) {
    return
  }

  const rect = target.getBoundingClientRect()
  logoutMenuPosition.value = {
    x: rect.right - 220,
    y: rect.bottom + 8,
  }
  isLogoutMenuOpen.value = true
}

const logoutMenuItems = computed(() => ([
  {
    key: 'logout-all',
    label: tl('Log out everywhere'),
    icon: 'solar:logout-3-bold',
    action: async () => {
      isLoggingOut.value = true
      try {
        await logoutAll()
        resetLiked()
      } finally {
        isLoggingOut.value = false
      }
    },
  },
  {
    key: 'logout-current',
    label: tl('Log out of this session'),
    icon: 'solar:logout-2-bold',
    action: async () => {
      isLoggingOut.value = true
      try {
        await logoutCurrent()
        resetLiked()
      } finally {
        isLoggingOut.value = false
      }
    },
  },
]))

const openLibraryItem = async (to: string) => {
  await router.push(to)
}

const playSidebarPlaylist = async (item: {
  id: string | number
  to: string
}) => {
  await router.push({
    path: item.to,
    query: {
      ...route.query,
      autoplay: '1',
    },
  })
}

const openCurrentAlbum = async () => {
  if (!currentTrack.value?.albumId || !currentTrack.value?.artistId) {
    return
  }

  await router.push(`/albums/${currentTrack.value.artistId}/${currentTrack.value.albumId}`)
}

const openCurrentArtist = async () => {
  if (!currentTrack.value?.artistId) {
    return
  }

  await router.push(`/albums/${currentTrack.value.artistId}`)
}

const applyTheme = (dark: boolean) => {
  if (typeof document === 'undefined') {
    return
  }

  document.documentElement.dataset.theme = dark ? 'dark' : 'light'
}

const toggleTheme = () => {
  isDarkTheme.value = !isDarkTheme.value
  applyTheme(isDarkTheme.value)

  if (typeof window !== 'undefined') {
    window.localStorage.setItem('jazzify-theme', isDarkTheme.value ? 'dark' : 'light')
  }
}

const loadSidebarPlaylists = async () => {
  if (!user.value) {
    sidebarPlaylists.value = []
    return
  }

  try {
    sidebarPlaylists.value = await getMyPlaylists()
  } catch (error) {
    console.error('Sidebar playlists loading error:', error)
    sidebarPlaylists.value = []
  }
}

const navigateWithState = async (path: string, state: typeof isLoadingAdmin) => {
  state.value = true
  try {
    await router.push(path)
  } catch (error) {
    console.error('Navigation error:', error)
  } finally {
    state.value = false
  }
}

const onAdminPanel = () => navigateWithState('/admin/users', isLoadingAdmin)

const openDiscoverGenre = () => {
  isDiscoverGenreOpen.value = true
}

const closeDiscoverGenre = () => {
  isDiscoverGenreOpen.value = false
}

const openDiscoverGenrePage = async (genre: string) => {
  closeDiscoverGenre()
  await router.push(`/discover?genre=${encodeURIComponent(genre)}`)
}
const onBecomeArtist = () => navigateWithState('/create-artist', isLoadingArtist)
const onCreatePlaylist = () => navigateWithState('/create-playlist', isLoadingPlaylist)
const onMyAlbums = () => {
  if (!artist.value?.id) {
    return
  }

  return navigateWithState(`/albums/${artist.value.id}`, isLoadingMyAlbums)
}
const openOwnUserPage = async () => {
  if (!user.value?.uid) {
    return
  }

  await router.push(`/users/${user.value.uid}`)
}
const openProfileEditor = async () => {
  await router.push('/profile/edit')
}
const openOwnArtistPage = async () => {
  if (!artist.value?.id) {
    return
  }

  await router.push(`/albums/${artist.value.id}`)
}

const onLikeCurrent = async () => {
  if (!currentTrack.value || likePending.value) {
    return
  }

  likePending.value = true
  try {
    await toggleLikedTrack(currentTrack.value.id)
  } finally {
    likePending.value = false
  }
}

const canHandleHotkeys = (target: EventTarget | null) => {
  if (!(target instanceof HTMLElement)) {
    return true
  }

  const tagName = target.tagName
  return tagName !== 'INPUT' && tagName !== 'TEXTAREA' && tagName !== 'SELECT' && !target.isContentEditable
}

const onWindowKeydown = async (event: KeyboardEvent) => {
  if (event.key === 'Escape' && isSearchDropdownOpen.value) {
    closeSearchDropdown()
    return
  }

  if (event.key === 'Escape' && isDiscoverGenreOpen.value) {
    closeDiscoverGenre()
    return
  }

  if (event.key === 'Escape' && isRightPanelOpen.value) {
    closeRightPanel()
    return
  }

  if (!isSidebarCollapsed.value || !canHandleHotkeys(event.target)) {
    return
  }

  const key = event.key.toLowerCase()

  if (key === 'h') {
    event.preventDefault()
    await router.push('/')
    return
  }

  if (key === 'p') {
    event.preventDefault()
    await router.push('/playlists')
    return
  }

  if (key === 'a') {
    event.preventDefault()
    if (artist.value?.id) {
      await router.push(`/albums/${artist.value.id}`)
    }
    return
  }

  if (/^[1-4]$/.test(key)) {
    const item = compactLibraryItems.value[Number(key) - 1]

    if (item) {
      event.preventDefault()
      await router.push(item.to)
    }
  }
}

const seekFromClick = (event: MouseEvent) => {
  const target = event.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  const percent = ((event.clientX - rect.left) / rect.width) * 100

  seekToPercent(percent)
}

const setVolumeFromClick = (event: MouseEvent) => {
  const target = event.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  const nextVolume = (event.clientX - rect.left) / rect.width

  setVolume(nextVolume)
}

const setVolumeFromWheel = (event: WheelEvent) => {
  const step = event.deltaY < 0 ? 0.05 : -0.05
  const currentVolume = isMuted.value ? 0 : volumePercent.value / 100
  const nextVolume = Math.min(1, Math.max(0, currentVolume + step))

  setVolume(nextVolume)
}

const loadAccountState = async (options: { reloadLibrary?: boolean } = {}) => {
  try {
    user.value = await getCurrentUser()
    artist.value = user.value ? await getCurrentArtist() : null

    if (user.value && options.reloadLibrary) {
      await refreshLiked()
      await loadSidebarPlaylists()
      await loadSidebarData(true)
    } else if (!user.value) {
      resetLiked()
      resetMessenger()
      sidebarPlaylists.value = []
    }
  } catch (error) {
    console.error('Profile loading error:', error)
    user.value = null
    resetLiked()
    resetMessenger()
    sidebarPlaylists.value = []
  }
}

const onProfileUpdated = async () => {
  await loadAccountState()
}

onMounted(async () => {
  setAudioElement(layoutAudio.value)

  if (typeof window !== 'undefined') {
    const savedTheme = window.localStorage.getItem('jazzify-theme')
    isDarkTheme.value = savedTheme === 'dark'
    applyTheme(isDarkTheme.value)
  }

  try {
    await loadAccountState({ reloadLibrary: true })
  } finally {
    isLoadingUser.value = false
  }

  window.addEventListener('keydown', onWindowKeydown)
  window.addEventListener('mousedown', onWindowPointerDown)
  window.addEventListener('profile-updated', onProfileUpdated)
})

watch(
  () => route.fullPath,
  async () => {
    if (user.value) {
      await loadSidebarPlaylists()
    }
  }
)

watch(
  () => route.query.q,
  (value) => {
    const nextQuery = normalizeQuery(String(value || ''))

    if (nextQuery !== searchQuery.value) {
      searchQuery.value = nextQuery
    }
  },
  { immediate: true }
)

watch(searchQuery, (value) => {
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }

  if (!normalizeQuery(value)) {
    clearQuickResults()
    closeSearchDropdown()
    return
  }

  searchDebounceTimer = setTimeout(() => {
    void runQuickSearch()
  }, 500)
})

onUnmounted(() => {
  setAudioElement(null)
  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }
  window.removeEventListener('keydown', onWindowKeydown)
  window.removeEventListener('mousedown', onWindowPointerDown)
  window.removeEventListener('profile-updated', onProfileUpdated)
})
</script>

<style scoped>
:global(html),
:global(body),
:global(#__nuxt) {
  width: 100%;
  height: 100%;
  margin: 0;
  background: var(--color-bg);
  overflow: hidden;
}

:global(body) {
  min-width: 320px;
}

* {
  box-sizing: border-box;
}

.player-shell {
  --sidebar-width: 280px;
  --right-panel-width: 320px;
  --sidebar-collapsed-width: 104px;
  --current-sidebar-width: var(--sidebar-width);
  --current-right-panel-width: 0px;
  width: 100%;
  height: 100vh;
  height: 100dvh;
  display: grid;
  grid-template-columns: var(--current-sidebar-width) minmax(0, 1fr) var(--current-right-panel-width);
  grid-template-rows: minmax(0, 1fr) 88px;
  gap: 8px;
  padding: 8px;
  overflow: hidden;
  color: var(--color-text-main);
  background:
    var(--gradient-glow),
    var(--gradient-page);
  font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.sidebar,
.workspace,
.content-area,
.player-bar {
  background: var(--color-shell-surface);
  border: 1px solid var(--color-shell-border);
  border-radius: 18px;
  box-shadow: var(--shadow-card);
}

.player-shell--sidebar-collapsed {
  --current-sidebar-width: var(--sidebar-collapsed-width);
}

.player-shell--right-panel-open {
  --current-right-panel-width: var(--right-panel-width);
}

.sidebar {
  grid-row: 1 / 2;
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-height: 0;
  padding: 20px;
  background: var(--color-sidebar-surface);
  border: 1px solid var(--color-shell-border);
}

.player-shell__right-panel {
  grid-column: 3 / 4;
  grid-row: 1 / 2;
  min-width: 0;
}

.sidebar--collapsed {
  align-items: center;
  gap: 12px;
  padding: 16px 10px;
}

.sidebar--collapsed .brand :deep(.app-brand__title),
.sidebar--collapsed .nav-item span:last-child,
.sidebar--collapsed .sidebar-create-playlist__label,
.sidebar--collapsed .account-panel__meta {
  display: none;
}

.sidebar--collapsed .sidebar-header,
.sidebar--collapsed .account-panel {
  width: 100%;
}

.sidebar--collapsed .sidebar-header {
  position: relative;
  justify-content: center;
  min-height: 42px;
}

.sidebar--collapsed .brand {
  margin: 0 auto;
}

.sidebar--collapsed .nav-item {
  justify-content: center;
  width: 56px;
  min-height: 56px;
  padding: 0;
  border-radius: 14px;
}

.sidebar--collapsed .account-panel {
  justify-content: center;
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.sidebar-toggle {
  width: 32px;
  height: 32px;
}

.sidebar--collapsed .sidebar-toggle {
  position: absolute;
  right: 0;
  width: auto;
  height: auto;
  padding: 0;
  background: transparent;
  border: 0;
  border-radius: 0;
  box-shadow: none;
}

.brand {
  width: max-content;
}

.sidebar-utility-actions {
  display: grid;
  gap: 12px;
  margin-top: auto;
}

.sidebar-create-playlist {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0;
  border: 0;
  color: var(--color-text-muted);
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.sidebar-create-playlist:hover {
  color: var(--color-primary);
}

.sidebar-create-playlist:disabled {
  opacity: 0.58;
  cursor: wait;
}

.sidebar-create-playlist__icon {
  width: 36px;
  height: 36px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border: 1px solid var(--button-primary-border);
  box-shadow: var(--shadow-primary);
}

.sidebar-create-playlist__label {
  color: var(--color-text-muted);
  font-size: 0.95rem;
  font-weight: 700;
}

.sidebar--collapsed .sidebar-utility-actions {
  width: 100%;
}

.sidebar--collapsed .sidebar-create-playlist {
  justify-content: center;
}

.workspace {
  min-width: 0;
  min-height: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 8px;
  background: transparent;
  border: 0;
  box-shadow: none;
  border-radius: 0;
}

.avatar,
.icon-button,
.main-play {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  border-radius: 50%;
}

.nav-list,
.library-panel,
.player-controls {
  display: grid;
}

.nav-list {
  gap: 6px;
}

.sidebar--collapsed .nav-list {
  gap: 2px;
}

.nav-item,
.icon-button,
.pill-button,
.main-play,
.control-row button {
  border: 0;
  font: inherit;
  cursor: pointer;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 14px;
  min-height: 52px;
  padding: 0 14px;
  color: var(--color-text-main);
  background: transparent;
  border-radius: 12px;
  font-weight: 700;
  text-align: left;
}

.nav-item.active {
  color: var(--color-text-main);
  background: var(--color-library-item-active);
  box-shadow: inset 0 0 0 1px var(--color-library-item-active-border);
}

.nav-item:hover {
  color: var(--color-text-main);
  background: var(--color-library-item-hover);
}

.nav-icon {
  width: 28px;
  height: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: currentColor;
}

.sidebar .nav-icon .ui-icon,
.sidebar .section-title .nav-icon .ui-icon {
  transform: scale(1.65);
  transform-origin: center;
}

.sidebar .nav-item .ui-icon {
  transform: scale(1.8);
}

.ui-icon {
  display: block;
  flex: 0 0 auto;
}

.ui-icon--sm {
  font-size: 1.05rem;
}

.ui-icon--md {
  font-size: 1.2rem;
}

.ui-icon--lg {
  font-size: 1.5rem;
}

.ui-icon--muted {
  opacity: 0.4;
}

.library-panel {
  align-content: start;
  gap: 10px;
  min-height: 0;
}

.library-list {
  min-height: 0;
  display: grid;
  gap: 2px;
  overflow: auto;
  padding-right: 4px;
}

.library-panel--compact {
  margin-top: 0;
}

.compact-group {
  display: grid;
  gap: 6px;
}

.compact-shortcuts {
  display: grid;
  gap: 2px;
  justify-items: center;
}

.mini-nav-button {
  width: 100%;
  min-height: 52px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 12px;
  color: var(--color-text-main);
  background: transparent;
  text-decoration: none;
  cursor: pointer;
}

.mini-nav-button:hover {
  background: var(--color-library-item-hover);
}

.mini-nav-button--active {
  background: var(--color-library-item-active);
  box-shadow: inset 0 0 0 1px var(--color-library-item-active-border);
}

.mini-library-grid {
  display: grid;
  gap: 10px;
  justify-items: center;
}

.mini-library-item-shell {
  position: relative;
  width: 56px;
  height: 56px;
  flex: 0 0 56px;
}

.mini-library-item {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 8px;
  background: transparent;
  border: 0;
  box-shadow: none;
  text-decoration: none;
}

.mini-library-item:hover {
  background: var(--color-library-item-hover);
  box-shadow: inset 0 0 0 1px var(--color-library-item-active-border);
}

.mini-library-item--active {
  background: var(--color-library-item-active);
  box-shadow: inset 0 0 0 1px var(--color-library-item-active-border);
}

.mini-library-item__cover {
  width: 100%;
  height: 100%;
  border: 0;
  border-radius: inherit;
  transition: filter 0.15s ease;
}

.mini-library-item-shell:hover .mini-library-item__cover,
.mini-library-item-shell:focus-within .mini-library-item__cover {
  filter: brightness(0.6);
}

.mini-library-item__play {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: 1;
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 50%;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border: 1px solid var(--button-primary-border);
  box-shadow: var(--shadow-primary);
  opacity: 0;
  pointer-events: none;
  transform: translate(-50%, -50%) scale(0.92);
  transition: opacity 0.15s ease, transform 0.15s ease;
  cursor: pointer;
}

.mini-library-item-shell:hover .mini-library-item__play,
.mini-library-item-shell:focus-within .mini-library-item__play,
.mini-library-item__play:focus-visible {
  opacity: 1;
  pointer-events: auto;
  transform: translate(-50%, -50%) scale(1);
}

.section-title,
.account-panel,
.current-track,
.volume-controls,
.topbar-controls,
.profile-actions,
.control-row,
.progress-row {
  display: flex;
  align-items: center;
}

.section-title {
  gap: 10px;
  color: var(--color-text-main);
  font-weight: 800;
}

.section-title--link {
  padding: 0;
  border: 0;
  background: transparent;
  text-decoration: none;
  font: inherit;
  text-align: left;
  transition: color 0.15s;
  cursor: pointer;
}

.section-title--link:hover {
  color: var(--color-primary);
}

.library-row {
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 8px;
  border-radius: 8px;
  color: inherit;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}

.library-row:hover,
.library-row--active {
  background: var(--color-library-item-hover);
}

.library-row--active {
  background: var(--color-library-item-active);
  box-shadow: inset 0 0 0 1px var(--color-library-item-active-border);
}

.library-row__cover {
  width: 48px;
  height: 48px;
  overflow: hidden;
  border-radius: 4px;
  border: 0;
}

.library-row__meta {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.library-row__meta strong,
.library-row__meta span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.library-row__meta strong {
  color: var(--color-text-main);
  font-size: 0.98rem;
}

.library-row__meta strong.library-row__title--playing {
  color: var(--color-primary);
}

.library-row__play {
  position: relative;
  z-index: 1;
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 50%;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border: 1px solid var(--button-primary-border);
  box-shadow: var(--shadow-primary);
  opacity: 0;
  pointer-events: none;
  transform: translateX(4px);
  transition: opacity 0.15s, transform 0.15s;
  cursor: pointer;
}

.library-row:hover .library-row__play,
.library-row__play:focus-visible {
  opacity: 1;
  pointer-events: auto;
  transform: translateX(0);
}

.library-row__meta span,
.account-panel span,
.current-track span,
.progress-row span {
  color: var(--color-text-muted);
}

.account-panel {
  width: 100%;
  margin-top: 0;
  padding-top: 14px;
  border-top: 1px solid var(--color-border);
}

.account-panel__profile {
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
}

.account-panel__meta,
.current-track__meta {
  display: grid;
  min-width: 0;
  gap: 1px;
}

.account-panel__name {
  color: var(--color-text-main);
  font-weight: 700;
  cursor: pointer;
}

.account-panel__role-link {
  width: fit-content;
  max-width: 100%;
  padding: 0;
  border: 0;
  color: var(--color-text-muted);
  background: transparent;
  text-align: left;
  font: inherit;
  line-height: inherit;
  cursor: pointer;
}

.account-panel__name:hover,
.account-panel__role-link:hover {
  text-decoration: underline;
}

.account-panel__meta {
  margin-left: 7px;
}

.current-track__link {
  width: fit-content;
  max-width: 100%;
  padding: 0;
  border: 0;
  color: inherit;
  background: transparent;
  text-align: left;
  font: inherit;
  cursor: pointer;
}

.current-track__link:disabled {
  cursor: default;
}

.current-track__link--title {
  color: var(--color-text-main);
  font-weight: 700;
}

.current-track__link--artist {
  color: var(--color-text-muted);
}

.current-track__link:not(:disabled):hover {
  text-decoration: underline;
}

.avatar {
  width: 40px;
  height: 40px;
  overflow: hidden;
  color: var(--color-avatar-text);
  background: var(--color-avatar-bg);
  font-weight: 900;
}

.avatar-image {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

.content-area {
  min-width: 0;
  min-height: 0;
  height: 100%;
  overflow: auto;
  background-color: var(--color-content-bg);
  background:
    var(--gradient-glow),
    var(--gradient-page);
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 2;
  display: grid;
  grid-template-columns: auto minmax(220px, 420px) 1fr;
  gap: 16px;
  align-items: center;
  min-height: 64px;
  padding: 12px 20px;
  background: var(--color-topbar-surface);
  border-bottom: 1px solid var(--color-topbar-border);
  backdrop-filter: blur(16px);
}

.topbar-controls,
.profile-actions {
  gap: 8px;
}

.profile-actions {
  justify-content: flex-end;
  flex-wrap: wrap;
}

.icon-button {
  width: 38px;
  height: 38px;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  border: 1px solid var(--button-control-border);
  box-shadow: var(--shadow-soft);
}

.icon-button.small {
  width: 32px;
  height: 32px;
  color: var(--button-control-text);
  font-size: 0.72rem;
  font-weight: 900;
}

.icon-button--messenger {
  position: relative;
}

.icon-button__badge {
  position: absolute;
  top: -3px;
  right: -3px;
  min-width: 18px;
  min-height: 18px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border: 1px solid var(--button-primary-border);
  font-size: 0.7rem;
  font-weight: 900;
  line-height: 1;
}

.locale-toggle {
  font-size: 0.78rem;
  font-weight: 900;
  letter-spacing: 0.04em;
}

.icon-button.small.active {
  color: var(--button-control-active-text);
  background: var(--button-control-active-bg);
  border-color: var(--button-control-active-border);
}

.control-row .icon-button,
.main-play {
  border: 1px solid var(--color-player-control-border);
}

.icon-button:hover:not(:disabled) {
  background: var(--button-control-hover);
}

.icon-button.active:hover:not(:disabled) {
  background: var(--button-control-active-hover);
  border-color: var(--button-control-active-border);
}

.icon-button:disabled,
.pill-button:disabled {
  cursor: wait;
  opacity: 0.58;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  padding: 0 14px;
  color: var(--color-input-text);
  background: var(--color-input-bg);
  border: 1px solid var(--color-input-border);
  border-radius: 999px;
}

.search-shell {
  position: relative;
}

.search-box__icon {
  color: var(--color-text-soft);
}

.search-box input {
  width: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  color: var(--color-input-text);
  background: transparent;
  font: inherit;
}

.search-box input::placeholder {
  color: var(--color-input-placeholder);
}

.search-box:focus-within {
  border-color: var(--color-input-focus-border);
  box-shadow: 0 0 0 3px var(--color-input-focus-ring);
}

.search-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  left: 0;
  width: min(420px, calc(100vw - 32px));
  max-height: min(70vh, 560px);
  display: grid;
  gap: 12px;
  padding: 14px;
  overflow: auto;
  border: 1px solid var(--color-card-border);
  border-radius: 18px;
  background: var(--color-card-surface);
  box-shadow: var(--shadow-menu);
  z-index: 8;
}

.search-dropdown__state {
  color: var(--color-text-muted);
  font-size: 0.94rem;
}

.search-dropdown__state--error {
  color: var(--color-error-text);
}

.search-dropdown__section {
  display: grid;
  gap: 8px;
}

.search-dropdown__title {
  color: var(--color-text-main);
  font-size: 0.84rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.search-dropdown__item {
  width: 100%;
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr);
  gap: 12px;
  align-items: center;
  padding: 8px;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: var(--color-text-main);
  text-align: left;
  font: inherit;
  cursor: pointer;
}

.search-dropdown__item:hover {
  background: var(--color-library-item-hover);
}

.search-dropdown__cover,
.search-dropdown__genre-badge {
  width: 44px;
  height: 44px;
  border-radius: 12px;
}

.search-dropdown__genre-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border: 1px solid var(--button-primary-border);
  font-weight: 900;
}

.search-dropdown__copy {
  min-width: 0;
  display: grid;
  gap: 2px;
}

.search-dropdown__copy strong,
.search-dropdown__copy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.search-dropdown__copy span {
  color: var(--color-text-muted);
  font-size: 0.9rem;
}

.search-dropdown__all {
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 16px;
  border: 1px solid var(--button-secondary-border);
  border-radius: 999px;
  background: var(--button-secondary-bg);
  color: var(--button-secondary-text);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.search-dropdown__all:hover {
  background: var(--button-secondary-hover);
}

.pill-button {
  min-height: 36px;
  padding: 0 16px;
  color: var(--button-secondary-text);
  background: var(--button-secondary-bg);
  border: 1px solid var(--button-secondary-border);
  border-radius: 999px;
  font-weight: 800;
}

.pill-button.accent,
.main-play {
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  border-color: var(--button-primary-border);
  box-shadow: var(--shadow-primary);
}

.pill-button:hover:not(:disabled) {
  background: var(--button-secondary-hover);
}

.pill-button.accent:hover:not(:disabled),
.main-play:hover:not(:disabled) {
  background: var(--button-primary-hover);
}

.state-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  min-height: 260px;
  color: var(--color-text-muted);
}

.page-slot {
  min-height: 0;
  padding: 56px 48px 120px;
}

:global(.player-shell .page) {
  width: 100%;
  min-height: 100% !important;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 0 !important;
  background: transparent !important;
  font-family: inherit !important;
}

:global(.player-shell .page::before) {
  display: none !important;
}

:global(.player-shell .credentials-form) {
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
  min-height: 0 !important;
  padding: 0 !important;
  background: transparent !important;
  border: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
}

:global(.player-shell .credentials-form form) {
  width: 100%;
  margin: 0;
  align-items: flex-start;
  text-align: left;
  flex-direction: row;
  flex-wrap: wrap;
  justify-content: flex-start;
}

:global(.player-shell .credentials-form .title) {
  width: min(var(--editor-content-width, 780px), 100%);
  margin: 0 auto 18px;
  justify-self: start;
  align-self: start;
  text-align: left;
  font-size: 2rem;
  line-height: 1.18;
  letter-spacing: 0;
}

:global(.player-shell .credentials-form .submit-button),
:global(.player-shell .credentials-form .cancel-button) {
  margin-left: 0 !important;
  margin-right: 0 !important;
  width: auto !important;
  min-width: 170px;
  padding-left: 24px;
  padding-right: 24px;
}

:global(.player-shell .credentials-form .button-container) {
  display: contents !important;
}

:global(.player-shell .credentials-form .submit-button) {
  order: 20;
}

:global(.player-shell .credentials-form .cancel-button) {
  order: 21;
}

:global(.player-shell .credentials-form .field),
:global(.player-shell .credentials-form .selector-field),
:global(.player-shell .credentials-form .file-drop-container) {
  flex: 0 0 100%;
  width: 100%;
  max-width: 100%;
  margin-left: 0;
  margin-right: 0;
}

:global(.player-shell .credentials-form .field span) {
  margin-left: 0;
}

:global(.player-shell .credentials-form .inputCredentials),
:global(.player-shell .credentials-form .selector-field__select) {
  width: 100%;
  box-sizing: border-box;
}

:global(.player-shell .credentials-form .selector-field) {
  display: block;
}

:global(.player-shell .credentials-form .selector-field > span) {
  display: block;
  margin-bottom: 5px;
}

:global(.player-shell .table-form) {
  width: min(1200px, 100%);
  margin: 0 auto;
  padding: 0;
}

.player-bar {
  grid-column: 1 / -1;
  position: relative;
  display: grid;
  grid-template-columns: minmax(180px, 1fr) minmax(320px, 1.45fr) minmax(180px, 1fr);
  gap: 20px;
  align-items: center;
  min-height: 88px;
  padding: 12px 20px;
  background: var(--color-player-surface);
  border: 1px solid var(--color-shell-border);
}

.current-track {
  gap: 12px;
  min-width: 0;
}

.current-like {
  flex: 0 0 auto;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: 4px;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--color-text-soft);
  line-height: 0;
  cursor: pointer;
  transition: color 0.15s, background 0.15s, transform 0.1s;
}

.current-like__icon {
  font-size: 1.2rem;
  display: block;
  line-height: 1;
}

.current-like:hover:not(:disabled) {
  color: var(--color-accent);
  transform: scale(1.08);
}

.current-like:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.current-like--active {
  color: var(--color-accent);
}

.current-art {
  width: 50px;
  height: 50px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  border-radius: 6px;
}

.current-art--button {
  padding: 0;
  border: 1px solid var(--color-shell-border);
  cursor: pointer;
}

.current-art--button:disabled {
  cursor: default;
}

.current-art__cover {
  width: 100%;
  height: 100%;
}

.control-row {
  justify-content: center;
  gap: 12px;
}

.player-controls {
  gap: 8px;
}

.main-play {
  width: 40px;
  height: 40px;
}

.main-play__play-icon {
  margin-left: 2px;
}

.main-play:disabled {
  cursor: default;
  opacity: 0.55;
}

.progress-row {
  gap: 10px;
  min-width: 0;
}

.progress-row span {
  width: 36px;
  flex: 0 0 auto;
  font-size: 0.76rem;
}

.progress-track,
.volume-track {
  height: 5px;
  overflow: hidden;
  background: var(--progress-track-bg);
  border-radius: 999px;
}

.progress-track {
  width: 100%;
  position: relative;
  cursor: pointer;
}

.progress-buffered,
.progress-fill {
  position: absolute;
  inset: 0 auto 0 0;
  height: 100%;
}

.progress-buffered {
  background: var(--progress-buffered-bg);
}

.progress-fill {
  background: var(--progress-fill-bg);
}

:global([data-theme='dark']) .progress-track {
  background: rgba(62, 78, 102, 0.58);
}

.volume-controls {
  justify-content: flex-end;
  gap: 10px;
  color: var(--color-text-muted);
}

.volume-track {
  width: min(120px, 36vw);
}

.volume-fill {
  height: 100%;
  background: var(--progress-fill-bg);
}

.queue-panel {
  width: min(320px, calc(100vw - 24px));
  max-height: 320px;
  position: absolute;
  right: 20px;
  bottom: calc(100% + 10px);
  display: grid;
  gap: 8px;
  padding: 14px;
  overflow: auto;
  border: 1px solid var(--color-card-border);
  border-radius: 14px;
  background: var(--color-card-surface);
  box-shadow: var(--shadow-menu);
}

.messenger-backdrop {
  display: none;
}

.queue-item {
  display: grid;
  gap: 3px;
  padding: 10px 12px;
  border: 0;
  border-radius: 8px;
  color: var(--color-text-main);
  background: var(--color-surface);
  text-align: left;
  font: inherit;
  cursor: pointer;
}

.queue-item small {
  color: var(--color-text-muted);
}

.queue-item.active {
  background: var(--color-card-surface-hover);
  border: 1px solid var(--color-border-strong);
}

.queue-item:hover {
  background: var(--color-library-item-hover);
}

.loader {
  display: inline-block;
}

.loader {
  width: 20px;
  height: 20px;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-accent);
  border-radius: 50%;
}

.current-art__cover {
  width: 100%;
  height: 100%;
  border-radius: inherit;
}

.layout-audio {
  display: none;
}

@media (max-width: 1120px) {
  .page-slot {
    padding: 40px 32px 120px;
  }

  .topbar {
    grid-template-columns: auto minmax(0, 1fr);
    gap: 12px;
  }

  .search-shell {
    min-width: 0;
  }

  .profile-actions {
    grid-column: 1 / -1;
    justify-content: flex-start;
  }
}

@media (max-width: 920px) {
  .page-slot {
    padding: 28px 20px 120px;
  }

  .topbar {
    grid-template-columns: 1fr;
    gap: 12px;
    padding: 14px;
  }

  .topbar-controls {
    display: none;
  }

  .profile-actions {
    justify-content: flex-start;
    gap: 10px;
  }

  .pill-button {
    max-width: 100%;
  }
}

@media (max-width: 760px) {
  .player-shell {
    grid-template-columns: 1fr;
    grid-template-rows: auto minmax(0, 1fr) auto;
    padding: 0;
    gap: 0;
  }

  .sidebar {
    grid-row: auto;
    flex-direction: row;
    justify-content: space-between;
    border-radius: 0;
  }

  .page-slot {
    padding: 28px 18px 120px;
  }

  .brand :deep(.app-brand__title),
  .nav-list,
  .account-panel,
  .library-panel {
    display: none;
  }

  .content-area {
    border-radius: 0;
  }

  .topbar {
    grid-template-columns: 1fr;
    gap: 12px;
    padding: 14px;
  }

  .topbar-controls {
    display: none;
  }

  .profile-actions {
    justify-content: flex-start;
  }

  .player-shell__right-panel {
    position: fixed;
    left: 12px;
    right: 12px;
    top: 12px;
    bottom: 128px;
    z-index: 21;
  }

  .messenger-backdrop {
    position: fixed;
    inset: 0;
    z-index: 20;
    display: block;
    border: 0;
    background: rgba(4, 10, 20, 0.38);
  }

  .player-bar {
    grid-template-columns: 1fr;
    gap: 12px;
    min-height: 0;
    padding: 12px 14px;
    border-radius: 0;
  }

  .current-track {
    gap: 10px;
  }

  .current-art {
    width: 44px;
    height: 44px;
  }

  .control-row {
    gap: 10px;
  }

  .progress-row {
    gap: 8px;
  }

  .progress-row span {
    width: 32px;
    font-size: 0.72rem;
  }

  .volume-controls {
    display: none;
  }
}
</style>
