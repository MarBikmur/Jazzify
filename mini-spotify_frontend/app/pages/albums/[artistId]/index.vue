<template>
  <section class="artist-albums-page">
    <PageState v-if="loading" :message="tl('Loading albums...')" min-height="260px" />
    <PageState v-else-if="errorMessage" variant="error" :message="errorMessage" min-height="260px" />

    <template v-else>
      <section class="artist-hero">
        <NuxtLink
          v-if="isOwnArtist"
          to="/create-album"
          class="artist-hero__top-action artist-hero__action-button artist-hero__action-button--accent"
        >
          {{ tl('New album') }}
        </NuxtLink>
        <ArtworkCover
          class="artist-hero__image"
          :src="artist?.image_url || mediaUrl(artist?.image_path)"
          :alt="artist?.name || tl('Artist')"
          :fallback="artist?.name?.slice(0, 1).toUpperCase() || 'A'"
          shape="circle"
          text-size="3.2rem"
          icon-size="3rem"
        />
        <div class="artist-hero__copy">
          <span class="artist-hero__eyebrow">{{ tl('Artist') }}</span>
          <h1>{{ artist?.name || tl('Artist') }}</h1>
          <p>{{ artistSubtitle }}</p>
        </div>
        <div class="artist-hero__actions">
          <NuxtLink
            v-if="currentUser?.role === 'admin' && artist?.id"
            :to="`/admin/artists/${artist.id}`"
            class="artist-hero__action-button"
          >
            {{ tl('Edit') }}
          </NuxtLink>
          <FollowToggleButton
            v-if="canFollowArtist"
            class="artist-hero__follow"
            :active="!!artist?.is_following"
            :loading="followBusy"
            loading-label="Saving..."
            @click="toggleArtistFollow"
          />
          <button
            v-if="artist"
            type="button"
            class="artist-hero__share-button"
            :aria-label="tl('Share artist')"
            @click="shareArtist"
          >
            <Icon :icon="getIcon('solar:share-linear')" />
          </button>
        </div>
      </section>

      <section v-if="popularTracks.length" class="popular-section">
        <PageSectionHeader :title="tl('Popular')" />
        <div class="popular-head" aria-hidden="true">
          <span>#</span>
          <span>{{ tl('Track') }}</span>
          <span>{{ tl('Plays') }}</span>
          <span>{{ tl('Time') }}</span>
        </div>
        <div class="popular-list">
          <button
            v-for="(track, index) in popularTracks"
            :key="track.id"
            class="popular-row"
            type="button"
            @click="playPopularTrack(track)"
          >
            <span class="popular-row__index">{{ index + 1 }}</span>
            <span class="popular-row__track">
              <span class="popular-row__cover-shell">
                <ArtworkCover
                  class="popular-row__cover"
                  :src="track.albumCover"
                  :alt="track.title"
                  fallback="A"
                  fallback-icon="solar:music-notes-bold"
                  fallback-variant="playlist"
                  icon-size="1.1rem"
                />
                <span class="popular-row__play">
                  <Icon :icon="getIcon('material-symbols:play-arrow-rounded')" class="popular-row__play-icon" />
                </span>
              </span>
              <span class="popular-row__meta">
                <strong>{{ track.title }}</strong>
                <span>{{ track.albumTitle }}</span>
              </span>
            </span>
            <span class="popular-row__plays">{{ formatPlayCount(track.play_count) }}</span>
            <span class="popular-row__duration">{{ formatDuration(track.duration) }}</span>
          </button>
        </div>
      </section>

      <PageSectionHeader :title="pageTitle" :subtitle="formatAlbumsCount(albums.length)" />

      <div v-if="albums.length" class="albums-grid">
        <MediaTile
          v-for="album in albums"
          :key="album.id"
          :to="`/albums/${route.params.artistId}/${album.id}`"
          :title="album.title"
          :subtitle="formatTracksCount(album.songs?.length || 0)"
          :image-src="albumCover(album)"
          fallback="A"
        />
      </div>

      <PageState
        v-else
        variant="empty"
        :title="tl('No albums yet')"
        :message="tl('This artist has not released albums yet.')"
        min-height="260px"
      />
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@iconify/vue'
import type { Artist as ArtistEntity } from '~/composables/useArtist'
import { formatDuration } from '~/utils/audioDuration'

interface Album {
  id: number
  title: string
  cover_image_path?: string
  cover_image_url?: string
  songs?: {
    id: number
    title: string
    duration?: number | null
    play_count?: number | null
    audio_path?: string
    audio_url?: string
  }[]
}

interface PopularTrack {
  id: number
  title: string
  duration?: number | null
  play_count?: number | null
  artistId: string
  artistName: string
  albumId: number
  albumTitle: string
  albumCover: string
}

const route = useRoute()
const { tl } = useLocalizedText()
const { isRussian } = useAppLocale()
const { getIcon } = useAppIcons()
const { getArtistAlbums, getTrackStreamUrl } = useAlbum()
const { getArtist } = useArtist()
const { followArtist, unfollowArtist } = useLibrary()
const { mediaUrl } = useMediaUrl()
const { getCurrentUser } = useAuth()
const { prepareShare } = useMessenger()
const {
  currentTrack,
  currentCollectionType,
  currentCollectionId,
  setQueue,
  playTrack: playInLayout,
  togglePlay,
} = useAudioPlayer()

const albums = ref<Album[]>([])
const artist = ref<ArtistEntity | null>(null)
const loading = ref(false)
const errorMessage = ref('')
const currentUser = ref<{ uid?: string; role?: string } | null>(null)
const followBusy = ref(false)

const pluralizeRu = (count: number, one: string, few: string, many: string) => {
  if (count % 10 === 1 && count % 100 !== 11) {
    return `${count} ${one}`
  }

  if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) {
    return `${count} ${few}`
  }

  return `${count} ${many}`
}

const formatTracksCount = (count: number) => count === 1 ? `1 ${tl('track')}` : `${count} ${tl('tracks')}`
const formatAlbumsCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? '1 album' : `${count} albums`
  }

  return pluralizeRu(count, 'альбом', 'альбома', 'альбомов')
}
const formatFollowersCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? `1 ${tl('follower')}` : `${count} ${tl('followers')}`
  }

  return pluralizeRu(count, 'подписчик', 'подписчика', 'подписчиков')
}

const albumCover = (album: Album) => album.cover_image_url || mediaUrl(album.cover_image_path)
const popularTracks = computed<PopularTrack[]>(() =>
  albums.value
    .flatMap((album) =>
      (album.songs || []).map((song) => ({
        id: song.id,
        title: song.title,
        duration: song.duration ?? null,
        play_count: song.play_count ?? 0,
        artistId: String(route.params.artistId),
        artistName: artist.value?.name || tl('Artist'),
        albumId: album.id,
        albumTitle: album.title,
        albumCover: albumCover(album),
      }))
    )
    .sort((a, b) => {
      const playDelta = (b.play_count ?? 0) - (a.play_count ?? 0)
      if (playDelta !== 0) {
        return playDelta
      }

      return a.title.localeCompare(b.title)
    })
    .slice(0, 5)
)
const pageTitle = computed(() => {
  if (!artist.value?.name) {
    return tl('Albums')
  }

  return isRussian.value ? `${artist.value.name}: альбомы` : `${artist.value.name} albums`
})
const artistSubtitle = computed(() => {
  const albumsLabel = formatAlbumsCount(albums.value.length)
  const followersCount = artist.value?.followers_count ?? 0
  const followersLabel = formatFollowersCount(followersCount)

  return `${albumsLabel} • ${followersLabel}`
})
const isOwnArtist = computed(() => !!currentUser.value?.uid && !!artist.value?.user_uid && currentUser.value.uid === artist.value.user_uid)
const canFollowArtist = computed(() => !!currentUser.value?.uid && !isOwnArtist.value)
const formatPlayCount = (value?: number | null) => new Intl.NumberFormat('en-US').format(value ?? 0)

const toggleArtistFollow = async () => {
  if (!artist.value || followBusy.value) {
    return
  }

  followBusy.value = true
  errorMessage.value = ''

  try {
    const response = artist.value.is_following
      ? await unfollowArtist(artist.value.id)
      : await followArtist(artist.value.id)

    if (!response.success) {
      errorMessage.value = response.message || tl('Could not update artist follow state')
      return
    }

    artist.value = {
      ...artist.value,
      ...(response.data || {}),
      is_following: response.data?.is_following ?? !artist.value.is_following,
      followers_count: response.data?.followers_count ?? artist.value.followers_count ?? 0,
    }
  } finally {
    followBusy.value = false
  }
}

const playPopularTrack = async (track: PopularTrack) => {
  const isSameTrack =
    currentTrack.value?.id === track.id
    && String(currentTrack.value?.albumId || '') === String(track.albumId)
    && (
      (currentCollectionType.value === 'album' && String(currentCollectionId.value) === String(track.albumId))
      || currentCollectionType.value === null
    )

  if (isSameTrack) {
    await togglePlay()
    return
  }

  const queue = popularTracks.value.map((item) => ({
    id: item.id,
    title: item.title,
    duration: item.duration ?? null,
    playCount: item.play_count ?? 0,
    artistName: item.artistName,
    artistId: item.artistId,
    albumTitle: item.albumTitle,
    albumId: item.albumId,
    coverUrl: item.albumCover,
    resolveStreamUrl: () => getTrackStreamUrl(item.id),
  }))

  setQueue(queue, track.id)

  await playInLayout({
    id: track.id,
    title: track.title,
    duration: track.duration ?? null,
    playCount: track.play_count ?? 0,
    artistName: track.artistName,
    artistId: track.artistId,
    albumTitle: track.albumTitle,
    albumId: track.albumId,
    coverUrl: track.albumCover,
    resolveStreamUrl: () => getTrackStreamUrl(track.id),
  })
}

const shareArtist = async () => {
  if (!artist.value) {
    return
  }

  await prepareShare({
    type: 'artist',
    id: artist.value.id,
    title: artist.value.name,
    subtitle: artistSubtitle.value,
    image_url: artist.value.image_url || mediaUrl(artist.value.image_path),
  })
}

onMounted(async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const artistId = String(route.params.artistId)
    const [artistData, albumsData, userData] = await Promise.all([
      getArtist(artistId),
      getArtistAlbums(artistId),
      getCurrentUser(),
    ])

    artist.value = artistData
    albums.value = albumsData
    currentUser.value = userData
  } catch (error: any) {
    console.error('Artist albums loading error:', error)
    errorMessage.value = error?.data?.message || tl('Failed to load albums')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.artist-albums-page {
  width: 100%;
  min-height: 100%;
  color: var(--color-text-main);
  display: grid;
  gap: 24px;
}

.artist-hero {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  grid-template-areas: "image copy actions";
  gap: 18px;
  align-items: center;
  padding: 18px 20px;
  border: 1px solid var(--color-card-border);
  border-radius: var(--radius-card);
  background:
    radial-gradient(circle at top right, rgba(0, 163, 255, 0.16), transparent 34%),
    linear-gradient(180deg, var(--color-bg-soft) 0%, var(--color-surface) 100%);
  box-shadow: var(--color-card-shadow);
}

.artist-hero__image {
  grid-area: image;
  width: 132px;
  height: 132px;
}

.artist-hero__copy {
  grid-area: copy;
  display: grid;
  gap: 6px;
  padding-right: 124px;
  min-width: 0;
}

.artist-hero__copy h1,
.artist-hero__copy p {
  margin: 0;
}

.artist-hero__copy h1 {
  font-size: clamp(2rem, 4vw, 3.2rem);
  line-height: 0.96;
  overflow-wrap: anywhere;
}

.artist-hero__copy p {
  color: var(--color-text-muted);
  overflow-wrap: anywhere;
}

.artist-hero__eyebrow {
  color: var(--color-primary);
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.artist-hero__follow {
  align-self: end;
  justify-self: end;
}

.artist-hero__actions {
  grid-area: actions;
  display: flex;
  flex-direction: row;
  gap: 10px;
  align-items: center;
  justify-content: flex-end;
  align-self: end;
  flex-wrap: wrap;
}

.artist-hero__top-action {
  position: absolute;
  top: 18px;
  right: 20px;
}

.artist-hero__action-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 32px;
  padding: 0 14px;
  border: 1px solid var(--button-control-border);
  border-radius: 999px;
  color: var(--button-control-text);
  background: var(--button-control-bg);
  font: inherit;
  font-weight: 800;
  font-size: 0.82rem;
  cursor: pointer;
  text-decoration: none;
}

.artist-hero__action-button:hover {
  background: var(--button-control-hover);
}

.artist-hero__action-button--accent {
  border-color: var(--button-primary-bg);
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
}

.artist-hero__action-button--accent:hover {
  background: var(--button-primary-hover);
}

.artist-hero__share-button {
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

.artist-hero__share-button:hover {
  background: var(--button-control-hover);
}

.artist-hero__share-button :deep(svg) {
  font-size: 1rem;
}

.popular-section {
  display: grid;
  gap: 12px;
  max-width: 980px;
}

.popular-list {
  display: grid;
  gap: 2px;
}

.popular-head,
.popular-row {
  display: grid;
  grid-template-columns: 20px minmax(0, 1fr) 64px 48px;
  gap: 10px;
  align-items: center;
}

.popular-head {
  padding: 0 8px 4px;
  color: var(--color-text-muted);
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.popular-head span:nth-child(3),
.popular-head span:nth-child(4) {
  text-align: right;
}

.popular-row {
  padding: 6px 8px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--color-text-main);
  text-align: left;
  font: inherit;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.popular-row:hover {
  background: var(--color-library-item-hover);
}

.popular-row__index,
.popular-row__plays,
.popular-row__duration {
  color: var(--color-text-muted);
}

.popular-row__cover {
  width: 38px;
  height: 38px;
  border-radius: 9px;
}

.popular-row__cover-shell {
  position: relative;
  width: 38px;
  height: 38px;
  display: inline-flex;
}

.popular-row__track {
  min-width: 0;
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr);
  align-items: center;
  gap: 10px;
}

.popular-row__play {
  position: absolute;
  inset: 50% auto auto 50%;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  color: #fff;
  background: rgba(12, 24, 42, 0.78);
  opacity: 0;
  transform: translate(-50%, calc(-50% + 2px));
  transition: opacity 0.15s ease, transform 0.15s ease, background 0.15s ease;
}

.popular-row__play-icon {
  font-size: 1rem;
  line-height: 1;
}

.popular-row:hover .popular-row__play {
  opacity: 1;
  transform: translate(-50%, -50%);
}

.popular-row:hover .popular-row__play:hover {
  background: rgba(9, 18, 32, 0.92);
}

.popular-row__meta {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.popular-row__meta strong {
  font-size: 0.94rem;
}

.popular-row__meta span,
.popular-row__index,
.popular-row__plays,
.popular-row__duration {
  font-size: 0.82rem;
}

.popular-row__meta strong,
.popular-row__meta span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.popular-row__meta span {
  color: var(--color-text-muted);
}

.popular-row__plays,
.popular-row__duration {
  text-align: right;
}

.albums-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 18px;
}

@media (max-width: 1280px) {
  .artist-hero {
    grid-template-columns: 120px minmax(0, 1fr);
    grid-template-areas:
      "top top"
      "image copy"
      "actions actions";
    align-items: end;
  }

  .artist-hero__copy {
    padding-right: 0;
  }

  .artist-hero__actions {
    justify-content: flex-start;
    align-self: start;
  }

  .artist-hero__top-action {
    position: static;
    grid-area: top;
    justify-self: start;
  }
}

@media (max-width: 820px) {
  .artist-hero {
    grid-template-columns: 1fr;
    grid-template-areas:
      "top"
      "image"
      "copy"
      "actions";
    gap: 16px;
    align-items: start;
  }

  .artist-hero__copy {
    padding-right: 0;
  }

  .artist-hero__image,
  .artist-hero__actions,
  .artist-hero__follow {
    justify-self: start;
  }

  .artist-hero__actions {
    justify-content: flex-start;
  }

  .artist-hero__top-action {
    position: static;
  }

  .popular-row {
    grid-template-columns: 20px minmax(0, 1fr) 58px 44px;
    gap: 8px;
  }

  .popular-row__track {
    grid-template-columns: 36px minmax(0, 1fr);
    gap: 8px;
  }

  .popular-row__cover-shell,
  .popular-row__cover {
    width: 36px;
    height: 36px;
  }
}

@media (max-width: 560px) {
  .artist-hero {
    padding: 16px;
  }

  .artist-hero__image {
    width: 116px;
    height: 116px;
  }

  .artist-hero__copy {
    gap: 4px;
  }

  .artist-hero__copy h1 {
    font-size: clamp(2rem, 11vw, 2.8rem);
  }

  .artist-hero__actions {
    width: 100%;
    gap: 8px;
  }

  .artist-hero__action-button {
    min-height: 34px;
    padding: 0 12px;
  }

  .popular-head,
  .popular-row {
    grid-template-columns: 18px minmax(0, 1fr) 44px;
  }

  .popular-head span:nth-child(3) {
    display: none;
  }

  .popular-row__plays {
    display: none;
  }
}
</style>
