<template>
  <section class="playlists-index">
    <PageState v-if="loading" :message="tl('Loading playlists...')" />
    <PageState v-else-if="errorMessage" variant="error" :message="errorMessage" />

    <template v-else>
      <PageSectionHeader :title="tl('Your playlists')" :subtitle="playlistsSubtitle">
        <template #aside>
          <NuxtLink to="/create-playlist" class="new-link">{{ tl('New playlist') }}</NuxtLink>
        </template>
      </PageSectionHeader>

      <div v-if="!playlists.length">
        <PageState :title="tl('No playlists yet')" :message="tl('You have no playlists yet.')" min-height="180px">
        <NuxtLink to="/create-playlist" class="new-link new-link--large">{{ tl('Create one') }}</NuxtLink>
        </PageState>
      </div>

      <div v-else class="grid">
        <MediaTile
          v-for="p in playlists"
          :key="p.id"
          :to="`/playlists/${p.id}`"
          :title="tl(p.title)"
          :subtitle="playlistSubtitle(p)"
          :image-src="cover(p)"
          :fallback-icon="playlistFallbackIcon(p)"
          :fallback-variant="playlistFallbackVariant(p)"
        />
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '~/composables/useAuth'
import { FAVORITES_PLAYLIST_TITLE } from '~/composables/useLikedSongs'
import { usePlaylist, type Playlist } from '~/composables/usePlaylist'
import { useMediaUrl } from '~/composables/useMediaUrl'

const router = useRouter()
const { isRussian } = useAppLocale()
const { tl } = useLocalizedText()
const { getCurrentUser } = useAuth()
const { getMyPlaylists } = usePlaylist()
const { mediaUrl } = useMediaUrl()

const loading = ref(true)
const errorMessage = ref('')
const playlists = ref<Playlist[]>([])

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

const formatPlaylistsCount = (count: number) => {
  if (!isRussian.value) {
    return count === 1 ? '1 playlist' : `${count} playlists`
  }

  return pluralizeRu(count, 'плейлист', 'плейлиста', 'плейлистов')
}

const cover = (p: Playlist) => (p as any).cover_image_url || mediaUrl(p.cover_image_path)
const isLikedPlaylist = (p: Playlist) => p.title === FAVORITES_PLAYLIST_TITLE
const playlistsSubtitle = computed(() => formatPlaylistsCount(playlists.value.length))
const playlistSubtitle = (p: Playlist) => isLikedPlaylist(p)
  ? tl('Playlist')
  : formatTracksCount(p.songs_count ?? p.songs?.length ?? 0)
const playlistFallbackIcon = (p: Playlist) =>
  isLikedPlaylist(p) ? 'material-symbols:favorite' : 'solar:music-note-2-bold'
const playlistFallbackVariant = (p: Playlist) => (isLikedPlaylist(p) ? 'liked' : 'playlist')

onMounted(async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const user = await getCurrentUser()
    if (!user) {
      await router.replace('/login')
      return
    }
    playlists.value = await getMyPlaylists()
  } catch (e: any) {
    errorMessage.value = e?.data?.message || tl('Could not load playlists')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.playlists-index {
  width: 100%;
  min-height: 100%;
  color: var(--color-text-main);
  display: grid;
  gap: 24px;
  padding: 4px 0 32px;
}

.new-link {
  display: inline-flex;
  align-items: center;
  padding: 8px 16px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  background: var(--button-primary-bg);
  color: var(--button-primary-text);
  box-shadow: var(--shadow-primary);
  font-weight: 700;
  text-decoration: none;
  font-size: 0.9rem;
}

.new-link--large {
  margin-top: 8px;
}

.new-link:hover {
  background: var(--button-primary-hover);
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 18px;
}
</style>
