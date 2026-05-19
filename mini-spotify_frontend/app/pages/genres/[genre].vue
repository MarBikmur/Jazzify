<template>
  <section class="genre-page">
    <div class="genre-page__header">
      <PageSectionHeader :title="decodedGenreName" :subtitle="`${albums.length} albums in this genre`" />
      <NuxtLink
        v-if="isAdmin && currentGenreId"
        class="genre-page__edit"
        :to="`/admin/genres/${currentGenreId}`"
      >
        Edit
      </NuxtLink>
    </div>

    <PageState v-if="loading" message="Loading genre..." min-height="180px" />
    <FormNotice v-else-if="errorMessage" variant="error" :message="errorMessage" />
    <PageState
      v-else-if="!albums.length"
      title="Nothing here yet"
      :message="`No albums found for genre “${decodedGenreName}”.`"
      min-height="180px"
    />

    <div v-else class="genre-grid">
      <MediaTile
        v-for="album in albums"
        :key="album.id"
        :to="album.artist?.id ? `/albums/${album.artist.id}/${album.id}` : ''"
        :title="album.title"
        :subtitle="album.artist?.name || 'Artist'"
        :image-src="albumCover(album)"
        fallback="A"
      />
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Album } from '~/composables/useAlbum'

const route = useRoute()
const { getCurrentUser } = useAuth()
const { getGenres } = useGenre()
const { mediaUrl } = useMediaUrl()
const { getAlbumsByGenre, normalizeQuery } = useSearch()

const loading = ref(false)
const errorMessage = ref('')
const albums = ref<Album[]>([])
const currentGenreId = ref<number | null>(null)
const isAdmin = ref(false)

const decodedGenreName = computed(() => normalizeQuery(decodeURIComponent(String(route.params.genre || ''))))
const albumCover = (album: Album) => album.cover_image_url || mediaUrl(album.cover_image_path)

const loadGenre = async () => {
  errorMessage.value = ''

  if (!decodedGenreName.value) {
    albums.value = []
    return
  }

  loading.value = true

  try {
    const [genreAlbums, user, genres] = await Promise.all([
      getAlbumsByGenre(decodedGenreName.value),
      getCurrentUser(),
      getGenres(),
    ])

    albums.value = genreAlbums
    isAdmin.value = user?.role === 'admin'
    currentGenreId.value = genres.find((genre) => normalizeQuery(genre.name) === decodedGenreName.value)?.id ?? null
  } catch (error: any) {
    console.error('Genre page error:', error)
    errorMessage.value = error?.data?.message || 'Could not load genre albums'
  } finally {
    loading.value = false
  }
}

watch(
  () => route.params.genre,
  () => {
    void loadGenre()
  },
  { immediate: true }
)
</script>

<style scoped>
.genre-page {
  min-height: 100%;
  display: grid;
  gap: 22px;
}

.genre-page__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.genre-page__edit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  text-decoration: none;
  font-weight: 800;
  font-size: 0.84rem;
}

.genre-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 16px;
}

@media (max-width: 760px) {
  .genre-page__header {
    flex-direction: column;
  }

  .genre-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
