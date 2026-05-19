<template>
  <PageState
    v-if="isPageLoading"
    :message="tl('Loading playlist...')"
    min-height="260px"
  />
  <ImageEntityEditorPage
    v-else
    ref="playlistEditorPage"
    :title="tl('Edit playlist')"
    :submitLabel="tl('Save playlist')"
    :loadingLabel="tl('Saving...')"
    :loading="isSubmitting"
    :fieldLabel="tl('Playlist name')"
    fieldName="title"
    v-model="form.title"
    :imageLabel="tl('Cover (optional)')"
    imageHelpText="JPEG or PNG, max 10MB"
    imageShape="square"
    :errorMessage="errorMessage"
    :panelEyebrow="tl('Playlist editor')"
    :panelDescription="tl('Update the playlist title and cover that listeners see in the library.')"
    :panelTips="playlistPanelTips"
    :panelImageUrl="currentCoverUrl"
    :currentImageUrl="initialCoverUrl"
    :currentImageAlt="form.title || tl('Playlist cover')"
    :currentImageLabel="tl('Current image')"
    :showCurrentImage="!!initialCoverUrl && !coverPreviewUrl"
    panelImageFallback="♪"
    @submit="onSubmit"
    @image-selected="handleFileSelected"
    @image-error="handleFileError"
    @image-cleared="handleFileCleared"
  >
    <template #fields>
      <label class="playlist-privacy-field">
        <input v-model="isPrivate" type="checkbox">
        <span>{{ tl('Private playlist') }}</span>
      </label>
    </template>
  </ImageEntityEditorPage>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '~/composables/useAuth'
import { useLikedSongs } from '~/composables/useLikedSongs'
import { useLocalizedText } from '~/composables/useLocalizedText'
import { useMediaUrl } from '~/composables/useMediaUrl'
import { usePlaylist, type Playlist } from '~/composables/usePlaylist'

const route = useRoute()
const router = useRouter()
const { tl } = useLocalizedText()
const { getCurrentUser } = useAuth()
const { favoritesPlaylistId } = useLikedSongs()
const { mediaUrl } = useMediaUrl()
const { getPlaylist, updatePlaylist } = usePlaylist()

const playlistEditorPage = ref<{ resetImage?: () => void; ensureImageSaved?: () => Promise<File | null> } | null>(null)
const playlist = ref<Playlist | null>(null)
const form = ref({ title: '' as string })
const cover = ref<File | null>(null)
const isPrivate = ref(false)
const initialCoverUrl = ref('')
const coverPreviewUrl = ref('')
const isPageLoading = ref(true)
const isSubmitting = ref(false)
const errorMessage = ref('')
const playlistPanelTips = [
  tl('Rename the playlist clearly'),
  tl('Choose a square cover image'),
  tl('Save changes to update the library view'),
]

const currentCoverUrl = computed(() => coverPreviewUrl.value || initialCoverUrl.value)

const revokePreviewUrl = () => {
  if (coverPreviewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(coverPreviewUrl.value)
  }
}

const handleFileSelected = (file: File) => {
  revokePreviewUrl()
  cover.value = file
  coverPreviewUrl.value = URL.createObjectURL(file)
  errorMessage.value = ''
}

const handleFileError = (error: unknown) => {
  console.error('File upload error:', error)
  errorMessage.value = tl('Cover upload failed')
}

const handleFileCleared = () => {
  cover.value = null
  revokePreviewUrl()
  coverPreviewUrl.value = ''
}

onMounted(async () => {
  isPageLoading.value = true
  try {
    const [user, data] = await Promise.all([
      getCurrentUser(),
      getPlaylist(String(route.params.id || '')),
    ])

    if (!user) {
      await router.replace('/login')
      return
    }

    if (!data?.id) {
      await router.replace('/playlists')
      return
    }

    const isOwner = !!user.uid && user.uid === data.user_uid
    const isFavorites = !!favoritesPlaylistId.value && Number(favoritesPlaylistId.value) === Number(data.id)

    if (!isOwner || isFavorites) {
      await router.replace(`/playlists/${data.id}`)
      return
    }

    playlist.value = data
    form.value.title = data.title
    isPrivate.value = !!data.is_private
    initialCoverUrl.value = data.cover_image_url || mediaUrl(data.cover_image_path)
  } catch (error) {
    console.error(error)
    await router.replace('/playlists')
  } finally {
    isPageLoading.value = false
  }
})

const onSubmit = async () => {
  if (!playlist.value) {
    return
  }

  errorMessage.value = ''

  if (!form.value.title.trim()) {
    errorMessage.value = tl('Enter a name for the playlist')
    return
  }

  isSubmitting.value = true
  try {
    const pendingCover = await playlistEditorPage.value?.ensureImageSaved?.()
    if (pendingCover) {
      cover.value = pendingCover
    }

    const result = await updatePlaylist(playlist.value.id, {
      title: form.value.title.trim(),
      cover: cover.value,
      is_private: isPrivate.value,
    })

    if (result.success && result.data?.id) {
      playlist.value = result.data
      cover.value = null
      await router.push(`/playlists/${result.data.id}`)
      return
    }

    errorMessage.value = result.message || tl('Failed to update playlist')
  } catch (error) {
    console.error(error)
    errorMessage.value = tl('Failed to update playlist')
  } finally {
    isSubmitting.value = false
  }
}

onBeforeUnmount(() => {
  revokePreviewUrl()
})
</script>

<style scoped>
.playlist-privacy-field {
  width: 100%;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text-main);
  font-weight: 700;
}

.playlist-privacy-field input {
  width: 18px;
  height: 18px;
}
</style>
