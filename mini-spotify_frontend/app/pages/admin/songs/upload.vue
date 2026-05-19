<template>
  <AdminRecordTemplate
    eyebrow="Songs"
    title="Upload track"
    back-to="/admin/songs"
    back-label="Back to songs"
    :public-url="createdResult?.public_url || duplicate?.public_url || null"
    :error-message="errorMessage"
  >
    <form class="admin-song-upload" @submit.prevent="analyze">
      <label class="admin-song-upload__field">
        <span>{{ tl('Audio file') }}</span>
        <input
          type="file"
          accept=".mp3,.wav,.ogg,.flac,.aac,.m4a,audio/*"
          @change="onFileChange"
        >
      </label>

      <p class="admin-song-upload__hint">
        {{ tl('First we read the file metadata and build a Spotify-based draft. Nothing is saved to the database until you confirm creation.') }}
      </p>

      <div class="admin-song-upload__actions">
        <button
          type="submit"
          class="admin-song-upload__submit"
          :disabled="isBusy || !file"
        >
          {{ analyzing ? tl('Analyzing...') : tl('Analyze track') }}
        </button>
      </div>
    </form>

    <section v-if="busyMessage" class="admin-song-upload__notice admin-song-upload__notice--info">
      <strong>{{ busyTitle }}</strong>
      <span>{{ busyMessage }}</span>
    </section>

    <section v-if="duplicate" class="admin-song-upload__notice admin-song-upload__notice--warning">
      <strong>{{ tl('Duplicate track found') }}</strong>
      <span>
        {{ duplicate.title || draft?.title || tl('This track') }} {{ tl('already exists') }}
        <template v-if="duplicate.artist"> {{ tl('by') }} {{ duplicate.artist }}</template>
        <template v-if="duplicate.album"> {{ tl('on') }} {{ duplicate.album }}</template>.
      </span>
      <span>{{ tl('You can still re-upload it to refresh the audio file and save the Spotify metadata into the existing record.') }}</span>
      <NuxtLink
        v-if="duplicate.public_url"
        class="admin-song-upload__inline-link"
        :to="duplicate.public_url"
      >
        {{ tl('Open existing record') }}
      </NuxtLink>
    </section>

    <section v-if="warnings.length" class="admin-song-upload__notice admin-song-upload__notice--warning">
      <strong>{{ tl('Import warnings') }}</strong>
      <ul class="admin-song-upload__warning-list">
        <li v-for="warning in warnings" :key="warning">
          {{ warning }}
        </li>
      </ul>
    </section>

    <section v-if="draft" class="admin-song-upload__draft">
      <header class="admin-song-upload__draft-header">
        <h2>{{ tl('Review draft') }}</h2>
        <p>{{ draftSummary }}</p>
      </header>

      <form class="admin-song-upload__draft-form" @submit.prevent="createTrack">
        <div class="admin-song-upload__grid">
          <label class="admin-song-upload__field">
            <span>{{ tl('Title') }}</span>
            <input v-model="draft.title" type="text">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Artist') }}</span>
            <input v-model="draft.artist" type="text">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Album') }}</span>
            <input v-model="draft.album" type="text">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Genre') }}</span>
            <input v-model="draft.genre" type="text">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Duration') }}</span>
            <input v-model="draft.duration" type="number" min="0">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Release date') }}</span>
            <input v-model="draft.release_date" type="date">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Tempo') }}</span>
            <input v-model="draft.tempo" type="number" min="0" step="0.01">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Energy') }}</span>
            <input v-model="draft.energy" type="number" min="0" max="1" step="0.0001">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Danceability') }}</span>
            <input v-model="draft.danceability" type="number" min="0" max="1" step="0.0001">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Valence') }}</span>
            <input v-model="draft.valence" type="number" min="0" max="1" step="0.0001">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Play count') }}</span>
            <input v-model.number="draft.play_count" type="number" min="0">
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Popularity (derived)') }}</span>
            <input :value="derivedPopularity" type="number" min="0" max="100" readonly>
          </label>
          <label class="admin-song-upload__field">
            <span>{{ tl('Spotify track ID') }}</span>
            <input v-model="draft.spotify_track_id" type="text">
          </label>
        </div>

        <div class="admin-song-upload__meta">
          <span>{{ tl('Spotify') }}: {{ spotifyUsed ? tl('Matched') : tl('No match') }}</span>
          <span>{{ tl('Audio features') }}: {{ audioFeaturesUsed ? tl('Loaded') : tl('Manual input required') }}</span>
          <span>{{ tl('Duration') }}: {{ formatDuration(draft.duration) }}</span>
          <span>{{ tl('Popularity') }}: {{ derivedPopularity }}</span>
        </div>

        <p v-if="missingRecommendationFields.length" class="admin-song-upload__hint">
          {{ tl('Optional recommendation fields left blank:') }}
          {{ missingRecommendationFields.join(', ') }}.
        </p>

        <div class="admin-song-upload__actions">
          <button
            type="submit"
            class="admin-song-upload__submit"
            :disabled="isBusy || !canCreateTrack"
        >
          {{ saving ? tl('Saving...') : duplicate ? tl('Re-upload existing track') : tl('Create track') }}
        </button>
      </div>
    </form>
    </section>

    <section v-if="createdResult" class="admin-song-upload__notice admin-song-upload__notice--success">
      <strong>{{ tl('Track created') }}</strong>
      <span>
        {{ createdResult.song?.title || tl('Track') }}
        {{ createdResult.reuploaded ? tl('was re-uploaded and updated in the catalog.') : tl('was added to the catalog.') }}
      </span>
      <NuxtLink
        v-if="createdResult.song?.id"
        class="admin-song-upload__inline-link"
        :to="`/admin/songs/${createdResult.song.id}`"
      >
        {{ tl('Edit song record') }}
      </NuxtLink>
    </section>
  </AdminRecordTemplate>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { AdminSongCreateResponse, AdminSongUploadResponse } from '~/composables/useAdmin'
import { getAudioDuration } from '~/utils/audioDuration'

definePageMeta({
  layout: 'admin',
})

const { tl } = useLocalizedText()
const { analyzeAdminSong, createAdminSong } = useAdmin()

const file = ref<File | null>(null)
const fileDuration = ref<number | null>(null)
const analyzing = ref(false)
const saving = ref(false)
const errorMessage = ref('')
const draft = ref<Record<string, any> | null>(null)
const duplicate = ref<AdminSongUploadResponse['duplicate'] | null>(null)
const createdResult = ref<AdminSongCreateResponse | null>(null)
const spotifyUsed = ref(false)
const audioFeaturesUsed = ref(false)
const warnings = ref<string[]>([])

const isBusy = computed(() => analyzing.value || saving.value)
const derivedPopularity = computed(() => popularityFromPlayCount(draft.value?.play_count))

const onFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement
  file.value = target.files?.[0] || null
  fileDuration.value = null
  draft.value = null
  duplicate.value = null
  createdResult.value = null
  spotifyUsed.value = false
  audioFeaturesUsed.value = false
  warnings.value = []
  errorMessage.value = ''

  if (!file.value) {
    return
  }

  try {
    fileDuration.value = await getAudioDuration(file.value)
  } catch (error) {
    console.error('Track metadata error:', error)
    errorMessage.value = tl('Could not read track duration from the file in browser metadata.')
  }
}

const draftSummary = computed(() => {
  if (!draft.value) {
    return ''
  }

  if (spotifyUsed.value && audioFeaturesUsed.value) {
    return tl('Matched in Spotify and loaded recommendation fields. Review the draft and confirm creation.')
  }

  if (spotifyUsed.value) {
    return tl('Matched in Spotify. Review the draft and fill any missing recommendation fields before saving.')
  }

  return tl('Draft created from file metadata. Review the fields before saving.')
})

const missingRecommendationFields = computed(() => {
  if (!draft.value) {
    return []
  }

  const checks: Array<[string, string]> = [
    ['tempo', tl('Tempo')],
    ['energy', tl('Energy')],
    ['danceability', tl('Danceability')],
    ['valence', tl('Valence')],
  ]

  return checks
    .filter(([key]) => draft.value?.[key] === null || draft.value?.[key] === undefined || draft.value?.[key] === '')
    .map(([, label]) => label)
})

const canCreateTrack = computed(() => {
  return !!draft.value
})

watch(
  () => draft.value?.play_count,
  (value) => {
    if (!draft.value) {
      return
    }

    draft.value.play_count = normalizePlayCount(value)
    draft.value.popularity = popularityFromPlayCount(draft.value.play_count)
  },
)

const busyTitle = computed(() => {
  if (saving.value) {
    return duplicate.value ? tl('Re-uploading track') : tl('Creating track')
  }

  return tl('Processing track')
})

const busyMessage = computed(() => {
  if (saving.value) {
    return duplicate.value
      ? tl('Updating the existing catalog record and replacing the audio file. Keep this page open.')
      : tl('Creating the track and saving the uploaded audio file. Keep this page open.')
  }

  if (analyzing.value) {
    return tl('Reading the audio file metadata and looking for Spotify matches.')
  }

  return ''
})

const analyze = async () => {
  if (!file.value) {
    return
  }

  analyzing.value = true
  errorMessage.value = ''
  createdResult.value = null
  spotifyUsed.value = false
  audioFeaturesUsed.value = false
  warnings.value = []

  try {
    const response = await analyzeAdminSong(file.value, fileDuration.value)
    draft.value = {
      ...response.draft,
      play_count: normalizePlayCount(response.draft?.play_count),
    }
    duplicate.value = response.duplicate || null
    spotifyUsed.value = response.spotify_used
    audioFeaturesUsed.value = response.audio_features_used
    warnings.value = response.warnings || []
  } catch (error: any) {
    errorMessage.value = error?.data?.message || tl('Could not analyze track')
  } finally {
    analyzing.value = false
  }
}

const createTrack = async () => {
  if (!file.value || !draft.value || !canCreateTrack.value) {
    return
  }

  saving.value = true
  errorMessage.value = ''

  try {
    createdResult.value = await createAdminSong(file.value, {
      ...draft.value,
      force_reupload: !!duplicate.value,
    })
  } catch (error: any) {
    errorMessage.value = error?.data?.message || tl('Could not create track')
  } finally {
    saving.value = false
  }
}

const formatDuration = (value: unknown) => {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  const totalSeconds = Number(value)
  if (!Number.isFinite(totalSeconds) || totalSeconds < 0) {
    return '—'
  }

  const minutes = Math.floor(totalSeconds / 60)
  const seconds = Math.floor(totalSeconds % 60)
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

const normalizePlayCount = (value: unknown) => {
  const normalized = Number(value)

  if (!Number.isFinite(normalized) || normalized < 0) {
    return 0
  }

  return Math.floor(normalized)
}

const popularityFromPlayCount = (value: unknown) => {
  const plays = normalizePlayCount(value)

  if (plays <= 0) {
    return 0
  }

  return Math.min(100, Math.round((Math.log10(plays + 1) / 5) * 100))
}
</script>

<style scoped>
.admin-song-upload,
.admin-song-upload__draft-form {
  display: grid;
  gap: 14px;
}

.admin-song-upload__field {
  display: grid;
  gap: 10px;
  font-weight: 700;
}

.admin-song-upload__field input {
  min-height: 44px;
  padding: 10px 14px;
  border: 1px solid var(--color-input-border);
  border-radius: 14px;
  color: var(--color-input-text);
  background: var(--color-input-bg);
  font: inherit;
}

.admin-song-upload__hint,
.admin-song-upload__draft-header p {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.92rem;
}

.admin-song-upload__actions,
.admin-song-upload__result-actions {
  display: flex;
  justify-content: flex-start;
}

.admin-song-upload__submit,
.admin-song-upload__inline-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid var(--button-primary-border);
  border-radius: 999px;
  color: var(--button-primary-text);
  background: var(--button-primary-bg);
  font-weight: 800;
  font-size: 0.9rem;
  text-decoration: none;
}

.admin-song-upload__submit:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.admin-song-upload__draft,
.admin-song-upload__notice {
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border);
}

.admin-song-upload__draft-header h2 {
  margin: 0 0 8px;
}

.admin-song-upload__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px 22px;
}

.admin-song-upload__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 20px;
  color: var(--color-text-muted);
  font-weight: 700;
}

.admin-song-upload__notice {
  display: grid;
  gap: 10px;
}

.admin-song-upload__notice--warning {
  color: var(--color-warning-text, #c2892f);
}

.admin-song-upload__notice--info {
  color: var(--color-info-text);
}

.admin-song-upload__notice--success {
  color: var(--color-success-text, #2f9e63);
}

.admin-song-upload__warning-list {
  margin: 0;
  padding-left: 18px;
}

@media (max-width: 860px) {
  .admin-song-upload__grid {
    grid-template-columns: 1fr;
  }
}
</style>
