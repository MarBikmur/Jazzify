<template>
  <ImageEntityEditorPage
    ref="playlistEditorPage"
    :title="tl('New playlist')"
    :submitLabel="tl('Create playlist')"
    :loadingLabel="tl('Creating...')"
    :loading="isSubmitting"
    :fieldLabel="tl('Playlist name')"
    fieldName="title"
    v-model="form.title"
    :imageLabel="tl('Cover (optional)')"
    imageHelpText="JPEG or PNG, max 10MB"
    imageShape="square"
    :errorMessage="errorMessage"
    :panelEyebrow="tl('Playlist editor')"
    :panelDescription="tl('Shape the playlist title and cover before it goes into the library.')"
    :panelTips="playlistPanelTips"
    :panelImageUrl="coverPreviewUrl"
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
import { ref, onBeforeUnmount, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '~/composables/useAuth'
import { useLocalizedText } from '~/composables/useLocalizedText'
import { usePlaylist } from '~/composables/usePlaylist'

const router = useRouter()
const { tl } = useLocalizedText()
const { getCurrentUser } = useAuth()
const { createPlaylist } = usePlaylist()

const playlistEditorPage = ref<{ resetImage?: () => void; ensureImageSaved?: () => Promise<File | null> } | null>(null)
const form = ref({ title: '' as string })
const cover = ref<File | null>(null)
const isPrivate = ref(false)
const coverPreviewUrl = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')
const playlistPanelTips = [
  tl('Name the playlist clearly'),
  tl('Choose a square cover image'),
  tl('Decide whether the playlist is public or private'),
]

const handleFileSelected = (file: File) => {
  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value)
  }

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
  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value)
    coverPreviewUrl.value = ''
  }
}

onMounted(async () => {
  try {
    const user = await getCurrentUser()
    if (!user) {
      await router.replace('/login')
    }
  } catch {
    await router.replace('/login')
  }
})

const onSubmit = async () => {
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

    const result = await createPlaylist({
      title: form.value.title.trim(),
      cover: cover.value,
      is_private: isPrivate.value,
    })
    if (result.success && result.data?.id) {
      form.value.title = ''
      cover.value = null
      isPrivate.value = false
      if (coverPreviewUrl.value) {
        URL.revokeObjectURL(coverPreviewUrl.value)
        coverPreviewUrl.value = ''
      }
      playlistEditorPage.value?.resetImage?.()
      await router.push(`/playlists/${result.data.id}`)
      return
    }
    errorMessage.value = result.message || tl('Failed to create playlist')
  } catch (e) {
    console.error(e)
    errorMessage.value = tl('Failed to create playlist')
  } finally {
    isSubmitting.value = false
  }
}

onBeforeUnmount(() => {
  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value)
  }
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
