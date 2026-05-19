<template>
  <EditorPageTemplate
    class="album-page"
    :title="pageTitle"
    :submitLabel="submitLabel"
    :loadingLabel="loadingLabel"
    :loading="loading"
    contentWidth="900px"
    @submit="onSubmit"
  >
    <FormNotice v-if="pageError" variant="error" :message="pageError" />

    <template v-else>
      <UserDataFields
        label="title"
        type="text"
        name="title"
        :show-validation-errors="false"
        input-credentials="inputCredentials"
        v-model="form.title"
        :v$="null"
      />

      <section v-if="isEditMode && currentCoverUrl && !form.cover_image" class="current-cover">
        <span class="current-cover__label">{{ tl('Current cover') }}</span>
        <img :src="currentCoverUrl" :alt="form.title || tl('Album cover')" class="current-cover__image" />
      </section>

      <ImageCropUpload
        ref="coverCropUpload"
        :label="isEditMode ? tl('Replace album cover') : tl('Album cover')"
        item-extension="image/jpeg,image/png"
        :help-text="isEditMode ? tl('Leave empty to keep the current cover') : tl('Supported formats: JPEG, PNG. Maximum size: 10MB')"
        :max-size="10 * 1024 * 1024"
        shape="square"
        @file-selected="handleFileSelected"
        @file-error="handleFileError"
        @file-cleared="handleFileCleared"
      />

      <section class="tracks-panel">
        <h2 v-if="isEditMode">{{ tl('Tracks') }}</h2>
        <button v-if="!isTrackEditorOpen" class="add-track-button" type="button" @click="openTrackEditor">
          {{ tl('Add track') }}
        </button>

        <div v-if="isTrackEditorOpen" class="track-editor">
          <label class="track-field">
            <span>{{ tl('Track title') }}</span>
            <input
              v-model="trackDraft.title"
              class="track-field__input"
              type="text"
              :placeholder="tl('Track name')"
            />
          </label>

          <label class="track-field">
            <span>{{ tl('Genre') }}</span>
            <select v-model="trackDraft.genre_id" class="track-field__input">
              <option value="" disabled>{{ tl('Select genre') }}</option>
              <option v-for="genre in genres" :key="genre.id" :value="String(genre.id)">
                {{ genre.name }}
              </option>
            </select>
          </label>

          <label class="track-field">
            <span>{{ tl('Audio file') }}</span>
            <input
              class="track-field__input"
              type="file"
              accept="audio/mpeg,audio/wav,audio/ogg,audio/flac,audio/aac,.mp3,.wav,.ogg,.flac,.aac"
              @change="handleTrackFileSelected"
            />
            <small class="track-field__hint">
              {{ trackEditorHint }}
            </small>
          </label>

          <div class="track-editor__actions">
            <button class="track-field__button" type="button" @click="saveTrack">
              {{ trackEditorMode === 'create' ? tl('Save track') : tl('Save changes') }}
            </button>
            <button class="track-cancel" type="button" @click="closeTrackEditor">
              {{ tl('Cancel') }}
            </button>
          </div>
        </div>

        <table v-if="allTracks.length" class="tracks-table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ tl('Title') }}</th>
              <th>{{ tl('Genre') }}</th>
              <th>{{ tl('Audio') }}</th>
              <th>{{ tl('Duration') }}</th>
              <th class="tracks-table__action-col"></th>
              <th class="tracks-table__action-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(track, index) in allTracks" :key="track.id || track.localKey || `${track.title}-${index}`">
              <td>{{ index + 1 }}</td>
              <td>{{ track.title }}</td>
              <td>{{ track.genre?.name || '-' }}</td>
              <td>{{ track.audioLabel || tl('Uploaded') }}</td>
              <td>{{ formatDuration(track.duration) }}</td>
              <td class="tracks-table__action-cell">
                <button
                  v-if="track.id && !track.isNew && isEditMode"
                  class="track-action track-action--edit"
                  type="button"
                  @click="editExistingTrack(track)"
                >
                  {{ tl('Edit') }}
                </button>
                <button
                  v-if="track.isNew"
                  class="track-action track-action--edit"
                  type="button"
                  @click="editNewTrack(track.localKey)"
                >
                  {{ tl('Edit') }}
                </button>
              </td>
              <td class="tracks-table__action-cell">
                <button
                  v-if="track.id && !track.isNew && isEditMode"
                  class="track-action track-action--remove"
                  type="button"
                  :disabled="deletingTrackId === track.id"
                  @click="removeExistingTrack(track.id)"
                >
                  {{ deletingTrackId === track.id ? tl('Removing...') : tl('Remove') }}
                </button>
                <button
                  v-if="track.isNew"
                  class="track-action track-action--remove"
                  type="button"
                  @click="removeNewTrack(track.localKey)"
                >
                  {{ tl('Remove') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="tracks-empty">{{ tl('No tracks in this album yet.') }}</p>
      </section>

      <FormNotice v-if="errorMessage" variant="error" :message="errorMessage" />
    </template>

    <template #footer>
      <CancelButton
        :disabled="loading"
        :label="isEditMode ? tl('Reset changes') : tl('Clear form')"
        @click="resetForm"
      />
    </template>
  </EditorPageTemplate>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue'
import { useEditorLayout } from '~/composables/useEditorLayout'
import type { Artist as ArtistEntity } from '~/composables/useArtist'
import { formatDuration, getAudioDuration } from '~/utils/audioDuration'

interface Track {
  id?: number
  title: string
  localKey?: string
  isNew?: boolean
  replacesTrackId?: number
  genre_id?: string | number
  audio_file?: File | null
  audioLabel?: string
  duration?: number | null
  genre?: {
    id: number
    name: string
  }
}

interface Genre {
  id: number
  name: string
}

interface Album {
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

const props = withDefaults(defineProps<{
  mode: 'create' | 'edit'
  artistId?: string
  albumId?: string
}>(), {
  artistId: '',
  albumId: '',
})

const isEditMode = computed(() => props.mode === 'edit')
const { tl } = useLocalizedText()
const pageTitle = computed(() => (isEditMode.value ? tl('Edit album') : tl('Create an album')))
const submitLabel = computed(() => (isEditMode.value ? tl('Save changes') : tl('Create')))
const loadingLabel = computed(() => (isEditMode.value ? tl('Saving...') : tl('Creating...')))

const router = useRouter()
const { getCurrentUser } = useAuth()
const { getArtistAlbum, createAlbum, updateAlbum, deleteTrack } = useAlbum()
const { getCurrentArtist } = useArtist()
const { getGenres } = useGenre()
const { mediaUrl } = useMediaUrl()
const { setEditorPanel, clearEditorPanel } = useEditorLayout()

const coverCropUpload = ref<{ reset: () => void } | null>(null)
const album = ref<Album | null>(null)
const currentArtist = ref<ArtistEntity | null>(null)
const loading = ref(false)
const pageError = ref('')
const errorMessage = ref('')
const deletingTrackId = ref<number | null>(null)
const replacingTrackIds = ref<number[]>([])
const initialTitle = ref('')
const initialCoverUrl = ref('')
const coverPreviewUrl = ref('')
const genres = ref<Genre[]>([])
const isTrackEditorOpen = ref(false)
const newTracks = ref<Track[]>([])
const trackEditorMode = ref<'create' | 'edit-new' | 'replace-existing'>('create')
const editingTrackLocalKey = ref<string | null>(null)
const editingExistingTrackId = ref<number | null>(null)
const trackDraft = ref<{
  title: string
  genre_id: string
  audio_file: File | null
  duration: number | null
}>({
  title: '',
  genre_id: '',
  audio_file: null,
  duration: null,
})
const form = ref<{
  title: string
  cover_image: File | null
}>({
  title: '',
  cover_image: null,
})

const currentCoverUrl = computed(() => {
  return coverPreviewUrl.value || initialCoverUrl.value
})
const trackEditorHint = computed(() => {
  const baseHint = trackEditorMode.value === 'edit-new'
    ? tl('Leave empty to keep current uploaded audio')
    : tl('Select audio file')

  if (trackDraft.value.duration == null) {
    return baseHint
  }

  return `${baseHint} • ${formatDuration(trackDraft.value.duration)}`
})
const existingTracks = computed(() => {
  if (!isEditMode.value) {
    return []
  }

  return (album.value?.songs || [])
    .filter((track) => !replacingTrackIds.value.includes(track.id || -1))
    .map((track) => ({
    ...track,
    audioLabel: tl('Existing track'),
    isNew: false,
  }))
})
const allTracks = computed(() => [...existingTracks.value, ...newTracks.value])
const editorChecklist = computed(() => {
  if (isEditMode.value) {
    return [
      tl('Review title and cover'),
      tl('Replace or remove tracks if needed'),
      tl('Save changes when the release looks right'),
    ]
  }

  return [
    tl('Add an album title'),
    tl('Upload a square cover'),
    tl('Prepare at least one track before publishing'),
  ]
})
const editorTrackStatus = computed(() => {
  if (isTrackEditorOpen.value) {
    return tl('Track editor is open')
  }

  if (newTracks.value.length) {
    return tl('{count} pending track(s)', { count: newTracks.value.length })
  }

  return tl('No pending new tracks')
})

const handleFileSelected = (file: File) => {
  form.value.cover_image = file
  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value)
  }
  coverPreviewUrl.value = URL.createObjectURL(file)
  errorMessage.value = ''
}

const handleFileError = (error: any) => {
  console.error('Album cover upload error:', error)
  errorMessage.value = error?.message || tl('File error occurred')
}

const handleFileCleared = () => {
  form.value.cover_image = null
  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value)
    coverPreviewUrl.value = ''
  }
}

const openTrackEditor = () => {
  trackEditorMode.value = 'create'
  editingTrackLocalKey.value = null
  editingExistingTrackId.value = null
  isTrackEditorOpen.value = true
  errorMessage.value = ''
}

const closeTrackEditor = () => {
  isTrackEditorOpen.value = false
  trackDraft.value = {
    title: '',
    genre_id: '',
    audio_file: null,
    duration: null,
  }
  trackEditorMode.value = 'create'
  editingTrackLocalKey.value = null
  editingExistingTrackId.value = null
}

const handleTrackFileSelected = async (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  trackDraft.value.audio_file = file

  if (!file) {
    trackDraft.value.duration = null
    return
  }

  try {
    trackDraft.value.duration = await getAudioDuration(file)
    errorMessage.value = ''
  } catch (error) {
    console.error('Track metadata error:', error)
    trackDraft.value.duration = null
    errorMessage.value = tl('Could not read track duration. The track can still be uploaded.')
  }
}

const saveTrack = () => {
  const title = trackDraft.value.title.trim()
  const genre = genres.value.find((item) => String(item.id) === trackDraft.value.genre_id)

  if (!title || !trackDraft.value.genre_id || !genre) {
    errorMessage.value = tl('Fill track title and genre')
    return
  }

  const mustHaveAudioFile =
    trackEditorMode.value === 'create' || trackEditorMode.value === 'replace-existing'

  if (mustHaveAudioFile && !trackDraft.value.audio_file) {
    errorMessage.value = tl('Upload an audio file')
    return
  }

  if (trackEditorMode.value === 'edit-new') {
    const editingKey = editingTrackLocalKey.value
    if (!editingKey) {
      errorMessage.value = tl('Track edit state is invalid')
      return
    }

    const trackIndex = newTracks.value.findIndex((track) => track.localKey === editingKey)
    if (trackIndex === -1) {
      errorMessage.value = tl('Track not found')
      return
    }

    const existingTrack = newTracks.value[trackIndex]
    if (!existingTrack) {
      errorMessage.value = tl('Track not found')
      return
    }

    const nextAudioFile = trackDraft.value.audio_file || existingTrack.audio_file || null
    const nextDuration = trackDraft.value.audio_file
      ? trackDraft.value.duration
      : existingTrack.duration ?? null

    if (!nextAudioFile) {
      errorMessage.value = tl('Upload an audio file')
      return
    }

    newTracks.value[trackIndex] = {
      ...existingTrack,
      title,
      genre_id: trackDraft.value.genre_id,
      genre: {
        id: genre.id,
        name: genre.name,
      },
      audio_file: nextAudioFile,
      audioLabel: nextAudioFile.name,
      duration: nextDuration,
      isNew: true,
    }

    closeTrackEditor()
    errorMessage.value = ''
    return
  }

  const audioFile = trackDraft.value.audio_file as File
  newTracks.value.push({
    localKey: `${Date.now()}-${Math.random()}`,
    title,
    genre_id: trackDraft.value.genre_id,
    genre: {
      id: genre.id,
      name: genre.name,
    },
    audio_file: audioFile,
    audioLabel: audioFile.name,
    duration: trackDraft.value.duration,
    isNew: true,
    replacesTrackId: trackEditorMode.value === 'replace-existing' ? (editingExistingTrackId.value || undefined) : undefined,
  })

  if (trackEditorMode.value === 'replace-existing' && editingExistingTrackId.value) {
    replacingTrackIds.value.push(editingExistingTrackId.value)
  }

  closeTrackEditor()
  errorMessage.value = ''
}

const editNewTrack = (localKey?: string) => {
  if (!localKey) {
    return
  }

  const track = newTracks.value.find((item) => item.localKey === localKey)
  if (!track) {
    return
  }

  trackEditorMode.value = 'edit-new'
  editingTrackLocalKey.value = localKey
  editingExistingTrackId.value = null
  trackDraft.value = {
    title: track.title,
    genre_id: String(track.genre_id || ''),
    audio_file: null,
    duration: track.duration ?? null,
  }
  isTrackEditorOpen.value = true
  errorMessage.value = ''
}

const editExistingTrack = (track?: Track) => {
  if (!track?.id) {
    return
  }

  trackEditorMode.value = 'replace-existing'
  editingTrackLocalKey.value = null
  editingExistingTrackId.value = track.id
  trackDraft.value = {
    title: track.title,
    genre_id: String(track.genre?.id || track.genre_id || ''),
    audio_file: null,
    duration: track.duration ?? null,
  }
  isTrackEditorOpen.value = true
  errorMessage.value = ''
}

const removeNewTrack = (localKey?: string) => {
  if (!localKey) {
    return
  }

  const trackToRemove = newTracks.value.find((track) => track.localKey === localKey)
  if (trackToRemove?.replacesTrackId) {
    replacingTrackIds.value = replacingTrackIds.value.filter((id) => id !== trackToRemove.replacesTrackId)
  }

  newTracks.value = newTracks.value.filter((track) => track.localKey !== localKey)
}

const removeExistingTrack = async (trackId?: number) => {
  if (!isEditMode.value || !trackId || deletingTrackId.value) {
    return
  }

  deletingTrackId.value = trackId
  errorMessage.value = ''

  try {
    await deleteTrack(trackId)

    if (album.value?.songs) {
      album.value.songs = album.value.songs.filter((track) => track.id !== trackId)
    }
  } catch (error: any) {
    console.error('Track delete failed:', error)
    errorMessage.value = error?.data?.message || tl('Failed to delete track')
  } finally {
    deletingTrackId.value = null
  }
}

const resetForm = () => {
  form.value.title = isEditMode.value ? initialTitle.value : ''
  form.value.cover_image = null
  newTracks.value = []
  replacingTrackIds.value = []
  errorMessage.value = ''
  closeTrackEditor()
  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value)
    coverPreviewUrl.value = ''
  }
  coverCropUpload.value?.reset()
}

const onSubmit = async () => {
  if (pageError.value) {
    return
  }

  if (!isEditMode.value && !form.value.cover_image) {
    errorMessage.value = tl('Save album cover before creating album')
    return
  }

  if (!newTracks.value.length) {
    errorMessage.value = tl('Add at least one track')
    return
  }

  loading.value = true
  errorMessage.value = ''

  const tracksPayload = newTracks.value.map((track) => ({
    title: track.title,
    genre_id: track.genre_id || '',
    audio_file: track.audio_file as File,
    duration: track.duration ?? null,
  }))

  try {
    if (isEditMode.value) {
      const tracksToDelete = newTracks.value
        .map((track) => track.replacesTrackId)
        .filter((trackId): trackId is number => Boolean(trackId))

      for (const trackId of tracksToDelete) {
        await deleteTrack(trackId)
      }

      const response = await updateAlbum(props.albumId, {
        title: form.value.title,
        cover_image: form.value.cover_image,
        tracks: tracksPayload,
      })

      if (!response.success) {
        errorMessage.value = response.message || tl('Failed to update album')
        return
      }

      await router.push(`/albums/${props.artistId}/${props.albumId}`)
      return
    }

    const response = await createAlbum({
      title: form.value.title,
      cover_image: form.value.cover_image,
      tracks: tracksPayload,
    })

    if (response.success) {
      resetForm()
      return
    }

    errorMessage.value = response.message || tl('Failed to create album')
  } catch (error) {
    console.error('Album submit failed:', error)
    errorMessage.value = tl('An unexpected error occurred')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  loading.value = true
  pageError.value = ''
  errorMessage.value = ''

  try {
    genres.value = await getGenres()

    if (!isEditMode.value) {
      return
    }

    if (!props.artistId || !props.albumId) {
      pageError.value = tl('Album id is missing')
      return
    }

    const [albumData, userData] = await Promise.all([
      getArtistAlbum(props.artistId, props.albumId),
      getCurrentUser(),
    ])

    album.value = albumData
    initialTitle.value = albumData.title
    initialCoverUrl.value = albumData.cover_image_url || mediaUrl(albumData.cover_image_path)
    form.value.title = albumData.title

    currentArtist.value = await getCurrentArtist()

    if (!currentArtist.value?.id || currentArtist.value.id !== albumData.artist?.id) {
      pageError.value = tl('Only the album owner can edit this album')
    }
  } catch (error: any) {
    console.error('Album page loading error:', error)
    pageError.value = error?.data?.message || tl('Failed to load album data')
  } finally {
    loading.value = false
  }
})

watchEffect(() => {
  setEditorPanel({
    eyebrow: isEditMode.value ? tl('Album editor') : tl('New release'),
    title: form.value.title.trim() || pageTitle.value,
    description: isEditMode.value
      ? tl('Update the album metadata, artwork and track list before saving.')
      : tl('Build the release cover and track list before creating the album.'),
    imageUrl: currentCoverUrl.value,
    imageShape: 'square',
    imageFallback: form.value.title.trim().slice(0, 1).toUpperCase() || 'A',
    stats: [
      { label: tl('Mode'), value: isEditMode.value ? tl('Edit') : tl('Create') },
      { label: tl('Tracks'), value: allTracks.value.length },
      { label: tl('Artwork'), value: currentCoverUrl.value ? tl('Ready') : tl('Missing') },
      { label: tl('Track editor'), value: isTrackEditorOpen.value ? tl('Open') : tl('Closed') },
    ],
    sections: [
      {
        title: tl('Checklist'),
        items: editorChecklist.value,
      },
      {
        title: tl('Track state'),
        items: [
          editorTrackStatus.value,
          tl('{count} existing track(s)', { count: existingTracks.value.length }),
          tl('{count} new track(s) in queue', { count: newTracks.value.length }),
        ],
      },
    ],
    note: errorMessage.value || pageError.value || '',
  })
})

onBeforeUnmount(() => {
  clearEditorPanel()

  if (coverPreviewUrl.value) {
    URL.revokeObjectURL(coverPreviewUrl.value)
  }
})
</script>

<style scoped>
:deep(.album-page .credentials-form) {
  width: min(780px, 100%);
}

:deep(.album-page .credentials-form .file-drop-container),
:deep(.album-page .credentials-form .tracks-panel),
:deep(.album-page .credentials-form .field) {
  width: 100%;
  max-width: 100%;
}

.current-cover {
  width: 100%;
  display: grid;
  gap: 12px;
}

.current-cover__label {
  color: var(--color-text-main);
  font-weight: 700;
}

.current-cover__image {
  width: 180px;
  aspect-ratio: 1;
  display: block;
  object-fit: cover;
  border-radius: 12px;
  border: 1px solid var(--color-border);
}

.tracks-panel {
  width: 100%;
  display: grid;
  gap: 14px;
}

.tracks-panel h2 {
  margin: 0;
  color: var(--color-text-main);
  font-size: 1.1rem;
}

.add-track-button {
  width: max-content;
  min-height: 38px;
  padding: 0 18px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  box-shadow: var(--shadow-primary);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.track-editor {
  width: 100%;
  display: grid;
  gap: 14px;
  padding: 16px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 16px;
  box-shadow: var(--shadow-card);
}

.track-field {
  width: 100%;
  display: grid;
  gap: 6px;
  color: var(--color-text-main);
}

.track-field > span {
  font-size: 14px;
}

.track-field__hint {
  color: var(--color-text-soft);
  font-size: 12px;
}

.track-field__input {
  width: 100%;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid var(--color-input-border);
  background: var(--color-input-bg);
  color: var(--color-input-text);
  outline: none;
}

.track-editor__actions {
  display: flex;
  gap: 10px;
}

.track-field__button,
.track-cancel,
.track-remove {
  border-radius: 999px;
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.track-field__button {
  min-width: 120px;
  min-height: 36px;
  padding: 0 16px;
  border: 1px solid var(--button-primary-border);
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  box-shadow: var(--shadow-primary);
}

.track-cancel {
  min-height: 36px;
  padding: 0 16px;
  border: 1px solid var(--button-secondary-border);
  color: var(--button-secondary-text);
  background: var(--button-secondary-bg);
}

.tracks-table {
  width: 100%;
  border-collapse: collapse;
  color: var(--color-text-main);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: var(--shadow-card);
}

.tracks-table th,
.tracks-table td {
  padding: 12px;
  border-bottom: 1px solid var(--color-border);
  text-align: left;
}

.tracks-table th:first-child,
.tracks-table td:first-child {
  width: 52px;
  color: var(--color-text-muted);
}

.tracks-table__action-col,
.tracks-table__action-cell {
  width: 116px;
  text-align: right;
}

.track-action {
  width: 100%;
  padding: 7px 12px;
  border-radius: 999px;
  font-family: 'Spotcast', sans-serif;
  font-size: 0.95rem;
  font-weight: 500;
}

.track-action--edit {
  border: 1px solid var(--button-secondary-border);
  color: var(--button-secondary-text);
  background: var(--button-secondary-bg);
}

.track-action--remove {
  border: 1px solid var(--button-danger-border);
  color: var(--button-danger-text);
  background: var(--button-danger-bg);
}

.tracks-empty {
  margin: 0;
  color: var(--color-text-muted);
}

</style>
