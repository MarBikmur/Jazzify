<template>
  <section class="user-library-page">
    <PageState v-if="loading" :message="tl('Loading profile...')" min-height="240px" />
    <PageState v-else-if="errorMessage" variant="error" :message="errorMessage" min-height="240px" />

    <template v-else-if="profile">
      <section class="user-library-hero">
        <div class="user-library-hero__main">
          <ArtworkCover
            class="user-library-hero__avatar"
            :src="profile.user.avatar_url || mediaUrl(profile.user.avatar_path)"
            :alt="profile.user.name"
            :fallback="profile.user.name.slice(0, 1).toUpperCase() || 'U'"
            shape="circle"
            text-size="3.2rem"
            icon-size="3rem"
          />

          <div class="user-library-hero__copy">
            <span class="user-library-hero__eyebrow">{{ isOwnProfile ? tl('Your public library') : tl('User library') }}</span>
            <h1>{{ profile.user.name }}</h1>
            <p>{{ heroSubtitle }}</p>
          </div>
        </div>

        <div class="user-library-hero__actions">
          <NuxtLink
            v-if="profile.user.artist?.id"
            class="user-library-hero__artist-link"
            :to="`/albums/${profile.user.artist.id}`"
          >
            {{ tl('Open artist page') }}
          </NuxtLink>
          <div class="user-library-hero__action-row">
            <NuxtLink
              v-if="isAdmin"
              class="user-library-hero__admin-link"
              :to="`/admin/users/${profile.user.uid}`"
            >
              {{ tl('Edit') }}
            </NuxtLink>
            <FollowToggleButton
              v-if="canFollowUser"
              class="user-library-hero__follow"
              :active="!!profile.user.is_following"
              :loading="followBusy"
              :loading-label="tl('Saving...')"
              @click="toggleUserFollow"
            />
            <button
              v-if="canMessageUser"
              type="button"
              class="user-library-hero__message"
              :disabled="messageBusy"
              @click="messageUser"
            >
              {{ messageBusy ? tl('Opening...') : tl('Message') }}
            </button>
          </div>
        </div>
      </section>

      <PageSectionHeader :title="tl('Library overview')" :subtitle="librarySubtitle" />

      <section class="library-section">
        <PageSectionHeader :title="tl('Playlists')" :count="profile.playlists.length" />
        <div v-if="profile.playlists.length" class="library-grid">
          <MediaTile
            v-for="playlist in profile.playlists"
            :key="`own-playlist-${playlist.id}`"
            :to="`/playlists/${playlist.id}`"
            :title="tl(playlist.title)"
            :subtitle="playlistSubtitle(playlist)"
            :image-src="playlist.cover_image_url || mediaUrl(playlist.cover_image_path)"
            fallback-icon="solar:music-note-2-bold"
            fallback-variant="playlist"
          />
        </div>
        <PageState v-else :message="tl('No playlists yet.')" min-height="120px" />
      </section>

      <PageState
        v-if="!hasSavedResults"
        :title="tl('Nothing public yet')"
        :message="tl('This library does not have followed users, artists, albums or playlists yet.')"
        min-height="180px"
      />

      <template v-else>
        <section v-if="profile.followed_users.length" class="library-section">
          <PageSectionHeader :title="tl('Users')" :count="profile.followed_users.length" />
          <div class="library-grid">
            <MediaTile
              v-for="followedUser in profile.followed_users"
              :key="`followed-user-${followedUser.uid}`"
              :to="`/users/${followedUser.uid}`"
              :title="followedUser.name"
              :subtitle="userSubtitle(followedUser)"
              :image-src="followedUser.avatar_url || mediaUrl(followedUser.avatar_path)"
              :fallback="followedUser.name.slice(0, 1).toUpperCase()"
              shape="circle"
            />
          </div>
        </section>

        <section v-if="profile.artists.length" class="library-section">
          <PageSectionHeader :title="tl('Artists')" :count="profile.artists.length" />
          <div class="library-grid">
            <MediaTile
              v-for="artist in profile.artists"
              :key="`artist-${artist.id}`"
              :to="`/albums/${artist.id}`"
              :title="artist.name"
              :subtitle="artistFollowersLabel(artist)"
              :image-src="artist.image_url || mediaUrl(artist.image_path)"
              :fallback="artist.name.slice(0, 1).toUpperCase()"
              shape="circle"
            />
          </div>
        </section>

        <section v-if="profile.albums.length" class="library-section">
          <PageSectionHeader :title="tl('Albums')" :count="profile.albums.length" />
          <div class="library-grid">
            <MediaTile
              v-for="album in profile.albums"
              :key="`album-${album.id}`"
              :to="album.artist?.id ? `/albums/${album.artist.id}/${album.id}` : ''"
              :title="album.title"
              :subtitle="album.artist?.name || tl('Artist')"
              :image-src="album.cover_image_url || mediaUrl(album.cover_image_path)"
              fallback="A"
            />
          </div>
        </section>

        <section v-if="profile.liked_playlists.length" class="library-section">
          <PageSectionHeader :title="tl('Saved playlists')" :count="profile.liked_playlists.length" />
          <div class="library-grid">
            <MediaTile
              v-for="playlist in profile.liked_playlists"
              :key="`liked-playlist-${playlist.id}`"
              :to="`/playlists/${playlist.id}`"
              :title="tl(playlist.title)"
              :subtitle="playlistSubtitle(playlist)"
              :image-src="playlist.cover_image_url || mediaUrl(playlist.cover_image_path)"
              fallback-icon="solar:music-note-2-bold"
              fallback-variant="playlist"
            />
          </div>
        </section>
      </template>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { Artist } from '~/composables/useArtist'
import { FAVORITES_PLAYLIST_TITLE } from '~/composables/useLikedSongs'
import { useAppLocale } from '~/composables/useAppLocale'
import { useLocalizedText } from '~/composables/useLocalizedText'
import type { Playlist } from '~/composables/usePlaylist'
import type { PublicProfileResponse, PublicProfileUser } from '~/composables/useUserProfile'

const route = useRoute()
const { isRussian } = useAppLocale()
const { tl } = useLocalizedText()
const { getCurrentUser } = useAuth()
const { getPublicProfile } = useUserProfile()
const { followUser, unfollowUser } = useUserSocial()
const { mediaUrl } = useMediaUrl()
const { openConversationWithUser } = useMessenger()

const loading = ref(true)
const errorMessage = ref('')
const profile = ref<PublicProfileResponse | null>(null)
const currentUserUid = ref('')
const currentUserRole = ref('')
const followBusy = ref(false)
const messageBusy = ref(false)

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

const isOwnProfile = computed(() => !!profile.value?.user.uid && profile.value.user.uid === currentUserUid.value)
const isAdmin = computed(() => currentUserRole.value === 'admin')
const canFollowUser = computed(() => !!profile.value?.user.uid && !isOwnProfile.value)
const canMessageUser = computed(() => !!profile.value?.user.uid && !isOwnProfile.value)
const hasSavedResults = computed(() => {
  return !!profile.value && (
    profile.value.followed_users.length > 0
    || profile.value.artists.length > 0
    || profile.value.albums.length > 0
    || profile.value.liked_playlists.length > 0
  )
})
const librarySubtitle = computed(() => {
  if (!profile.value) {
    return ''
  }

  const total =
    profile.value.playlists.length
    + profile.value.followed_users.length
    + profile.value.artists.length
    + profile.value.albums.length
    + profile.value.liked_playlists.length

  return formatItemsCount(total)
})
const heroSubtitle = computed(() => {
  if (!profile.value) {
    return ''
  }

  const followersCount = profile.value.user.followers_count ?? 0
  return formatFollowersCount(followersCount)
})

const artistFollowersLabel = (artist: Artist) => {
  const count = artist.followers_count ?? 0
  return formatFollowersCount(count)
}

const playlistSubtitle = (playlist: Playlist) => {
  if (playlist.title === FAVORITES_PLAYLIST_TITLE) {
    return tl('Playlist')
  }

  const count = playlist.songs_count ?? playlist.songs?.length ?? 0
  return formatTracksCount(count)
}

const userSubtitle = (user: PublicProfileUser) => {
  const followersCount = user.followers_count ?? 0
  return formatFollowersCount(followersCount)
}

const toggleUserFollow = async () => {
  if (!profile.value?.user.uid || followBusy.value || isOwnProfile.value) {
    return
  }

  followBusy.value = true
  errorMessage.value = ''

  try {
    const response = profile.value.user.is_following
      ? await unfollowUser(profile.value.user.uid)
      : await followUser(profile.value.user.uid)

    if (!response.success) {
      errorMessage.value = response.message || tl('Could not update follow state')
      return
    }

    profile.value = {
      ...profile.value,
      user: {
        ...profile.value.user,
        ...(response.data || {}),
      },
    }
  } finally {
    followBusy.value = false
  }
}

const messageUser = async () => {
  if (!profile.value?.user.uid || isOwnProfile.value || messageBusy.value) {
    return
  }

  messageBusy.value = true
  try {
    await openConversationWithUser(profile.value.user.uid)
  } finally {
    messageBusy.value = false
  }
}

onMounted(async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const uid = String(route.params.uid || '')
    const [user, publicProfile] = await Promise.all([
      getCurrentUser(),
      getPublicProfile(uid),
    ])

    if (!user) {
      await navigateTo('/login')
      return
    }

    currentUserUid.value = user.uid
    currentUserRole.value = user.role || ''
    profile.value = publicProfile
  } catch (error: any) {
    console.error('Public profile loading error:', error)
    errorMessage.value = error?.data?.message || tl('Failed to load user library')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.user-library-page {
  min-height: 100%;
  display: grid;
  gap: 24px;
}

.user-library-hero {
  position: relative;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  padding: 18px 20px;
  padding-right: 210px;
  border: 1px solid var(--color-card-border);
  border-radius: var(--radius-card);
  background:
    radial-gradient(circle at top right, rgba(0, 163, 255, 0.16), transparent 34%),
    linear-gradient(180deg, var(--color-bg-soft) 0%, var(--color-surface) 100%);
  box-shadow: var(--color-card-shadow);
}

.user-library-hero__main {
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 18px;
  flex: 1 1 auto;
}

.user-library-hero__avatar {
  width: 132px;
  height: 132px;
  flex: 0 0 auto;
}

.user-library-hero__copy {
  display: grid;
  gap: 6px;
  min-width: 0;
}

.user-library-hero__copy h1,
.user-library-hero__copy p {
  margin: 0;
}

.user-library-hero__copy h1 {
  font-size: clamp(2rem, 4vw, 3.2rem);
  line-height: 0.96;
}

.user-library-hero__copy p {
  color: var(--color-text-muted);
}

.user-library-hero__eyebrow {
  color: var(--color-primary);
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.user-library-hero__artist-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 0 18px;
  border: 1px solid var(--button-secondary-border);
  border-radius: 999px;
  color: var(--button-secondary-text);
  background: var(--button-secondary-bg);
  text-decoration: none;
  font-weight: 800;
}

.user-library-hero__admin-link,
.user-library-hero__message {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 0 18px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
  text-decoration: none;
}

.user-library-hero__artist-link:hover {
  background: var(--button-secondary-hover);
}

.user-library-hero__admin-link:hover,
.user-library-hero__message:hover:not(:disabled) {
  background: var(--button-primary-hover);
}

.user-library-hero__message:disabled {
  opacity: 0.58;
  cursor: wait;
}

.user-library-hero__actions {
  position: absolute;
  top: 18px;
  right: 20px;
  bottom: 18px;
  display: grid;
  gap: 12px;
  justify-items: end;
  align-content: space-between;
}

.user-library-hero__action-row {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.user-library-hero__follow {
  justify-self: end;
}

.library-section {
  display: grid;
  gap: 14px;
}

.library-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
}

@media (max-width: 820px) {
  .user-library-hero {
    flex-direction: column;
    align-items: flex-start;
    padding-right: 20px;
  }

  .user-library-hero__main {
    width: 100%;
    align-items: flex-start;
    flex-direction: column;
  }

  .user-library-hero__actions {
    position: static;
    width: 100%;
    justify-items: start;
  }

  .user-library-hero__action-row {
    justify-content: flex-start;
    flex-wrap: wrap;
  }
}

@media (max-width: 760px) {
  .library-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
